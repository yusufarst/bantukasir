<?php

namespace Tests\Feature\Sales;

use App\Database\CommandPoints;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;
use Tests\Harness\Race\InjectedFailure;

/**
 * The replay of TASK-025 over Sale issues (AC-028-10, AC-028-11; ADR-008 sections 2, 4.1, 4.3 and 7;
 * COSTING sections 6 to 8, BR-COST-15, BR-COST-20 to BR-COST-28; SCHEMA G-07, G-14 and G-21; D-060;
 * REQ-COST-06, REQ-COST-09; suite FIN03; TASK-028 scope item 7 and section 7, L1 and L2; RACE-11 is
 * CheckoutRaceTest's): a cost completed after a Sale publishes the Sale line's projection and never
 * its fact, the same correction again gives the same, and a failure during the publication changes
 * nothing; a Sale that empties the logical quantity ends its pool; the Sale issues count against the
 * movement cap; a reversal that would leave a later Sale without stock at its point is refused. No
 * replay touches a Sale, its lines, its Payment, its receipt snapshot or a session's figures.
 */
final class CheckoutReplayTest extends DatabaseTestCase
{
    private int $owner = 0;

    private int $category = 0;

    private int $session = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->owner = TokoContoh::person('Hendra')['id'];
        $this->category = TokoContoh::category();
        $this->session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
    }

    public function test_completing_a_receipts_cost_publishes_the_sale_issue_projection_and_never_its_fact_or_the_sale(): void
    {
        // AC-028-10; ADR-008 sections 4.1 and 4.3; REQ-COST-06, REQ-COST-09; G-14; D-060. A RECEIPT of
        // 10 at UNKNOWN cost and a Sale of 4, UNKNOWN at commit; completing the receipt at Rp50.000
        // publishes the Sale line's projection KNOWN Rp20.000 and the valuation 6 worth Rp30.000, the
        // allocation fact staying UNKNOWN; the same correction again gives the same and rewrites no
        // projection; the Sale and its session are unchanged.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '10000']);
        $receipt = TokoContoh::receive([['productId' => $book, 'quantity' => '10', 'cost' => ['status' => 'UNKNOWN']]]);
        TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '4']]);
        $this->assertSame([['SALE_ISSUE', 'UNKNOWN', null, 'UNKNOWN', null, 2]], $this->allocationsOf($book));
        $this->assertSame('UNKNOWN||2|2', $this->valuationOf($book));
        $kept = $this->saleRows();
        $evidence = TokoContoh::evidenceOf(TokoContoh::movementOf($receipt));

        TokoContoh::correctCost($evidence, '50000', 'Nota ditemukan');
        $this->assertSame([['SALE_ISSUE', 'UNKNOWN', null, 'KNOWN', '20000.000000', 3]], $this->allocationsOf($book));
        $this->assertSame('KNOWN|30000.000000|3|2', $this->valuationOf($book));
        $this->assertSame($kept, $this->saleRows());

        TokoContoh::correctCost($evidence, '50000', 'Nota yang sama');
        $this->assertSame([['SALE_ISSUE', 'UNKNOWN', null, 'KNOWN', '20000.000000', 3]], $this->allocationsOf($book));
        $this->assertSame('KNOWN|30000.000000|4|2', $this->valuationOf($book));
        $this->assertSame($kept, $this->saleRows());

        // A later Sale is valued from the completed pool: 2 of 6 worth Rp30.000.
        TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '2']]);
        $this->assertSame(['SALE_ISSUE', 'KNOWN', '10000.000000', 'KNOWN', '10000.000000', 5], $this->allocationsOf($book)[1] ?? null);
        $this->assertSame('KNOWN|20000.000000|5|3', $this->valuationOf($book));
    }

    public function test_a_failure_during_the_publication_changes_nothing(): void
    {
        // AC-028-10, TS-21: a failure after each of the correction's six write steps — the correction,
        // the valuation, the projections, the clearances, the audit event, the result — leaves the Sale
        // issue's projection, the valuation and every fact of the Sale as they were.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '10000']);
        $receipt = TokoContoh::receive([['productId' => $book, 'quantity' => '10', 'cost' => ['status' => 'UNKNOWN']]]);
        TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '4']]);
        $evidence = TokoContoh::evidenceOf(TokoContoh::movementOf($receipt));
        $before = [$this->saleRows(), $this->allocationsOf($book), $this->valuationOf($book), $this->watcher()->table('cost_corrections')->count()];

        for ($step = 1; $step <= 6; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));

            try {
                TokoContoh::correctCost($evidence, '50000', 'Nota ditemukan');
                $this->fail(sprintf('The correction failed after write step %d.', $step));
            } catch (InjectedFailure) {
                $this->addToAssertionCount(1);
            }

            $this->assertSame($before, [$this->saleRows(), $this->allocationsOf($book), $this->valuationOf($book), $this->watcher()->table('cost_corrections')->count()], (string) $step);
        }

        $this->app->instance(CommandPoints::class, new FailurePoints);
        TokoContoh::correctCost($evidence, '50000', 'Nota ditemukan');
        $this->assertSame([['SALE_ISSUE', 'UNKNOWN', null, 'KNOWN', '20000.000000', 3]], $this->allocationsOf($book));
    }

    public function test_a_sale_that_empties_the_logical_quantity_ends_its_pool_in_the_replay_too(): void
    {
        // AC-028-11; ADR-008 section 4.1; BR-COST-15: 4 at UNKNOWN cost, all 4 sold (UNKNOWN; the pool
        // starts again at a known zero), 10 at Rp50.000, 4 sold (Rp20.000). Completing the first receipt
        // at Rp8.000 completes the first Sale's projection only: the later Sale is of the new pool.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '10000']);
        $first = TokoContoh::receive([['productId' => $book, 'quantity' => '4', 'cost' => ['status' => 'UNKNOWN']]]);
        TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '4']]);
        $this->assertSame('KNOWN|0.000000|2|2', $this->valuationOf($book));
        TokoContoh::stock($book, '10', '50000');
        TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '4']]);
        $this->assertSame([
            ['SALE_ISSUE', 'UNKNOWN', null, 'UNKNOWN', null, 2],
            ['SALE_ISSUE', 'KNOWN', '20000.000000', 'KNOWN', '20000.000000', 4],
        ], $this->allocationsOf($book));
        $kept = $this->saleRows();

        TokoContoh::correctCost(TokoContoh::evidenceOf(TokoContoh::movementOf($first)), '8000', 'Nota ditemukan');
        $this->assertSame([
            ['SALE_ISSUE', 'UNKNOWN', null, 'KNOWN', '8000.000000', 5],
            ['SALE_ISSUE', 'KNOWN', '20000.000000', 'KNOWN', '20000.000000', 4],
        ], $this->allocationsOf($book));
        $this->assertSame('KNOWN|30000.000000|5|4', $this->valuationOf($book));
        $this->assertSame($kept, $this->saleRows());
    }

    public function test_the_sale_issues_count_against_the_movement_cap(): void
    {
        // AC-028-11; TR-TIME-01; BR-COST-27, BR-COST-28; SCHEMA section 5.8; G-21: the receipt and the two
        // Sales after it are three movements; a cap of two refuses REPLAY_REJECTED with the intent's mark
        // and nothing of the Sales changed; at three the replay runs: 2 and 3 of 10 worth Rp60.000.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '10000']);
        $receipt = TokoContoh::stock($book, '10', '50000');
        TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '2']]);
        TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '3']]);
        $evidence = TokoContoh::evidenceOf(TokoContoh::movementOf($receipt));
        $before = [$this->saleRows(), $this->allocationsOf($book), $this->valuationOf($book)];

        config(['costing.replay.movement_cap' => 2]);
        $this->assertSame(['refused' => 'REPLAY_REJECTED'], $this->correction($evidence, '60000'));
        $this->assertSame($before, [$this->saleRows(), $this->allocationsOf($book), $this->valuationOf($book)]);
        $this->assertSame([[$evidence, 'MOVEMENT_CAP']], $this->watcher()->table('cost_incomplete_marks')->get(['evidence_id', 'rejection_code'])->map(static fn (object $row): array => array_values((array) $row))->all());

        config(['costing.replay.movement_cap' => 3]);
        $this->assertSame('COMMITTED', $this->correction($evidence, '60000')['outcome'] ?? null);
        $this->assertSame([
            ['SALE_ISSUE', 'KNOWN', '10000.000000', 'KNOWN', '12000.000000', 4],
            ['SALE_ISSUE', 'KNOWN', '15000.000000', 'KNOWN', '18000.000000', 4],
        ], $this->allocationsOf($book));
        $this->assertSame('KNOWN|30000.000000|4|3', $this->valuationOf($book));
    }

    public function test_a_reversal_of_a_receipt_whose_units_a_later_sale_issued_is_refused_with_no_change(): void
    {
        // AC-028-11; G-07; BR-INV-37; COSTING section 8, the invalid history; TASK-026: 5 in, 4 sold, 10
        // in, 11 on hand. Reversing the first receipt would leave the Sale without stock at its point
        // of the logical ledger: REPLAY_REJECTED, nothing written and no mark. The second receipt, after
        // the Sale, reverses.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '10000']);
        $first = TokoContoh::stock($book, '5', '5000');
        TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '4']]);
        $second = TokoContoh::stock($book, '10', '20000');
        $before = [$this->saleRows(), $this->allocationsOf($book), $this->valuationOf($book), $this->watcher()->table('stock_movements')->count()];

        $this->assertSame(['refused' => 'REPLAY_REJECTED'], $this->reversal(TokoContoh::movementOf($first)));
        $this->assertSame($before, [$this->saleRows(), $this->allocationsOf($book), $this->valuationOf($book), $this->watcher()->table('stock_movements')->count()]);
        $this->assertSame(0, $this->watcher()->table('cost_incomplete_marks')->count());

        $this->assertSame('COMMITTED', $this->reversal(TokoContoh::movementOf($second))['outcome'] ?? null);
        $this->assertSame(['1.000', 4], array_values((array) $this->watcher()->table('stock_balances')->where('product_id', $book)->first(['on_hand', 'last_sequence'])));
        $this->assertSame([['SALE_ISSUE', 'KNOWN', '4000.000000', 'KNOWN', '4000.000000', 2]], $this->allocationsOf($book));
    }

    /**
     * RC-15 of a source by the Owner, the first correction of it reviewed: its outcome or the code of its refusal.
     *
     * @return array<string, mixed>
     */
    private function correction(int $evidence, string $amount): array
    {
        return (new UserCommandScenario)->run(['operation' => 'cost_correction.confirm', 'actor' => $this->owner, 'intent' => (string) Str::uuid(), 'key' => (string) Str::uuid(), 'epoch' => TokoContoh::recoveryEpoch(), 'expected' => ['correctionNo' => 0], 'payload' => ['evidenceId' => $evidence, 'newCost' => ['amount' => $amount], 'reason' => 'Nota pemasok']]);
    }

    /**
     * RC-10 of a movement by the Owner: its outcome or the code of its refusal.
     *
     * @return array<string, mixed>
     */
    private function reversal(int $movement): array
    {
        return (new UserCommandScenario)->run(['operation' => 'reversal.confirm', 'actor' => $this->owner, 'intent' => (string) Str::uuid(), 'key' => (string) Str::uuid(), 'epoch' => TokoContoh::recoveryEpoch(), 'expected' => [], 'payload' => ['movementId' => $movement, 'reason' => 'Salah catat']]);
    }

    /**
     * Every row of the Sales and their sessions, and every allocation fact, which no replay changes.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function saleRows(): array
    {
        $rows = [];

        foreach (['sales' => 'id', 'sale_lines' => 'id', 'payments' => 'id', 'receipt_snapshots' => 'id', 'sale_correction_totals' => 'sale_id', 'cash_sessions' => 'id', 'issue_cost_allocations' => 'id'] as $table => $key) {
            $rows[$table] = array_values($this->watcher()->table($table)->orderBy($key)->get()->map(static fn (object $row): array => (array) $row)->all());
        }

        return $rows;
    }

    /**
     * A product's allocations: the issue type, the fact's status and amount, the projection's status,
     * amount and publication.
     *
     * @return list<list<mixed>>
     */
    private function allocationsOf(int $product): array
    {
        return array_values($this->watcher()->table('issue_cost_allocations as a')->join('issue_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->where('a.product_id', $product)->orderBy('a.id')
            ->get(['a.issue_type', 'a.at_commit_status', 'a.at_commit_amount', 'p.current_status', 'p.current_amount', 'p.publication_no'])
            ->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    /**
     * A product's valuation as "status|value|publication|last sequence".
     */
    private function valuationOf(int $product): string
    {
        $row = (array) $this->watcher()->table('inventory_valuations')->where('product_id', $product)->sole();
        $text = static fn (mixed $value): string => is_string($value) ? $value : '';
        $number = static fn (mixed $value): string => is_int($value) ? (string) $value : '';

        return implode('|', [$text($row['value_status']), $text($row['value']), $number($row['publication_no']), $number($row['last_sequence'])]);
    }

    /** One independent session for every read of the test, so that the test stays within the server's connections. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
