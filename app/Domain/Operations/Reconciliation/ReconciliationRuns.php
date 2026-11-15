<?php

namespace App\Domain\Operations\Reconciliation;

use Illuminate\Support\Facades\DB;

/**
 * The rows of `reconciliation_runs` (SCHEMA section 5.12; ROUTE_CONTRACTS SC-01; REQ-OPS-04), the
 * Operations module's own table and this its only writer (STK-12): one row per run, written once at
 * its end and never changed. It holds counts and codes only.
 */
final class ReconciliationRuns
{
    public const string CLEAN = 'CLEAN';

    public const string MISMATCH = 'MISMATCH';

    public const string FAILED = 'FAILED';

    public const string ALL = 'ALL';

    public const string PRODUCT = 'PRODUCT';

    public const string SCHEDULE = 'SCHEDULE';

    public const string OPERATOR = 'OPERATOR';

    /**
     * Writes the row of a run in a statement of its own and returns its key.
     */
    public function record(string $startedAt, string $finishedAt, string $outcome, int $productsChecked, int $blocksWritten, int $otherDifferences, string $scope, string $trigger): int
    {
        return DB::table('reconciliation_runs')->insertGetId([
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'outcome' => $outcome,
            'products_checked' => $productsChecked,
            'blocks_written' => $blocksWritten,
            'other_differences' => $otherDifferences,
            'scope' => $scope,
            'run_trigger' => $trigger,
        ]);
    }
}
