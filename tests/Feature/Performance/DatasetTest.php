<?php

namespace Tests\Feature\Performance;

use App\Database\DatabaseRefused;
use App\Domain\Operations\Reconciliation\ReconciliationRuns;
use LogicException;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\TestDatabase;
use Tests\Performance\Budgets;
use Tests\Performance\Dataset\Datasets;
use Tests\Performance\Dataset\Fingerprint;
use Tests\Performance\Plan;
use Tests\Performance\Recorder;

/**
 * The datasets of the performance harness (TASK-043 section 7, "Dataset generation with a reconciliation
 * after it"; AC-043-01 to AC-043-03; TECHNICAL_RULES section 13.2; OPEN_ITEMS T-09), at a small scale of
 * the same shape, on the test database: built through the Actions, reproducible from the seed, clean
 * by the reconciliation of SC-01; refused on any environment but test or local and on an installation
 * that already holds products; and the plan of a run and its record, a dry run that leaves PERF01 NOT_RUN.
 */
final class DatasetTest extends DatabaseTestCase
{
    /** One ten-thousandth of the datasets: the smallest of each count. */
    private const int SCALE = 100;

    private const string UNTIL = '2026-10-20';

    public function test_the_base_dataset_is_built_through_the_actions_reproducibly_and_reconciles_cleanly(): void
    {
        $summary = Datasets::build(Datasets::BASE, Datasets::SEED, self::SCALE, self::UNTIL);

        $this->assertSame(ReconciliationRuns::CLEAN, $summary['reconciliation']);
        $this->assertSame([
            'categories' => 2,
            'activeProducts' => 12,
            'inactiveProducts' => 1,
            // One token on every active product, a second on every other one; one more added and retired.
            'activeTokens' => 18,
            'retiredTokens' => 1,
            // The 12 opening lines, one Sale of 9 lines and four receivings of 10.
            'movements' => 61,
            // Its opening receipt, the Sale and the four receivings.
            'heavyProductMovements' => 6,
            'registers' => 3,
            'cashiers' => 2,
            // The two cashiers' sessions of the one day, closed at its end.
            'sessions' => 2,
            'sales' => 1,
            'saleLines' => 9,
            // The opening document and the four receivings.
            'receivings' => 5,
        ], array_diff_key($summary['counts'], ['auditEvents' => 0]));
        $this->assertSame(0, $this->observer()->table('cash_sessions')->where('status', 'OPEN')->count());

        // Every record is a command of its Action, which writes its audit event.
        $this->assertGreaterThanOrEqual(61, $summary['counts']['auditEvents']);

        // The history lies on its days in Asia/Jakarta: the Sale on the last day.
        $this->assertSame([self::UNTIL], $this->observer()->table('sales')->pluck('business_date')->map(static fn (mixed $date): string => is_string($date) ? substr($date, 0, 10) : '')->all());

        $first = Fingerprint::of();

        TestDatabase::reset();
        Datasets::build(Datasets::BASE, Datasets::SEED, self::SCALE, self::UNTIL);
        $this->assertSame($first, Fingerprint::of(), 'The same seed builds the same dataset.');

        TestDatabase::reset();
        Datasets::build(Datasets::BASE, Datasets::SEED + 1, self::SCALE, self::UNTIL);
        $this->assertNotSame($first, Fingerprint::of(), 'Another seed builds another dataset.');
    }

    public function test_the_growth_dataset_is_built_through_the_actions_and_reconciles_cleanly(): void
    {
        $summary = Datasets::build(Datasets::GROWTH, Datasets::SEED, self::SCALE, self::UNTIL);
        $counts = $summary['counts'];

        $this->assertSame(ReconciliationRuns::CLEAN, $summary['reconciliation']);
        $this->assertSame(60, $counts['sales']);
        $this->assertGreaterThanOrEqual(60, $counts['saleLines']);
        $this->assertLessThanOrEqual(240, $counts['saleLines']);
        // Each Sale printed four times: about five audit events a Sale (section 13.2).
        $this->assertSame(240, $this->observer()->table('audit_events')->where('action', 'PRINT')->count());
        $this->assertGreaterThanOrEqual(300, $counts['auditEvents']);
        $this->assertSame(0, $this->observer()->table('cash_sessions')->where('status', 'OPEN')->count());
    }

    public function test_a_dataset_is_refused_outside_test_and_local_and_on_an_installation_with_products(): void
    {
        config(['app.environment_name' => 'staging']);

        try {
            Datasets::build(Datasets::BASE, Datasets::SEED, self::SCALE, self::UNTIL);
            $this->fail('A dataset was built where the environment is staging.');
        } catch (DatabaseRefused) {
            $this->assertSame(0, $this->observer()->table('products')->count());
            $this->assertSame(0, $this->observer()->table('users')->count());
        }

        config(['app.environment_name' => 'test']);
        TokoContoh::product('ADA-01');

        $this->expectException(LogicException::class);
        Datasets::build(Datasets::BASE, Datasets::SEED, self::SCALE, self::UNTIL);
    }

    public function test_the_plan_of_a_run_and_its_record_are_a_dry_run(): void
    {
        $summary = Datasets::build(Datasets::BASE, Datasets::SEED, self::SCALE, self::UNTIL);
        $plan = Plan::make(1, 1, 60, $summary);

        $this->assertSame(ReconciliationRuns::CLEAN, $plan['reconciliationBefore']);
        $this->assertIsArray($plan['people']);
        $this->assertSame(['owner', 'cashierA', 'cashierB'], array_keys($plan['people']));
        $this->assertIsArray($plan['till']);
        $this->assertCount(11, $plan['till'], 'Every active product but the one of many movements.');
        $this->assertNotContains($summary['heavyProduct'], array_column($plan['till'], 'id'));
        $this->assertIsArray($plan['tokens']);
        $this->assertNotEmpty($plan['tokens']);
        $this->assertIsArray($plan['refunds']);
        $this->assertCount(1, $plan['refunds']);
        $this->assertIsArray($plan['replay']);
        $this->assertSame(0, $plan['replay']['correctionNo']);

        $summaryOfTool = ['metrics' => [
            'op_barcode_lookup' => ['type' => 'trend', 'values' => ['p(95)' => 41.7, 'max' => 60.1, 'count' => 30]],
            'fail_barcode_lookup' => ['type' => 'counter', 'values' => ['count' => 2, 'rate' => 0.1]],
            'op_csv_import' => ['type' => 'trend', 'values' => ['p(95)' => 9000.4, 'max' => 9100.6, 'count' => 1]],
        ]];
        $record = Recorder::record($plan, $summaryOfTool, ReconciliationRuns::CLEAN);

        $this->assertSame('NOT_RUN', $record['perf01']);
        $this->assertSame(Recorder::LABEL, $record['label']);
        $this->assertIsArray($record['results']);
        $results = $record['results'];
        $this->assertSame(array_keys(Budgets::METRICS), array_keys($results));
        $this->assertSame(['operation' => 'Exact barcode lookup', 'statistic' => 'p(95)', 'budgetMs' => 500, 'observedMs' => 42, 'samples' => 30, 'failures' => 2], $results['op_barcode_lookup']);
        $import = $results['op_csv_import'];
        $replay = $results['op_cost_replay'];
        $this->assertIsArray($import);
        $this->assertIsArray($replay);
        // The 30-second budgets name the longest time; a metric that the run did not measure has no value.
        $this->assertSame(9101, $import['observedMs']);
        $this->assertSame([null, 0], [$replay['observedMs'], $replay['samples']]);

        // A dry run of every scenario leaves no metric without a successful sample.
        $this->assertSame(array_values(array_diff(array_keys(Budgets::METRICS), ['op_barcode_lookup', 'op_csv_import'])), Recorder::unmeasured($record));
        $this->assertSame(['before' => ReconciliationRuns::CLEAN, 'after' => ReconciliationRuns::CLEAN], $record['reconciliation']);
        $this->assertIsArray($record['indexes']);
        $this->assertGreaterThan(0, $record['indexes']['count']);
        $this->assertIsArray($record['host']);
        $this->assertArrayNotHasKey('name', $record['host']);
        $this->assertStringNotContainsString(php_uname('n'), json_encode($record['host'], JSON_THROW_ON_ERROR));

        // The record holds no password of the plan.
        $owner = $plan['people']['owner'] ?? null;
        $this->assertIsArray($owner);
        $password = $owner['password'] ?? null;
        $this->assertIsString($password);
        $this->assertStringNotContainsString($password, json_encode($record, JSON_THROW_ON_ERROR));

        $table = Recorder::markdown($record);
        $this->assertStringContainsString(Recorder::LABEL, $table);
        $this->assertSame(12 + 2, substr_count($table, "\n| "));
        $this->assertStringContainsString('| Exact barcode lookup | 500 ms | p(95) | 42 | 30 | 2 |', $table);
    }
}
