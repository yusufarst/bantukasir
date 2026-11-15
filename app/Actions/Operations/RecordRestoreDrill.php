<?php

namespace App\Actions\Operations;

use App\Database\CommandPoints;
use App\Domain\Operations\Restore\RestoreDrills;
use App\Domain\Operations\Restore\RestoreRefused;
use App\Support\Time\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * OP-08, the record of a restore drill (SECURITY_MODEL SEC-OP-08 and section 14; ROUTE_CONTRACTS OP-08;
 * BACKUP_RECOVERY RB-02 step 10; SCHEMA section 5.12; TASK-037 scope item 4). An operator procedure that
 * changes no business data: one row of `restore_drills` with the kind, the backup restored, the SHA-256
 * of its manifest, the drill's times, its result, the measured restore time and the operator's note, the
 * procedure its actor; the run in the security log, by the procedure's framework.
 *
 * The drill names a backup of this environment whose manifest was stored, as backup_runs records it
 * (RB-02 step 1); a drill that passed verified that manifest, so its SHA-256 is the one recorded at the
 * upload (RB-02 step 3), while a drill that failed is recorded with what the operator measured. Its times
 * are in the past, the restore time within the drill's span.
 *
 * Write step (TEST_STRATEGY TS-21): 1 the row.
 */
final class RecordRestoreDrill
{
    public function __construct(
        private readonly RestoreDrills $drills,
        private readonly CommandPoints $points,
        private readonly Clock $clock,
    ) {}

    /**
     * @param  array{kind: string, backupId: string, manifestSha256: string, startedAt: CarbonImmutable, finishedAt: CarbonImmutable, result: string, restoreSeconds: int}  $drill
     *
     * @throws RestoreRefused
     */
    public function __invoke(array $drill, string $operatorNote): int
    {
        $now = $this->clock->now();

        if ($drill['finishedAt']->lessThan($drill['startedAt'])) {
            throw new RestoreRefused('The drill finished before it started: nothing was recorded.');
        }

        if ($drill['finishedAt']->greaterThan($now)) {
            throw new RestoreRefused('The drill finishes in the future: nothing was recorded.');
        }

        if ($drill['restoreSeconds'] > $drill['startedAt']->diffInSeconds($drill['finishedAt'], true)) {
            throw new RestoreRefused('The restore time is longer than the drill: nothing was recorded.');
        }

        return DB::transaction(function () use ($drill, $operatorNote, $now): int {
            $stored = $this->drills->storedManifestOf($drill['backupId']) ?? throw new RestoreRefused('No backup of this environment with a stored manifest has this identifier (backup_runs): nothing was recorded.');

            if ($drill['result'] === 'PASS' && ! hash_equals($stored, $drill['manifestSha256'])) {
                throw new RestoreRefused('A drill that passed verified its manifest, whose SHA-256 differs from the one recorded at its upload: nothing was recorded.');
            }

            $id = $this->drills->record([
                'kind' => $drill['kind'],
                'backupId' => $drill['backupId'],
                'manifestSha256' => $drill['manifestSha256'],
                'startedAt' => $drill['startedAt']->utc()->format('Y-m-d H:i:s.uP'),
                'finishedAt' => $drill['finishedAt']->utc()->format('Y-m-d H:i:s.uP'),
                'result' => $drill['result'],
                'restoreSeconds' => $drill['restoreSeconds'],
            ], $operatorNote, $now->format('Y-m-d H:i:s.uP'));
            $this->points->afterWrite(1);
            $this->points->beforeCommit();

            return $id;
        });
    }
}
