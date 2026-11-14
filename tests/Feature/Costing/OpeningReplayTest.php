<?php

namespace Tests\Feature\Costing;

use App\Actions\Costing\CorrectCost;
use App\Database\CommandPoints;
use Illuminate\Database\Connection;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;

/**
 * The replay of TASK-025 reaches OPENING (AC-035-08, AC-035-09; ADR-008 sections 4.1 and 4.3; COSTING
 * sections 7 and 8; BR-COST-22 to BR-COST-25, BR-COST-32; D-060, D-087; G-07; TASK-035 scope item 4 and
 * section 7, L2; suite FIN03): an OPENING enters the logical ledger as an increase at its effective cost
 * evidence, and a completion or correction of its cost after the release starts the replay at it, through
 * the later Sales, manual issues and saleable returns; the same correction again gives the same result; a
 * failure during the publication changes nothing; the OPENING's evidence and every fact stay; a reversed
 * and replaced OPENING is a cancelled pair; and the fixture of COSTING section 8 with a real OPENING.
 */
final class OpeningReplayTest extends DatabaseTestCase
{
    use CommandRequests;

    private const string COMMAND = '/commands/cost_correction.confirm';

    private int $category = 0;

    private int $pen = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category();
        $this->pen = TokoContoh::product('PEN-01', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam']);
    }

    public function test_completing_an_openings_cost_after_the_release_replays_from_it_once_and_atomically(): void
    {
        // AC-035-08: 10 pens at UNKNOWN cost; after the release a Sale of 4, UNKNOWN at commit. Completing
        // the OPENING's cost at Rp50.000 publishes the Sale's projection as Rp20.000 and the valuation as
        // 6 worth Rp30.000; the fact, the evidence and every fact stay as committed (D-060; G-07).
        TokoContoh::startOnboarding();
        $opening = TokoContoh::openingStock($this->pen, '10', null);
        TokoContoh::releaseOnboarding();
        TokoContoh::sell('Hendra', TokoContoh::openSession('Hendra', TokoContoh::register()), [['productId' => $this->pen, 'quantity' => '4']]);
        $this->assertSame([['SALE_ISSUE', 'UNKNOWN', null, 'UNKNOWN', null, 2]], $this->allocationsOf($this->pen));
        $this->assertSame('UNKNOWN||2|2', $this->valuationOf($this->pen));
        $evidence = TokoContoh::evidenceOf($opening);
        $this->owner();
        $before = $this->factsAndRows();

        // A failure after each write step of the publication — the correction, the valuation, the
        // projections, the clearances, the audit event, the result — changes nothing (BR-COST-25).
        for ($step = 1; $step <= 6; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $this->correct($this->envelopeOf($evidence, '50000', 0))->assertStatus(500);
            $this->assertSame($before, $this->factsAndRows(), sprintf('after write step %d', $step));
        }

        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->correct($this->envelopeOf($evidence, '50000', 0))->assertStatus(201)->assertJsonPath('result.location', '/products/'.$this->pen.'/cost-history');

        $this->assertSame([['SALE_ISSUE', 'UNKNOWN', null, 'KNOWN', '20000.000000', 3]], $this->allocationsOf($this->pen));
        $this->assertSame('KNOWN|30000.000000|3|2', $this->valuationOf($this->pen));
        $this->assertSame(['OPENING', 'UNKNOWN', null], array_values((array) $this->watcher()->table('acquisition_cost_evidence')->where('id', $evidence)->first(['movement_type', 'cost_status', 'total_amount'])));
        $this->assertSame(['UNKNOWN', null, '50000.000000'], array_values((array) $this->watcher()->table('cost_corrections')->where('evidence_id', $evidence)->first(['old_status', 'old_amount', 'new_amount'])));
        $this->assertSame(['OPENING', 'SALE_ISSUE'], $this->watcher()->table('stock_movements')->where('product_id', $this->pen)->orderBy('product_sequence')->pluck('movement_type')->all());

        // The same correction again: the same projection, the valuation published again (ADR-008 section 4.3).
        TokoContoh::correctCost($evidence, '50000', 'Nota yang sama');
        $this->assertSame([['SALE_ISSUE', 'UNKNOWN', null, 'KNOWN', '20000.000000', 3]], $this->allocationsOf($this->pen));
        $this->assertSame('KNOWN|30000.000000|4|2', $this->valuationOf($this->pen));
    }

    public function test_the_replay_from_an_opening_reaches_the_later_sales_manual_issues_and_returns(): void
    {
        // TASK-035 section 7, L2; ADR-008 sections 3, 4.3 and 5; BR-COST-17, BR-COST-18: 10 at UNKNOWN; a
        // Sale of 4, a manual issue of 2, one returned saleable, all UNKNOWN. Completing the OPENING at
        // Rp50.000: the Sale Rp20.000, the issue Rp10.000, the return restoring a quarter of the Sale's,
        // Rp5.000; 5 left worth Rp25.000.
        TokoContoh::startOnboarding();
        $opening = TokoContoh::openingStock($this->pen, '10', null);
        TokoContoh::releaseOnboarding();
        $sale = TokoContoh::sell('Hendra', TokoContoh::openSession('Hendra', TokoContoh::register()), [['productId' => $this->pen, 'quantity' => '4']]);
        TokoContoh::issue([['productId' => $this->pen, 'quantity' => '2']]);
        TokoContoh::correct($sale, [['saleLineId' => TokoContoh::saleLinesOf($sale)[0] ?? 0, 'returnedSaleable' => '1']]);
        $this->assertSame([['UNKNOWN', null]], $this->returnsOf($this->pen));
        $this->assertSame('UNKNOWN||4|4', $this->valuationOf($this->pen));

        TokoContoh::correctCost(TokoContoh::evidenceOf($opening), '50000');

        $this->assertSame([
            ['SALE_ISSUE', 'UNKNOWN', null, 'KNOWN', '20000.000000', 5],
            ['MANUAL_ISSUE', 'UNKNOWN', null, 'KNOWN', '10000.000000', 5],
        ], $this->allocationsOf($this->pen));
        $this->assertSame([['KNOWN', '5000.000000']], $this->returnsOf($this->pen));
        $this->assertSame('KNOWN|25000.000000|5|4', $this->valuationOf($this->pen));
        $this->assertSame(['5.000', 4], $this->balanceOf($this->pen));
    }

    public function test_a_reversed_and_replaced_opening_is_a_cancelled_pair_and_the_replacement_enters_at_its_own_place(): void
    {
        // AC-035-09; BR-COST-32; D-087; G-24: OPENING A of 10 at Rp100.000 reversed during onboarding and
        // replaced by OPENING B of 8 at Rp96.000; after the release a Sale of 4 at Rp48.000. Correcting B to
        // Rp88.000 republishes the Sale as Rp44.000 and the valuation as 4 worth Rp44.000, as a ledger
        // holding B alone gives.
        TokoContoh::startOnboarding();
        $original = TokoContoh::openingStock($this->pen, '10', '100000');
        TokoContoh::reverse($original);
        $replacement = TokoContoh::replaceOpening(TokoContoh::openingLineOf($original), '8', '96000');
        $twin = TokoContoh::product('PEN-02', ['categoryId' => $this->category, 'name' => 'Pulpen Kembar']);
        TokoContoh::openingStock($twin, '8', '88000');
        TokoContoh::releaseOnboarding();
        $session = TokoContoh::openSession('Hendra', TokoContoh::register());
        TokoContoh::sell('Hendra', $session, [['productId' => $this->pen, 'quantity' => '4']]);
        TokoContoh::sell('Hendra', $session, [['productId' => $twin, 'quantity' => '4']]);
        $this->assertSame([['SALE_ISSUE', 'KNOWN', '48000.000000', 'KNOWN', '48000.000000', 4]], $this->allocationsOf($this->pen));

        TokoContoh::correctCost(TokoContoh::evidenceOf($replacement), '88000');

        $this->assertSame([['SALE_ISSUE', 'KNOWN', '48000.000000', 'KNOWN', '44000.000000', 5]], $this->allocationsOf($this->pen));
        $this->assertSame('KNOWN|44000.000000|5|4', $this->valuationOf($this->pen));
        $this->assertSame([['SALE_ISSUE', 'KNOWN', '44000.000000', 'KNOWN', '44000.000000', 2]], $this->allocationsOf($twin));
        $this->assertSame('KNOWN|44000.000000|2|2', $this->valuationOf($twin));

        // The physical ledger keeps the original, its REVERSAL and the replacement (BR-COST-32).
        $this->assertSame(['OPENING', 'REVERSAL', 'OPENING', 'SALE_ISSUE'], $this->watcher()->table('stock_movements')->where('product_id', $this->pen)->orderBy('product_sequence')->pluck('movement_type')->all());
        $this->assertSame(['4.000', 4], $this->balanceOf($this->pen));
    }

    public function test_the_fixture_of_costing_section_8_with_a_real_opening(): void
    {
        // AC-035-09; COSTING section 8: OPENING 10 at 100, RECEIPT 10 at 200, an issue of 5: 15 worth 225,
        // issue cost 75. The full reversal of the RECEIPT gives 5 worth 50 and the issue's projection 50,
        // its fact staying 75 (D-060; ADR-008).
        TokoContoh::startOnboarding();
        TokoContoh::openingStock($this->pen, '10', '100');
        TokoContoh::releaseOnboarding();
        $receipt = TokoContoh::movementOf(TokoContoh::stock($this->pen, '10', '200'));
        TokoContoh::issue([['productId' => $this->pen, 'quantity' => '5']]);
        $this->assertSame(['15.000', 3], $this->balanceOf($this->pen));
        $this->assertSame('KNOWN|225.000000|3|3', $this->valuationOf($this->pen));
        $this->assertSame([['MANUAL_ISSUE', 'KNOWN', '75.000000', 'KNOWN', '75.000000', 3]], $this->allocationsOf($this->pen));

        TokoContoh::reverse($receipt);

        $this->assertSame(['5.000', 4], $this->balanceOf($this->pen));
        $this->assertSame('KNOWN|50.000000|4|4', $this->valuationOf($this->pen));
        $this->assertSame([['MANUAL_ISSUE', 'KNOWN', '75.000000', 'KNOWN', '50.000000', 4]], $this->allocationsOf($this->pen));
    }

    /** The Owner, with the recent authentication of RC-15 (INTERACTION_CONTRACTS section 1.5). */
    private function owner(): void
    {
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
    }

    /**
     * An envelope of RC-15 for this cost source, new total and correction reviewed.
     *
     * @return array<string, mixed>
     */
    private function envelopeOf(int $evidence, string $amount, int $correctionNo): array
    {
        return $this->envelope(['evidenceId' => $evidence, 'newCost' => ['amount' => $amount], 'reason' => 'Nota ditemukan'], ['operation' => CorrectCost::OPERATION, 'expected' => ['correctionNo' => $correctionNo]]);
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function correct(array $envelope): TestResponse
    {
        return $this->postJson(self::COMMAND, $envelope);
    }

    /**
     * The issue allocations of a product in their order: the type, the fact's status and amount, the
     * projection's status, amount and publication.
     *
     * @return list<list<mixed>>
     */
    private function allocationsOf(int $product): array
    {
        return array_values($this->watcher()->table('issue_cost_allocations as a')->join('issue_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->where('a.product_id', $product)->orderBy('a.product_sequence')
            ->get(['a.issue_type', 'a.at_commit_status', 'a.at_commit_amount', 'p.current_status', 'p.current_amount', 'p.publication_no'])
            ->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    /**
     * The return allocations' projections of a product in their order: status and amount.
     *
     * @return list<list<mixed>>
     */
    private function returnsOf(int $product): array
    {
        return array_values($this->watcher()->table('return_cost_allocations as ra')->join('return_cost_allocation_projections as rp', 'rp.allocation_id', '=', 'ra.id')
            ->join('stock_movements as m', 'm.id', '=', 'ra.movement_id')->where('m.product_id', $product)->orderBy('m.product_sequence')
            ->get(['rp.current_status', 'rp.current_amount'])->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    /**
     * A product's valuation as "status|value|publication|last sequence".
     */
    private function valuationOf(int $product): string
    {
        $row = (array) $this->watcher()->table('inventory_valuations')->where('product_id', $product)->sole();

        return implode('|', [self::text($row['value_status']), self::text($row['value']), (string) self::number($row['publication_no']), (string) self::number($row['last_sequence'])]);
    }

    /**
     * A product's balance: on hand and the last sequence.
     *
     * @return array{mixed, mixed}|null
     */
    private function balanceOf(int $product): ?array
    {
        $row = $this->watcher()->table('stock_balances')->where('product_id', $product)->first(['on_hand', 'last_sequence']);

        return $row === null ? null : [((array) $row)['on_hand'], ((array) $row)['last_sequence']];
    }

    /** One independent session for every read of the test. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    /**
     * Every fact and projection that a correction may write, in key order.
     *
     * @return array<string, mixed>
     */
    private function factsAndRows(): array
    {
        $rows = [];

        foreach (['cost_corrections' => 'id', 'acquisition_cost_evidence' => 'id', 'issue_cost_allocations' => 'id', 'issue_cost_allocation_projections' => 'allocation_id', 'inventory_valuations' => 'product_id', 'stock_movements' => 'id', 'audit_events' => 'id', 'command_results' => 'command_id'] as $table => $key) {
            $rows[$table] = array_values($this->watcher()->table($table)->orderBy($key)->get()->map(static fn (object $row): array => (array) $row)->all());
        }

        return $rows;
    }
}
