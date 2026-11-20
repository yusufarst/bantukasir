<?php

namespace Tests\Feature\Operations\Status;

use App\Domain\Operations\Reconciliation\ReconciliationRuns;
use App\Domain\Operations\Restore\RestoreDrills;
use App\Domain\Operations\VolumeSpace;
use App\Queries\Operations\SystemStatus;
use App\Support\Time\Clock;
use App\Support\Time\TestClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The system status query (AC-039-01, AC-039-04; OPERATIONS_RUNBOOK section 5; SCHEMA section 5.12; REQ-OPS-04;
 * TASK-039 section 7, L2 thresholds with the test clock): each item against its threshold with the clock
 * standing still, at the threshold and one second past it; every case of "Belum Terverifikasi"; the newest
 * row of each table as SCHEMA section 5.12 says, `backup_runs` written as the backup role writes it,
 * `restore_drills` and `reconciliation_runs` through their own services; the open incidents; and the
 * attention signal of Beranda.
 */
final class SystemStatusTest extends DatabaseTestCase
{
    private const string NOW = '2026-11-22T05:00:00Z';

    private const int HOUR = 3600;

    private const int DAY = 86400;

    private TestClock $clock;

    /** @var array{free: int, total: int}|null */
    private ?array $volume = ['free' => 30_000_000_000, 'total' => 100_000_000_000];

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new TestClock(self::NOW);
        $this->app->instance(Clock::class, $this->clock);
        $this->app->instance(VolumeSpace::class, new class($this) implements VolumeSpace
        {
            public function __construct(private readonly SystemStatusTest $test) {}

            public function measure(string $path): ?array
            {
                return $this->test->volumeAt($path);
            }
        });
    }

    /**
     * The measure of the fake volume; the path is the disk `media`'s.
     *
     * @return array{free: int, total: int}|null
     */
    public function volumeAt(string $path): ?array
    {
        $this->assertSame(config('filesystems.disks.media.root'), $path);

        return $this->volume;
    }

    public function test_without_any_evidence_every_item_is_unverified_and_the_signal_is_raised(): void
    {
        $this->volume = null;
        $status = $this->status();

        foreach (SystemStatus::ITEMS as $item) {
            $this->assertSame(['state' => SystemStatus::UNVERIFIED, 'at' => null, 'ageMinutes' => null, 'bytes' => null, 'totalBytes' => null], $status[$item], $item);
        }

        $this->assertFalse($status['incidentsOpen']);
        $this->assertTrue(app(SystemStatus::class)->attention());
    }

    public function test_every_item_proven_passes_and_the_signal_is_quiet(): void
    {
        $this->provenBackup(self::HOUR);
        $this->drill('PASS', self::DAY);
        $this->reconciliation('CLEAN', self::HOUR);
        $status = $this->status();

        $this->assertSame(['state' => SystemStatus::OK, 'at' => '2026-11-22T04:00:00.000000Z', 'ageMinutes' => 60, 'bytes' => null, 'totalBytes' => null], $status['backup']);
        $this->assertSame(['state' => SystemStatus::OK, 'at' => '2026-11-21T05:00:00.000000Z', 'ageMinutes' => 1440, 'bytes' => null, 'totalBytes' => null], $status['restore']);
        $this->assertSame(['state' => SystemStatus::OK, 'at' => null, 'ageMinutes' => null, 'bytes' => 30_000_000_000, 'totalBytes' => 100_000_000_000], $status['disk']);
        $this->assertSame(['state' => SystemStatus::OK, 'at' => '2026-11-22T04:00:00.000000Z', 'ageMinutes' => 60, 'bytes' => null, 'totalBytes' => null], $status['reconciliation']);
        $this->assertSame(['state' => SystemStatus::OK, 'at' => null, 'ageMinutes' => null, 'bytes' => 1_000_000_000, 'totalBytes' => SystemStatus::STORE_ALLOWANCE_BYTES], $status['backupStore']);
        $this->assertFalse(app(SystemStatus::class)->attention());
    }

    public function test_the_backup_passes_up_to_7_hours_and_then_is_unverified_with_its_age(): void
    {
        $this->reverified(2 * self::DAY);
        $this->backupRun(7 * self::HOUR);
        $this->assertSame([SystemStatus::OK, 420], $this->stateAndAge('backup'));

        $this->clock->advanceSeconds(1);
        $this->assertSame([SystemStatus::UNVERIFIED, 420], $this->stateAndAge('backup'));
        $this->assertSame('2026-11-21T22:00:00.000000Z', $this->status()['backup']['at']);
    }

    public function test_the_newest_backup_failed_is_failed_whatever_succeeded_before(): void
    {
        $this->provenBackup(2 * self::HOUR);
        $this->backupRun(self::HOUR, 'FAILED');

        $this->assertSame(['state' => SystemStatus::FAILED, 'at' => '2026-11-22T04:00:00.000000Z', 'ageMinutes' => null, 'bytes' => null, 'totalBytes' => null], $this->status()['backup']);
        // The store's usage is still that of the newest successful run.
        $this->assertSame(SystemStatus::OK, $this->status()['backupStore']['state']);
    }

    public function test_the_backup_needs_a_weekly_re_verification_that_passed_within_8_days(): void
    {
        // A recent success alone proves nothing without the weekly re-verification.
        $this->backupRun(self::HOUR);
        $this->assertSame(['state' => SystemStatus::UNVERIFIED, 'at' => null, 'ageMinutes' => null, 'bytes' => null, 'totalBytes' => null], $this->status()['backup']);

        // One second within 8 days, then exactly 8 days: it has not run for 8 days.
        $this->reverified(8 * self::DAY - 1);
        $this->assertSame(SystemStatus::OK, $this->stateAndAge('backup')[0]);
        $this->clock->advanceSeconds(1);
        $this->assertSame(SystemStatus::UNVERIFIED, $this->stateAndAge('backup')[0]);

        // A newer re-verification that failed, followed by a success, is not proven.
        $this->reverified(self::DAY);
        $this->backupRun(2 * self::HOUR, 'FAILED', ['tiers' => '6h,weekly', 'failure_code' => 'REMOTE_MISMATCH']);
        $this->backupRun(self::HOUR);
        $this->assertSame(SystemStatus::UNVERIFIED, $this->stateAndAge('backup')[0]);

        // A REMOTE_MISMATCH of a run without the weekly tier is not a re-verification.
        $this->reverified(30 * 60);
        $this->backupRun(20 * 60, 'FAILED', ['failure_code' => 'REMOTE_MISMATCH']);
        $this->backupRun(10 * 60);
        $this->assertSame([SystemStatus::OK, 10], $this->stateAndAge('backup'));
    }

    public function test_the_restore_drill_passes_up_to_35_days_fails_on_fail_and_is_unverified_without_one(): void
    {
        $this->assertSame(SystemStatus::UNVERIFIED, $this->stateAndAge('restore')[0]);

        $this->drill('PASS', 35 * self::DAY);
        $this->assertSame([SystemStatus::OK, 35 * 1440], $this->stateAndAge('restore'));
        $this->clock->advanceSeconds(1);
        $this->assertSame([SystemStatus::UNVERIFIED, 35 * 1440], $this->stateAndAge('restore'));

        $this->drill('FAIL', self::DAY);
        $this->assertSame(['state' => SystemStatus::FAILED, 'at' => '2026-11-21T05:00:01.000000Z', 'ageMinutes' => null, 'bytes' => null, 'totalBytes' => null], $this->status()['restore']);

        $this->drill('PASS', self::HOUR);
        $this->assertSame([SystemStatus::OK, 60], $this->stateAndAge('restore'));
    }

    public function test_the_disk_needs_20_percent_free_and_a_measurement(): void
    {
        $this->volume = ['free' => 20_000_000_000, 'total' => 100_000_000_000];
        $this->assertSame(SystemStatus::OK, $this->status()['disk']['state']);

        $this->volume = ['free' => 19_999_999_999, 'total' => 100_000_000_000];
        $this->assertSame(['state' => SystemStatus::FAILED, 'at' => null, 'ageMinutes' => null, 'bytes' => 19_999_999_999, 'totalBytes' => 100_000_000_000], $this->status()['disk']);

        $this->volume = null;
        $this->assertSame(SystemStatus::UNVERIFIED, $this->status()['disk']['state']);
        $this->assertNull($this->status()['disk']['bytes']);
    }

    public function test_the_daily_reconciliation_passes_up_to_26_hours_and_a_run_for_one_product_never_stands_for_it(): void
    {
        $this->assertSame(SystemStatus::UNVERIFIED, $this->stateAndAge('reconciliation')[0]);

        // Only a run for one product: not the daily run.
        $this->reconciliation('CLEAN', 30 * self::HOUR, ReconciliationRuns::PRODUCT);
        $this->assertSame(SystemStatus::UNVERIFIED, $this->stateAndAge('reconciliation')[0]);

        $this->reconciliation('CLEAN', 26 * self::HOUR);
        $this->assertSame([SystemStatus::OK, 26 * 60], $this->stateAndAge('reconciliation'));
        $this->clock->advanceSeconds(1);
        $this->assertSame([SystemStatus::UNVERIFIED, 26 * 60], $this->stateAndAge('reconciliation'));

        // A newer clean run for one product does not make the stale daily run pass.
        $this->reconciliation('CLEAN', self::HOUR, ReconciliationRuns::PRODUCT);
        $this->assertSame(SystemStatus::UNVERIFIED, $this->stateAndAge('reconciliation')[0]);
    }

    public function test_a_failed_or_mismatching_run_since_the_daily_run_is_failed(): void
    {
        $this->reconciliation('MISMATCH', 3 * self::HOUR);
        $this->assertSame(['state' => SystemStatus::FAILED, 'at' => '2026-11-22T02:00:00.000000Z', 'ageMinutes' => null, 'bytes' => null, 'totalBytes' => null], $this->status()['reconciliation']);

        // A newer clean run for one product does not clear the daily run's mismatch.
        $this->reconciliation('CLEAN', 2 * self::HOUR, ReconciliationRuns::PRODUCT);
        $this->assertSame(SystemStatus::FAILED, $this->stateAndAge('reconciliation')[0]);

        // A newer clean daily run does.
        $this->reconciliation('CLEAN', self::HOUR);
        $this->assertSame(SystemStatus::OK, $this->stateAndAge('reconciliation')[0]);

        // A run for one product that fails after it, or a daily run that fails.
        $this->reconciliation('FAILED', 30 * 60, ReconciliationRuns::PRODUCT);
        $this->assertSame(SystemStatus::FAILED, $this->stateAndAge('reconciliation')[0]);
        $this->reconciliation('CLEAN', 20 * 60);
        $this->assertSame(SystemStatus::OK, $this->stateAndAge('reconciliation')[0]);
        $this->reconciliation('FAILED', 10 * 60);
        $this->assertSame(SystemStatus::FAILED, $this->stateAndAge('reconciliation')[0]);
    }

    public function test_the_backup_store_fails_at_80_percent_of_the_allowance(): void
    {
        $this->assertSame(SystemStatus::UNVERIFIED, $this->status()['backupStore']['state']);

        // A failed run records no usage.
        $this->backupRun(3 * self::HOUR, 'FAILED');
        $this->assertSame(SystemStatus::UNVERIFIED, $this->status()['backupStore']['state']);

        $this->backupRun(2 * self::HOUR, 'SUCCEEDED', ['store_bytes' => 7_999_999_999]);
        $this->assertSame(['state' => SystemStatus::OK, 'at' => null, 'ageMinutes' => null, 'bytes' => 7_999_999_999, 'totalBytes' => 10_000_000_000], $this->status()['backupStore']);

        $this->backupRun(self::HOUR, 'SUCCEEDED', ['store_bytes' => 8_000_000_000]);
        $this->assertSame(SystemStatus::FAILED, $this->status()['backupStore']['state']);
    }

    public function test_evidence_dated_ahead_of_the_clock_beyond_the_drift_proves_nothing(): void
    {
        $this->reverified(self::HOUR);
        $this->backupRun(-300);
        $this->drill('PASS', -300);
        $this->reconciliation('CLEAN', -300);
        $this->assertSame([SystemStatus::OK, SystemStatus::OK, SystemStatus::OK], [$this->stateAndAge('backup')[0], $this->stateAndAge('restore')[0], $this->stateAndAge('reconciliation')[0]]);

        $this->backupRun(-301);
        $this->drill('PASS', -301);
        $this->reconciliation('CLEAN', -301);
        $this->assertSame([SystemStatus::UNVERIFIED, SystemStatus::UNVERIFIED, SystemStatus::UNVERIFIED], [$this->stateAndAge('backup')[0], $this->stateAndAge('restore')[0], $this->stateAndAge('reconciliation')[0]]);
    }

    public function test_an_open_incident_goes_with_the_read_until_it_is_reopened(): void
    {
        TokoContoh::shop();
        $product = TokoContoh::product('PEN-01', ['categoryId' => TokoContoh::category(), 'name' => 'Pulpen Hitam']);
        TokoContoh::stock($product, '5', '5000');

        $block = TokoContoh::postingBlock($product);
        $this->assertTrue($this->status()['incidentsOpen']);

        TokoContoh::reopenPostings($block);
        $this->assertFalse($this->status()['incidentsOpen']);
    }

    public function test_the_read_writes_nothing(): void
    {
        $this->provenBackup(self::HOUR);
        $before = [DB::table('backup_runs')->count(), DB::table('restore_drills')->count(), DB::table('reconciliation_runs')->count()];

        $this->status();
        app(SystemStatus::class)->attention();

        $this->assertSame($before, [DB::table('backup_runs')->count(), DB::table('restore_drills')->count(), DB::table('reconciliation_runs')->count()]);
    }

    /**
     * @return array{backup: array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}, restore: array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}, disk: array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}, reconciliation: array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}, backupStore: array{state: string, at: string|null, ageMinutes: int|null, bytes: int|null, totalBytes: int|null}, incidentsOpen: bool}
     */
    private function status(): array
    {
        return app(SystemStatus::class)->read();
    }

    /**
     * @param  'backup'|'restore'|'reconciliation'  $item
     * @return array{string, int|null}
     */
    private function stateAndAge(string $item): array
    {
        $read = $this->status()[$item];

        return [$read['state'], $read['ageMinutes']];
    }

    /** The instant this many seconds before the clock, as the services take it. */
    private function ago(int $seconds): string
    {
        return $this->clock->now()->subSeconds($seconds)->format('Y-m-d H:i:s.uP');
    }

    /**
     * A row of backup_runs as the backup role writes it (BackupRunner, SCHEMA section 5.12), finished this
     * long before the clock.
     *
     * @param  array<string, mixed>  $changes
     */
    private function backupRun(int $finishedAgo, string $outcome = 'SUCCEEDED', array $changes = []): void
    {
        $at = $this->ago($finishedAgo);
        $backup = $this->backup();
        $succeeded = $outcome === 'SUCCEEDED';

        $backup->table('backup_runs')->insert([
            'backup_id' => '20261122T000000Z-'.Str::random(8),
            'reason' => 'SCHEDULED',
            'started_at' => $at,
            'finished_at' => $at,
            'outcome' => $outcome,
            'failure_code' => $succeeded ? null : 'TIMEOUT',
            'snapshot_at' => $at,
            'tiers' => '6h',
            'dump_bytes' => $succeeded ? 1_000_000 : null,
            'files_uploaded' => $succeeded ? 0 : null,
            'uploaded_bytes' => $succeeded ? 1_000_000 : null,
            'store_bytes' => $succeeded ? 1_000_000_000 : null,
            'dump_object_sha256' => $succeeded ? $backup->raw("decode(repeat('00', 32), 'hex')") : null,
            'manifest_object_sha256' => $succeeded ? $backup->raw("decode(repeat('00', 32), 'hex')") : null,
            'reverified_backup_id' => null,
            ...$changes,
        ]);
    }

    /** A Sunday run that re-verified the previous weekly copy, finished this long before the clock. */
    private function reverified(int $finishedAgo): void
    {
        $this->backupRun($finishedAgo, 'SUCCEEDED', ['tiers' => '6h,weekly', 'reverified_backup_id' => '20261115T000000Z-'.Str::random(8)]);
    }

    /** A success this long ago with its re-verification the day before. */
    private function provenBackup(int $finishedAgo): void
    {
        $this->reverified(self::DAY);
        $this->backupRun($finishedAgo);
    }

    /** A drill recorded through the module's own service, as OP-08 records it. */
    private function drill(string $result, int $finishedAgo): void
    {
        DB::transaction(fn (): int => app(RestoreDrills::class)->record([
            'kind' => 'ROUTINE',
            'backupId' => '20261122T000000Z-'.Str::random(8),
            'manifestSha256' => str_repeat('ab', 32),
            'startedAt' => $this->ago($finishedAgo + 600),
            'finishedAt' => $this->ago($finishedAgo),
            'result' => $result,
            'restoreSeconds' => 600,
        ], 'Uji pemulihan rutin', $this->ago($finishedAgo)));
    }

    /** A reconciliation run recorded through the module's own service, as SC-01 records it. */
    private function reconciliation(string $outcome, int $finishedAgo, string $scope = ReconciliationRuns::ALL): void
    {
        app(ReconciliationRuns::class)->record($this->ago($finishedAgo + 60), $this->ago($finishedAgo), $outcome, 10, 0, 0, $scope, ReconciliationRuns::SCHEDULE);
    }
}
