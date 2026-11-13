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
use Throwable;

/**
 * The replay over saleable returns (AC-033-04; ADR-008 sections 3, 4.1, 4.3 and 5; COSTING BR-COST-17,
 * BR-COST-18, BR-COST-22 to BR-COST-25, BR-COST-32; D-060, D-061; G-07, G-13, G-14; suites RET01 and
 * FIN03; TASK-033 scope item 4 and section 7, L2): a cost correction recomputes each return projection
 * together with its issue — D-061's worked example — so that the cost is restored exactly once, also over
 * several partial returns, which leave no residue; a damaged return counts for no cost, and the saleable
 * returns after it restore their cumulative share; an UNKNOWN issue's return is completed with it; a
 * return of an issue before the start keeps its cost, and the state before the start counts the returns'
 * projections; a reversal recomputes the returns of the issues it reaches; a refused replay and a failure
 * during the publication change nothing. No replay touches a return's fact, its correction, its return
 * line or its Sale.
 */
final class CorrectionReplayTest extends DatabaseTestCase
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

    public function test_a_corrected_cost_recomputes_the_issue_and_its_linked_returns_together_and_restores_it_once(): void
    {
        // D-061's worked example; BR-COST-17, BR-COST-18: 10 units received for Rp1.000 and all sold; 2
        // returned saleable restore Rp200. The receipt corrected to Rp1.200: the issue's projection becomes
        // Rp1.200 and the return's Rp240 in the same publication, its fact staying Rp200; the 2 units are
        // worth Rp240 and net cost of goods sold is Rp960, 8 × Rp120.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '10000']);
        $receipt = TokoContoh::stock($book, '10', '1000');
        $sale = TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '10']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        TokoContoh::correct($sale, [['saleLineId' => $line, 'returnedSaleable' => '2']]);
        $this->assertSame([['KNOWN', '200.000000', 'KNOWN', '200.000000', 3]], $this->returnsOf($book));
        $this->assertSame('KNOWN|200.000000|3|3', $this->valuationOf($book));
        $kept = $this->correctionRows();
        $evidence = TokoContoh::evidenceOf(TokoContoh::movementOf($receipt));

        TokoContoh::correctCost($evidence, '1200', 'Nota pemasok');
        $this->assertSame([['SALE_ISSUE', 'KNOWN', '1000.000000', 'KNOWN', '1200.000000', 4]], $this->allocationsOf($book));
        $this->assertSame([['KNOWN', '200.000000', 'KNOWN', '240.000000', 4]], $this->returnsOf($book));
        $this->assertSame('KNOWN|240.000000|4|3', $this->valuationOf($book));
        $this->assertSame($kept, $this->correctionRows());

        // Two more come back: the cumulative rule restores Rp480 − Rp240; four are worth Rp480.
        TokoContoh::correct($sale, [['saleLineId' => $line, 'returnedSaleable' => '2']]);
        $this->assertSame([['KNOWN', '200.000000', 'KNOWN', '240.000000', 4], ['KNOWN', '240.000000', 'KNOWN', '240.000000', 5]], $this->returnsOf($book));
        $this->assertSame('KNOWN|480.000000|5|4', $this->valuationOf($book));

        // The receipt corrected back to Rp1.000: each return Rp200 again, Rp400 restored in all for 4 of 10
        // — never twice, and no new return, movement or allocation (BR-COST-18).
        $movements = $this->watcher()->table('stock_movements')->count();
        TokoContoh::correctCost($evidence, '1000', 'Nota yang benar');
        $this->assertSame([['KNOWN', '200.000000', 'KNOWN', '200.000000', 6], ['KNOWN', '240.000000', 'KNOWN', '200.000000', 6]], $this->returnsOf($book));
        $this->assertSame('KNOWN|400.000000|6|4', $this->valuationOf($book));
        $this->assertSame([$movements, 2], [$this->watcher()->table('stock_movements')->count(), $this->watcher()->table('return_cost_allocations')->count()]);
    }

    public function test_a_damaged_return_counts_for_no_cost_and_the_saleable_returns_after_it_restore_their_cumulative_share(): void
    {
        // ADR-008 section 5 and its test list ("a partial return after a not-saleable return of the same
        // line"); BR-COST-19; D-061: R(k) counts the saleable returns alone, never the line's running total.
        // 3 units received for Rp100 and all three sold: the issue takes Rp100. One unit comes back damaged:
        // no RETURN_RECEIPT, no allocation, the valuation as it was. Then one and one come back saleable and
        // restore Rp33,333333 and Rp33,333334, the first and the second third — had the damaged unit counted,
        // the first would restore Rp33,333334. The receipt corrected to Rp200: the replay, with R in ledger
        // order, gives Rp66,666667 and Rp66,666666; the facts stay.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '10000']);
        $receipt = TokoContoh::stock($book, '3', '100');
        $sale = TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '3']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $this->assertSame([['SALE_ISSUE', 'KNOWN', '100.000000', 'KNOWN', '100.000000', 2]], $this->allocationsOf($book));

        TokoContoh::correct($sale, [['saleLineId' => $line, 'returnedNotSaleable' => '1']]);
        $this->assertSame([[], 'KNOWN|0.000000|2|2', 0], [$this->returnsOf($book), $this->valuationOf($book), $this->watcher()->table('stock_movements')->where('movement_type', 'RETURN_RECEIPT')->count()]);

        TokoContoh::correct($sale, [['saleLineId' => $line, 'returnedSaleable' => '1']]);
        $this->assertSame([[['KNOWN', '33.333333', 'KNOWN', '33.333333', 3]], 'KNOWN|33.333333|3|3'], [$this->returnsOf($book), $this->valuationOf($book)]);
        TokoContoh::correct($sale, [['saleLineId' => $line, 'returnedSaleable' => '1']]);
        $this->assertSame([['KNOWN', '33.333333', 'KNOWN', '33.333333', 3], ['KNOWN', '33.333334', 'KNOWN', '33.333334', 4]], $this->returnsOf($book));
        $this->assertSame('KNOWN|66.666667|4|4', $this->valuationOf($book));

        TokoContoh::correctCost(TokoContoh::evidenceOf(TokoContoh::movementOf($receipt)), '200', 'Nota pemasok');
        $this->assertSame([['SALE_ISSUE', 'KNOWN', '100.000000', 'KNOWN', '200.000000', 5]], $this->allocationsOf($book));
        $this->assertSame([['KNOWN', '33.333333', 'KNOWN', '66.666667', 5], ['KNOWN', '33.333334', 'KNOWN', '66.666666', 5]], $this->returnsOf($book));
        $this->assertSame('KNOWN|133.333333|5|4', $this->valuationOf($book));
    }

    public function test_partial_returns_restore_the_whole_issue_with_no_residue_and_a_refused_replay_changes_no_projection(): void
    {
        // ADR-008 section 5 and its test list ("several partial returns of one line leaving no residue",
        // "a refused replay leaving every projection unchanged"); D-061: 3 units received for Rp100, all
        // three sold, and returned saleable one at a time restore Rp33,333333, Rp33,333334 and Rp33,333333 —
        // exactly Rp100 — each fact equal to its projection. A replay refused at the movement cap changes
        // no projection and no valuation. The receipt corrected to Rp200 gives Rp66,666667, Rp66,666666 and
        // Rp66,666667 — exactly Rp200 — the facts as they were; corrected back to Rp100, every projection
        // equals its fact again, with no drift.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '10000']);
        $receipt = TokoContoh::stock($book, '3', '100');
        $sale = TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '3']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;

        TokoContoh::correct($sale, [['saleLineId' => $line, 'returnedSaleable' => '1']]);
        TokoContoh::correct($sale, [['saleLineId' => $line, 'returnedSaleable' => '1']]);
        TokoContoh::correct($sale, [['saleLineId' => $line, 'returnedSaleable' => '1']]);
        $restored = [['KNOWN', '33.333333', 'KNOWN', '33.333333', 3], ['KNOWN', '33.333334', 'KNOWN', '33.333334', 4], ['KNOWN', '33.333333', 'KNOWN', '33.333333', 5]];
        $this->assertSame([$restored, 'KNOWN|100.000000|5|5'], [$this->returnsOf($book), $this->valuationOf($book)]);

        // From the receipt the replay reaches five movements: at a cap of four it is refused.
        $evidence = TokoContoh::evidenceOf(TokoContoh::movementOf($receipt));
        $cap = config('costing.replay.movement_cap');
        config(['costing.replay.movement_cap' => 4]);
        $this->assertSame(['refused' => 'REPLAY_REJECTED'], $this->correction($evidence, '200'));
        $this->assertSame([[['SALE_ISSUE', 'KNOWN', '100.000000', 'KNOWN', '100.000000', 2]], $restored, 'KNOWN|100.000000|5|5'], [$this->allocationsOf($book), $this->returnsOf($book), $this->valuationOf($book)]);
        config(['costing.replay.movement_cap' => $cap]);

        TokoContoh::correctCost($evidence, '200', 'Nota pemasok');
        $this->assertSame([['KNOWN', '33.333333', 'KNOWN', '66.666667', 6], ['KNOWN', '33.333334', 'KNOWN', '66.666666', 6], ['KNOWN', '33.333333', 'KNOWN', '66.666667', 6]], $this->returnsOf($book));
        $this->assertSame([[['SALE_ISSUE', 'KNOWN', '100.000000', 'KNOWN', '200.000000', 6]], 'KNOWN|200.000000|6|5'], [$this->allocationsOf($book), $this->valuationOf($book)]);

        TokoContoh::correctCost($evidence, '100', 'Nota yang benar');
        $this->assertSame([['KNOWN', '33.333333', 'KNOWN', '33.333333', 7], ['KNOWN', '33.333334', 'KNOWN', '33.333334', 7], ['KNOWN', '33.333333', 'KNOWN', '33.333333', 7]], $this->returnsOf($book));
        $this->assertSame('KNOWN|100.000000|7|5', $this->valuationOf($book));
    }

    public function test_an_unknown_issue_gives_an_unknown_return_which_its_completion_completes(): void
    {
        // ADR-008 section 3, "UNKNOWN"; BR-COST-13, BR-COST-22: 5 received at UNKNOWN cost and sold; one
        // returned restores UNKNOWN, never zero, and the valuation is UNKNOWN. Completing the receipt at
        // Rp500 completes the issue, Rp500, and the return, Rp100, together; the return's fact stays UNKNOWN.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '10000']);
        $receipt = TokoContoh::receive([['productId' => $book, 'quantity' => '5', 'cost' => ['status' => 'UNKNOWN']]]);
        $sale = TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '5']]);
        TokoContoh::correct($sale, [['saleLineId' => TokoContoh::saleLinesOf($sale)[0] ?? 0, 'returnedSaleable' => '1']]);
        $this->assertSame([['UNKNOWN', null, 'UNKNOWN', null, 3]], $this->returnsOf($book));
        $this->assertSame('UNKNOWN||3|3', $this->valuationOf($book));

        TokoContoh::correctCost(TokoContoh::evidenceOf(TokoContoh::movementOf($receipt)), '500', 'Nota ditemukan');
        $this->assertSame([['SALE_ISSUE', 'UNKNOWN', null, 'KNOWN', '500.000000', 4]], $this->allocationsOf($book));
        $this->assertSame([['UNKNOWN', null, 'KNOWN', '100.000000', 4]], $this->returnsOf($book));
        $this->assertSame('KNOWN|100.000000|4|3', $this->valuationOf($book));
    }

    public function test_a_return_of_an_issue_before_the_start_keeps_its_cost_and_the_state_before_the_start_counts_it(): void
    {
        // ADR-008 section 4.3: 10 received for Rp1.000 and all sold; 2 returned (Rp200); then 5 received for
        // Rp1.500. Correcting that receipt to Rp2.000 replays from it: the state before it is the return
        // alone, 2 worth Rp200, after the zero point of the Sale; the result 7 worth Rp2.200, the return's
        // projection unchanged. A later return of the same issue restores from its projection as it stands.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '10000']);
        TokoContoh::stock($book, '10', '1000');
        $sale = TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '10']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        TokoContoh::correct($sale, [['saleLineId' => $line, 'returnedSaleable' => '2']]);
        $later = TokoContoh::stock($book, '5', '1500');
        $this->assertSame('KNOWN|1700.000000|4|4', $this->valuationOf($book));

        TokoContoh::correctCost(TokoContoh::evidenceOf(TokoContoh::movementOf($later)), '2000', 'Nota pemasok');
        $this->assertSame([['KNOWN', '200.000000', 'KNOWN', '200.000000', 3]], $this->returnsOf($book));
        $this->assertSame([['SALE_ISSUE', 'KNOWN', '1000.000000', 'KNOWN', '1000.000000', 2]], $this->allocationsOf($book));
        $this->assertSame('KNOWN|2200.000000|5|4', $this->valuationOf($book));

        TokoContoh::correct($sale, [['saleLineId' => $line, 'returnedSaleable' => '3']]);
        $this->assertSame(['KNOWN', '300.000000', 'KNOWN', '300.000000', 6], $this->returnsOf($book)[1] ?? null);
        $this->assertSame('KNOWN|2500.000000|6|5', $this->valuationOf($book));
    }

    public function test_a_reversal_recomputes_the_returns_of_the_issues_it_reaches(): void
    {
        // ADR-008 sections 4.1 and 4.3; BR-COST-32; TASK-026: 10 received for Rp1.000, then 10 for Rp3.000;
        // 5 sold for Rp1.000; one returned restores Rp200. Reversing the second receipt replays the logical
        // ledger without it: the Sale took Rp500 of the first pool, so its return restores Rp100, and 6 are
        // worth Rp600.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '10000']);
        TokoContoh::stock($book, '10', '1000');
        $second = TokoContoh::stock($book, '10', '3000');
        $sale = TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '5']]);
        TokoContoh::correct($sale, [['saleLineId' => TokoContoh::saleLinesOf($sale)[0] ?? 0, 'returnedSaleable' => '1']]);
        $this->assertSame([['KNOWN', '200.000000', 'KNOWN', '200.000000', 4]], $this->returnsOf($book));
        $this->assertSame('KNOWN|3200.000000|4|4', $this->valuationOf($book));

        $this->assertSame('COMMITTED', $this->reversal(TokoContoh::movementOf($second))['outcome'] ?? null);
        $this->assertSame([['SALE_ISSUE', 'KNOWN', '1000.000000', 'KNOWN', '500.000000', 5]], $this->allocationsOf($book));
        $this->assertSame([['KNOWN', '200.000000', 'KNOWN', '100.000000', 5]], $this->returnsOf($book));
        $this->assertSame(['KNOWN|600.000000|5|5', '6.000'], [$this->valuationOf($book), $this->watcher()->table('stock_balances')->where('product_id', $book)->value('on_hand')]);
    }

    public function test_a_failure_during_the_publication_changes_no_return_projection(): void
    {
        // RACE-11, "publication is all or nothing"; BR-COST-25; TS-21: a failure after each of the cost
        // correction's six write steps leaves the issue's and the return's projections, the valuation and
        // every fact as they were; then the correction publishes both.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '10000']);
        $receipt = TokoContoh::stock($book, '10', '1000');
        $sale = TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '10']]);
        TokoContoh::correct($sale, [['saleLineId' => TokoContoh::saleLinesOf($sale)[0] ?? 0, 'returnedSaleable' => '2']]);
        $evidence = TokoContoh::evidenceOf(TokoContoh::movementOf($receipt));
        $before = [$this->allocationsOf($book), $this->returnsOf($book), $this->valuationOf($book), $this->correctionRows()];

        for ($step = 1; $step <= 6; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));

            $this->assertInstanceOf(InjectedFailure::class, $this->failureOf($evidence, '1200'), (string) $step);

            $this->assertSame($before, [$this->allocationsOf($book), $this->returnsOf($book), $this->valuationOf($book), $this->correctionRows()], (string) $step);
        }

        $this->app->instance(CommandPoints::class, new FailurePoints);
        TokoContoh::correctCost($evidence, '1200', 'Nota pemasok');
        $this->assertSame([['KNOWN', '200.000000', 'KNOWN', '240.000000', 4]], $this->returnsOf($book));
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
     * What RC-15 of a source by the Owner failed with, or null when it committed or was refused.
     */
    private function failureOf(int $evidence, string $amount): ?Throwable
    {
        try {
            $this->correction($evidence, $amount);
        } catch (Throwable $failure) {
            return $failure;
        }

        return null;
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
     * Every row of the corrections and their records, the return allocation facts and the Sales, which no
     * replay changes (D-060, D-061).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function correctionRows(): array
    {
        $rows = [];

        foreach (['sale_corrections' => 'id', 'refunds' => 'id', 'refund_lines' => 'id', 'return_lines' => 'id', 'return_cost_allocations' => 'id', 'sale_correction_totals' => 'sale_id', 'sale_line_correction_totals' => 'sale_line_id', 'sales' => 'id', 'sale_lines' => 'id', 'cash_sessions' => 'id'] as $table => $key) {
            $rows[$table] = array_values($this->watcher()->table($table)->orderBy($key)->get()->map(static fn (object $row): array => (array) $row)->all());
        }

        return $rows;
    }

    /**
     * A product's issue allocations: the issue type, the fact's status and amount, the projection's
     * status, amount and publication.
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
     * A product's return allocations: the fact's status and amount, the projection's status, amount and
     * publication.
     *
     * @return list<list<mixed>>
     */
    private function returnsOf(int $product): array
    {
        return array_values($this->watcher()->table('return_cost_allocations as a')->join('return_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->where('a.product_id', $product)->orderBy('a.id')
            ->get(['a.at_commit_status', 'a.at_commit_amount', 'p.current_status', 'p.current_amount', 'p.publication_no'])
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
