<?php

namespace App\Queries\Operations;

use App\Domain\Operations\VolumeSpace;
use App\Queries\Inventory\Incidents;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The Owner's system status (REQ-OPS-04; OPERATIONS_RUNBOOK section 5; COPY_DECK section 11.11; SCHEMA
 * section 5.12; ROUTE_CONTRACTS RP-611; TASK-039 scope items 1 and 2): the five items read in one request,
 * each with its state judged here against its threshold, by the application's clock (STK-31).
 *
 * - "Cadangan data terakhir": the newest `backup_runs` row. FAILED when it failed; OK when it succeeded at
 *   most 7 hours ago and the newest weekly re-verification of the last 8 days passed; otherwise UNVERIFIED —
 *   no run at all, a success older than 7 hours (with its age), or a re-verification that failed or did not
 *   run in 8 days. A re-verification is a Sunday run's `reverified_backup_id`; its failure is a Sunday run that
 *   failed REMOTE_MISMATCH, the code BackupRunner gives only to a weekly copy gone or changed in the store.
 * - "Uji pemulihan terakhir": the newest `restore_drills` row: FAIL is FAILED; PASS at most 35 days ago OK,
 *   older UNVERIFIED with its age; none UNVERIFIED.
 * - "Ruang penyimpanan": the free and total space of the file system of the file volume, the disk `media`,
 *   measured now: under 20 % free FAILED, otherwise OK; a measurement that could not be made UNVERIFIED.
 * - "Rekonsiliasi harian": the daily run is the newest run of scope ALL; a run that failed or found a
 *   mismatch from that one on, of either scope, is FAILED; without a run of scope ALL UNVERIFIED; otherwise
 *   OK when that run is at most 26 hours old, UNVERIFIED with its age when older. A run for one product
 *   (RB-04) never stands for the daily run, so it never makes the item pass. The item is read in one
 *   statement, one snapshot, so that a run recorded during the read never passes as clean.
 * - "Penyimpanan cadangan": `store_bytes` of the newest successful run against the store's free allowance
 *   of 10 GB: 80 % or more FAILED, below OK; no successful run UNVERIFIED.
 *
 * Evidence dated more than five minutes after the clock proves nothing and is UNVERIFIED. Whether a
 * reconciliation incident is open goes with the read, for UXI-375. It reads only: no lock, no write.
 */
final class SystemStatus
{
    public const string OK = 'OK';

    public const string FAILED = 'FAILED';

    public const string UNVERIFIED = 'UNVERIFIED';

    /** The items, in the order of COPY_DECK section 11.11. */
    public const array ITEMS = ['backup', 'restore', 'disk', 'reconciliation', 'backupStore'];

    public const int BACKUP_SECONDS = 7 * 3600;

    public const int REVERIFICATION_SECONDS = 8 * 86400;

    public const int DRILL_SECONDS = 35 * 86400;

    public const int RECONCILIATION_SECONDS = 26 * 3600;

    public const int DISK_FREE_PERCENT = 20;

    /** The backup store's free allowance (ADR-010 items 4 and 11; BACKUP_RECOVERY section 6). */
    public const int STORE_ALLOWANCE_BYTES = 10_000_000_000;

    public const int STORE_FAILED_PERCENT = 80;

    /** How far a record may lie ahead of the clock, the drift of two synchronized clocks. */
    private const int SKEW_SECONDS = 300;

    public function __construct(
        private readonly Clock $clock,
        private readonly VolumeSpace $volume,
        private readonly Incidents $incidents,
    ) {}

    /**
     * @return array{backup: array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}, restore: array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}, disk: array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}, reconciliation: array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}, backupStore: array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}, incidentsOpen: bool}
     */
    public function read(): array
    {
        $now = $this->clock->now()->getTimestamp();

        return [
            'backup' => $this->backup($now),
            'restore' => $this->restore($now),
            'disk' => $this->disk(),
            'reconciliation' => $this->reconciliation($now),
            'backupStore' => $this->backupStore(),
            'incidentsOpen' => $this->incidents->openCount() > 0,
        ];
    }

    /**
     * The attention signal "Status sistem perlu dicek" of Beranda (UXI-064; TASK-041): an item failed or
     * UNVERIFIED. A read that fails throws, so the caller shows its own failed read (UXS-22), never a pass.
     */
    public function attention(): bool
    {
        $status = $this->read();

        foreach (self::ITEMS as $item) {
            if ($status[$item]['state'] !== self::OK) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}
     */
    private function backup(int $now): array
    {
        $newest = self::row(DB::selectOne(<<<'SQL'
            select outcome,
                   to_char(finished_at at time zone 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') as finished,
                   floor(extract(epoch from finished_at))::bigint as epoch
            from backup_runs
            order by finished_at desc, id desc
            limit 1
            SQL));

        if ($newest === null) {
            return self::item(self::UNVERIFIED);
        }

        $at = self::text($newest, 'finished');

        if (self::text($newest, 'outcome') === 'FAILED') {
            return self::item(self::FAILED, $at);
        }

        $age = $now - self::int($newest, 'epoch');

        if (! self::fresh($age, self::BACKUP_SECONDS)) {
            return self::item(self::UNVERIFIED, $at, $age);
        }

        // The newest re-verification of the last 8 days, passed or failed (BACKUP_RECOVERY section 4 step 7).
        $reverification = self::row(DB::selectOne(<<<'SQL'
            select reverified_backup_id is not null as passed
            from backup_runs
            where finished_at > to_timestamp(?)
              and (reverified_backup_id is not null or (failure_code = 'REMOTE_MISMATCH' and tiers like '%weekly%'))
            order by finished_at desc, id desc
            limit 1
            SQL, [$now - self::REVERIFICATION_SECONDS]));

        if ($reverification === null || ($reverification['passed'] ?? null) !== true) {
            return self::item(self::UNVERIFIED);
        }

        return self::item(self::OK, $at, $age);
    }

    /**
     * @return array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}
     */
    private function restore(int $now): array
    {
        $newest = self::row(DB::selectOne(<<<'SQL'
            select result,
                   to_char(finished_at at time zone 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') as finished,
                   floor(extract(epoch from finished_at))::bigint as epoch
            from restore_drills
            order by finished_at desc, id desc
            limit 1
            SQL));

        if ($newest === null) {
            return self::item(self::UNVERIFIED);
        }

        $at = self::text($newest, 'finished');

        if (self::text($newest, 'result') !== 'PASS') {
            return self::item(self::FAILED, $at);
        }

        $age = $now - self::int($newest, 'epoch');

        return self::item(self::fresh($age, self::DRILL_SECONDS) ? self::OK : self::UNVERIFIED, $at, $age);
    }

    /**
     * @return array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}
     */
    private function disk(): array
    {
        $root = config('filesystems.disks.media.root');
        $measured = is_string($root) && $root !== '' ? $this->volume->measure($root) : null;

        if ($measured === null) {
            return self::item(self::UNVERIFIED);
        }

        $failed = $measured['free'] * 100 < $measured['total'] * self::DISK_FREE_PERCENT;

        return self::item($failed ? self::FAILED : self::OK, bytes: $measured['free'], total: $measured['total']);
    }

    /**
     * @return array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}
     */
    private function reconciliation(int $now): array
    {
        // One statement, so one snapshot: the daily run and the failures from it on are read together, and a
        // run recorded during the read can never lend its time to a pass that its outcome would deny.
        $read = self::row(DB::selectOne(<<<'SQL'
            with daily as (
                select finished_at
                from reconciliation_runs
                where scope = 'ALL'
                order by finished_at desc, id desc
                limit 1
            )
            select (select to_char(finished_at at time zone 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') from daily) as finished,
                   (select floor(extract(epoch from finished_at))::bigint from daily) as epoch,
                   (select to_char(r.finished_at at time zone 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"')
                    from reconciliation_runs r
                    where r.outcome <> 'CLEAN'
                      and r.finished_at >= coalesce((select finished_at from daily), '-infinity'::timestamptz)
                    order by r.finished_at desc, r.id desc
                    limit 1) as failed
            SQL)) ?? throw new LogicException('A select without a table returns one row.');

        if (($read['failed'] ?? null) !== null) {
            return self::item(self::FAILED, self::text($read, 'failed'));
        }

        if (($read['finished'] ?? null) === null) {
            return self::item(self::UNVERIFIED);
        }

        $age = $now - self::int($read, 'epoch');

        return self::item(self::fresh($age, self::RECONCILIATION_SECONDS) ? self::OK : self::UNVERIFIED, self::text($read, 'finished'), $age);
    }

    /**
     * @return array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}
     */
    private function backupStore(): array
    {
        $newest = self::row(DB::selectOne("select store_bytes from backup_runs where outcome = 'SUCCEEDED' order by finished_at desc, id desc limit 1"));

        if ($newest === null) {
            return self::item(self::UNVERIFIED);
        }

        $used = self::int($newest, 'store_bytes');
        $failed = $used * 100 >= self::STORE_ALLOWANCE_BYTES * self::STORE_FAILED_PERCENT;

        return self::item($failed ? self::FAILED : self::OK, bytes: $used, total: self::STORE_ALLOWANCE_BYTES);
    }

    /**
     * Evidence within its limit and not dated ahead of the clock beyond the drift.
     */
    private static function fresh(int $age, int $limit): bool
    {
        return $age >= -self::SKEW_SECONDS && $age <= $limit;
    }

    /**
     * @return array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}
     */
    private static function item(string $state, ?string $at = null, ?int $age = null, ?int $bytes = null, ?int $total = null): array
    {
        return [
            'state' => $state,
            'at' => $at,
            'ageMinutes' => $age === null ? null : intdiv(max(0, $age), 60),
            'bytes' => $bytes,
            'totalBytes' => $total,
        ];
    }

    /**
     * A row of a read, or null when there is none.
     *
     * @return array<array-key, mixed>|null
     */
    private static function row(mixed $row): ?array
    {
        return $row === null ? null : (array) $row;
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    private static function text(array $row, string $name): string
    {
        $value = $row[$name] ?? null;

        return is_string($value) ? $value : throw new LogicException(sprintf('The column %s holds a text (SCHEMA section 5.12).', $name));
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    private static function int(array $row, string $name): int
    {
        $value = $row[$name] ?? null;

        return is_int($value) ? $value : throw new LogicException(sprintf('The column %s holds a whole number (SCHEMA section 5.12).', $name));
    }
}
