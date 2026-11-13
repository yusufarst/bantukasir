<?php

namespace Tests\Feature\Sales;

use App\Actions\Sales\RecordCorrection;
use App\Database\CommandPoints;
use Carbon\CarbonImmutable;
use FilesystemIterator;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-06 `sale_correction.other` through its route and its Action (AC-033-01 to AC-033-05; INTERACTION_CONTRACTS
 * RC-06 and sections 1.1 to 1.6; ROUTE_CONTRACTS RC-06; TECHNICAL_RULES sections 7.1, 7.2 and 11; SCHEMA
 * sections 5.7 to 5.9; SALES_CASH BR-RFD-01 to BR-RFD-14; COSTING BR-COST-17 to BR-COST-19, BR-COST-36;
 * ADR-008 sections 3 and 5; D-035, D-061; G-06, G-10, G-11, G-13, G-14, G-16, G-18; CAP-24; THR-19;
 * EXECUTION_CONTEXT section 10, a route and a command; suites RET01, FIN03, CMD01, AUTH01, AUTH03; TASK-033
 * section 7, L2 and L4): the Owner's refund by bank transfer with a saleable and a damaged return, every
 * record in one transaction; the Owner alone, with recent authentication; the separate, cumulative money
 * and quantity bounds; a refund without a return and a return without money; onboarding, a block and an
 * inactive product, which refuse only a saleable return; the refusals before any lock; the envelope, a
 * repeat and a status check; a failure after every write step; the original Sale and a closed session's
 * report unchanged; and the one writer of each new table.
 */
final class RecordCorrectionTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string COMMAND = '/commands/sale_correction.other';

    private const string REASON = 'Pembeli mengembalikan barang';

    /** The tables that a refused or failed correction leaves as they were. */
    private const array FACTS = ['sale_corrections', 'refunds', 'refund_lines', 'return_lines', 'return_cost_allocations', 'return_cost_allocation_projections', 'stock_movements', 'issue_cost_allocations', 'command_identities', 'command_results', 'audit_events'];

    /** Counted whole: `pcs`, precision 0, Rp7.500. */
    private int $pen = 0;

    /** Weighed: `kg`, precision 3, Rp12.000. */
    private int $rice = 0;

    private int $session = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $category = TokoContoh::category();
        $this->pen = TokoContoh::product('PEN-01', ['categoryId' => $category, 'name' => 'Pulpen Hitam', 'sellingPrice' => '7500']);
        $this->rice = TokoContoh::product('BERAS-01', ['categoryId' => $category, 'name' => 'Beras', 'baseUnit' => 'kg', 'quantityPrecision' => 3, 'sellingPrice' => '12000']);
        $this->session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
    }

    public function test_the_owner_refunds_by_bank_transfer_and_takes_goods_back_with_every_record_in_one_transaction(): void
    {
        // AC-033-03 to AC-033-05; BR-RFD-02 to BR-RFD-12; ADR-008 sections 3 and 5; G-06, G-13, G-14, G-16,
        // G-18. On 7 November Rina sells two pens and 1,5 kg of rice for Rp33.000; on 8 November at 10:00
        // in Asia/Jakarta the Owner refunds Rp7.500 of the pens and Rp6.000 of the rice by bank transfer,
        // takes one pen back saleable and 0,5 kg of rice damaged.
        $this->travelTo('2026-11-07T03:30:00.000000Z');
        TokoContoh::stock($this->pen, '10', '50000');
        TokoContoh::stock($this->rice, '5', '30000');
        $sale = TokoContoh::sell('Rina', $this->session, [self::sold($this->pen, '2'), self::sold($this->rice, '1.5')], ['method' => 'CASH', 'tendered' => '50000']);
        [$penLine, $riceLine] = TokoContoh::saleLinesOf($sale);
        $number = self::text($this->watcher()->table('sales')->where('id', $sale)->value('sale_number'));
        $penIssue = $this->issueOf($penLine);
        $kept = $this->saleRows($sale);
        $this->travelTo('2026-11-08T03:00:00.250000Z');
        $hendra = $this->people['Hendra']['id'];
        $envelope = $this->envelopeOf($sale, [self::line($penLine, '7500', '1'), self::line($riceLine, '6000', '0', '0.5')], self::transfer('TRF-77'));

        // AUTH03: without a recent password confirmation nothing runs, not even a receipt.
        $this->as('Hendra');
        $this->correct($envelope)->assertStatus(423)->assertJsonPath('code', 'RECENT_AUTH_REQUIRED');
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', RecordCorrection::OPERATION)->count());

        $this->confirm();
        $response = $this->correct($envelope)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed', 'owner');
        $correction = self::number($response->json('result.id'));
        $commandId = self::number($response->json('commandId'));
        // Its location is RP-204, the correction recorded (TASK-034).
        $this->assertSame(['sale_correction', '/sales/'.$sale.'/corrections/'.$correction], [$response->json('result.type'), $response->json('result.location')]);

        // The correction, linked to its Sale and dated by its posting (BR-COST-36, G-18), the actor's name frozen (G-16).
        $row = (array) $this->watcher()->table('sale_corrections')->where('id', $correction)->sole();
        $this->assertSame([$sale, '2026-11-08', self::REASON, $hendra, 'Hendra', $commandId], [$row['sale_id'], $row['business_date'], $row['reason'], $row['actor_user_id'], $row['actor_display_name'], $row['command_id']]);
        $this->assertSame('2026-11-08T03:00:00.250000Z', self::instant($row['recorded_at']));

        // The refund, the sum of its lines (G-06), by bank transfer with the payout's reference (BR-RFD-05).
        $this->assertSame([$sale, 13500, 'BANK_TRANSFER', null, null, 'TRF-77'], array_values((array) $this->watcher()->table('refunds')->where('correction_id', $correction)->sole(['sale_id', 'amount', 'payout_method', 'funding_source', 'cash_session_id', 'payout_reference'])));
        $this->assertSame([[$penLine, 7500], [$riceLine, 6000]], $this->rowsOf('refund_lines', ['sale_line_id', 'amount']));

        // The return lines: the pen saleable, posting its RETURN_RECEIPT; the rice damaged, posting none.
        $returns = $this->rowsOf('return_lines', ['id', 'sale_line_id', 'product_id', 'quantity', 'condition', 'movement_presence']);
        $penReturn = self::number($returns[0][0] ?? null);
        $this->assertSame([
            [$penReturn, $penLine, $this->pen, '1.000', 'SALEABLE', $penReturn],
            [self::number($returns[1][0] ?? null), $riceLine, $this->rice, '0.500', 'NOT_SALEABLE', null],
        ], $returns);

        // The running totals, by relative increments (SCH-33): the money of both lines and both returns.
        $this->assertSame([33000, 13500], array_values((array) $this->watcher()->table('sale_correction_totals')->where('sale_id', $sale)->sole(['sale_total', 'refunded_amount'])));
        $this->assertSame([[$penLine, 15000, '2.000', 7500, '1.000'], [$riceLine, 18000, '1.500', 6000, '0.500']], $this->rowsOf('sale_line_correction_totals', ['sale_line_id', 'line_total', 'issued_quantity', 'refunded_amount', 'returned_quantity']));

        // AC-033-03: one RETURN_RECEIPT of the pen, in its next place; the rice's stock unchanged.
        $movement = (array) $this->watcher()->table('stock_movements')->where('movement_type', 'RETURN_RECEIPT')->sole();
        $this->assertSame([$this->pen, 3, '1.000', $penReturn, '1.000', 'SALEABLE', $commandId], [$movement['product_id'], $movement['product_sequence'], $movement['quantity'], $movement['return_line_id'], $movement['line_quantity'], $movement['return_condition'], $movement['command_id']]);
        $this->assertSame([['9.000', 3], ['3.500', 2]], [$this->balanceOf($this->pen), $this->balanceOf($this->rice)]);

        // AC-033-04, D-061: the return restores its share of the pen issue's cost, Rp5.000 of Rp10.000 for one
        // of two, linked to that issue allocation; the fact and its projection equal (G-13, G-14); the
        // valuation 9 worth Rp45.000. The issue allocations and the rice's valuation are unchanged: a damaged
        // return restores no cost (BR-COST-19).
        $allocation = (array) $this->watcher()->table('return_cost_allocations as a')->join('return_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')->sole();
        $this->assertSame(
            [$penReturn, self::number($movement['id']), $this->pen, $penLine, 'SALEABLE', $penIssue, '1.000', 3, 'KNOWN', '5000.000000', 2, 'KNOWN', '5000.000000', 3],
            [$allocation['return_line_id'], $allocation['movement_id'], $allocation['product_id'], $allocation['sale_line_id'], $allocation['return_condition'], $allocation['issue_allocation_id'], $allocation['returned_quantity'], $allocation['product_sequence'], $allocation['at_commit_status'], $allocation['at_commit_amount'], $allocation['based_on_publication_no'], $allocation['current_status'], $allocation['current_amount'], $allocation['publication_no']],
        );
        $this->assertSame(['KNOWN|45000.000000|3|3', 'KNOWN|21000.000000|2|2'], [$this->valuationOf($this->pen), $this->valuationOf($this->rice)]);

        // BR-RFD-12, AC-033-05: the Sale, its lines, its Payment, its receipt and its issue allocations
        // unchanged; no session touched (D-035).
        $this->assertSame($kept, $this->saleRows($sale));

        // One audit event with the Sale's number as its reference and the reason in its column; the cost
        // restored is the Owner's part of it.
        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['BUSINESS', 'SALE_CORRECTION', 'USER', $hendra, 'Hendra', 'sale_correction', $correction, $number, self::REASON, null, null], [$event['category'], $event['action'], $event['actor_kind'], $event['actor_user_id'], $event['actor_display_name'], $event['subject_type'], $event['subject_id'], $event['reference'], $event['reason'], $event['product_id'], $event['cash_session_id']]);
        $this->assertSame(['businessDate' => '2026-11-08', 'lines' => [
            ['movementId' => self::number($movement['id']), 'productId' => $this->pen, 'productSequence' => 3, 'refundAmount' => '7500', 'returnedNotSaleable' => '0', 'returnedSaleable' => '1', 'saleLineId' => $penLine],
            ['movementId' => null, 'productId' => $this->rice, 'productSequence' => null, 'refundAmount' => '6000', 'returnedNotSaleable' => '0.5', 'returnedSaleable' => '0', 'saleLineId' => $riceLine],
        ], 'refund' => ['amount' => '13500', 'fundingSource' => null, 'payoutMethod' => 'BANK_TRANSFER', 'payoutReference' => 'TRF-77'], 'saleId' => $sale, 'saleNumber' => $number], self::dataOf($event['change_data']));
        $this->assertSame(['returns' => [['allocationId' => self::number($allocation['id']), 'amount' => '5000', 'basedOnPublicationNo' => 2, 'issueAllocationId' => $penIssue, 'saleLineId' => $penLine, 'status' => 'KNOWN']]], self::dataOf($event['owner_only_data']));

        // The result holds references only; the receipt of the request is settled.
        $stored = (array) $this->watcher()->table('command_results')->where('command_id', $commandId)->sole();
        $this->assertSame(['sale_correction', $correction], [$stored['result_type'], $stored['result_ref']]);
        $this->assertSame(['COMMITTED'], $this->outcomesOf($envelope));

        // The same request again answers the original and writes nothing; a status check rebuilds it.
        $facts = $this->factsAndRows();
        $this->correct($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('commandId', $commandId)->assertJsonPath('result.id', $correction);
        $this->assertSame($facts, $this->factsAndRows());
        $resolved = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.location', '/sales/'.$sale.'/corrections/'.$correction);
        $this->assertJsonChannel($resolved, 'commands.resolve', 'owner');
    }

    public function test_only_the_owner_corrects_and_every_other_role_and_the_combined_account_is_refused(): void
    {
        // AC-033-01; BR-RFD-01, D-035; CAP-24; THR-19: a cashier refunding to herself, Operations and the
        // combined account are refused at the gate on the server, before any receipt or fact; the Owner
        // with a reason and recent authentication corrects.
        TokoContoh::stock($this->pen, '10', '50000');
        $sale = TokoContoh::sell('Rina', $this->session, [self::sold($this->pen, '2')]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $before = $this->factsAndRows();

        foreach (['Rina', 'Agus', 'Dewi'] as $person) {
            $this->as($person);
            $this->correct($this->envelopeOf($sale, [self::line($line, '7500')], self::transfer('TRF-1')))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        }

        $this->assertSame($before, $this->factsAndRows());
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', RecordCorrection::OPERATION)->count());

        // A reason is required, 1 to 1,000 characters without a control character (BR-RFD-12; TR-VAL-05).
        $this->as('Hendra');
        $this->confirm();

        foreach (['', str_repeat('r', 1001)] as $reason) {
            $this->correct($this->envelopeOf($sale, [self::line($line, '7500')], self::transfer('TRF-1'), $reason))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')->assertJsonPath('fields', ['payload.reason' => [['key' => $reason === '' ? 'ux.validation.required' : 'ux.validation.text_max', 'params' => $reason === '' ? [] : ['maks' => 1000]]]]);
        }

        $this->correct($this->envelopeOf($sale, [self::line($line, '7500')], self::transfer('TRF-1'), "Retur\u{0007}"))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame($before, $this->factsAndRows());

        $this->correct($this->envelopeOf($sale, [self::line($line, '7500')], self::transfer('TRF-1')))->assertStatus(201);
        $this->assertSame(1, $this->watcher()->table('sale_corrections')->count());
    }

    public function test_the_money_and_quantity_bounds_are_separate_and_cumulative(): void
    {
        // AC-033-02; BR-RFD-03, BR-RFD-04, BR-PAY-02; SCH-33; G-06; UXS-35. A CASH Sale of Rp75.000 paid
        // with Rp100.000, ten pens: never more than Rp75.000 refunded, never the tendered cash; a partial
        // refund of the line stays within its bound; the goods come back after their money with no more
        // money; and nothing more than ten comes back.
        TokoContoh::stock($this->pen, '20', '100000');
        $sale = TokoContoh::sell('Rina', $this->session, [self::sold($this->pen, '10')], ['method' => 'CASH', 'tendered' => '100000']);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $this->as('Hendra');
        $this->confirm();
        $before = $this->factsAndRows();

        $refused = $this->correct($this->envelopeOf($sale, [self::line($line, '75001')], self::transfer('TRF-1')))->assertStatus(422)->assertJsonPath('code', 'REFUND_BOUND_EXCEEDED')->assertJsonPath('messageKey', 'ux.state.refund_bound.money');
        $this->assertJsonChannel($refused, 'commands.refused.refund_bound', 'owner');
        $this->assertSame([['uang' => 'Rp75.000'], [0 => ['saleLineId' => $line, 'remainingAmount' => '75000', 'remainingQuantity' => '10']]], [$refused->json('params'), $refused->json('lines')]);
        $this->correct($this->envelopeOf($sale, [self::line($line, '100000')], self::transfer('TRF-1')))->assertStatus(422)->assertJsonPath('code', 'REFUND_BOUND_EXCEEDED');
        $this->assertSame($before, $this->factsAndRows());

        // Rp30.000 of the line, then at most the Rp45.000 that remains: Rp45.001 is refused, Rp45.000 is not.
        $this->correct($this->envelopeOf($sale, [self::line($line, '30000')], self::transfer('TRF-1')))->assertStatus(201);
        $this->correct($this->envelopeOf($sale, [self::line($line, '45001')], self::transfer('TRF-2')))->assertStatus(422)->assertJsonPath('code', 'REFUND_BOUND_EXCEEDED')->assertJsonPath('params.uang', 'Rp45.000')->assertJsonPath('lines.0.remainingAmount', '45000');
        $this->correct($this->envelopeOf($sale, [self::line($line, '45000')], ['method' => 'CASH', 'reference' => 'Tunai dari Pemilik']))->assertStatus(201);
        $this->correct($this->envelopeOf($sale, [self::line($line, '1')], self::transfer('TRF-3')))->assertStatus(422)->assertJsonPath('code', 'REFUND_BOUND_EXCEEDED')->assertJsonPath('params.uang', 'Rp0');
        $this->assertSame([75000, 75000], array_values((array) $this->watcher()->table('sale_correction_totals')->where('sale_id', $sale)->sole(['sale_total', 'refunded_amount'])));

        // The quantity bound is separate: the ten pens come back after their money, with no money; a
        // return of more than remains is refused at its line, saleable and damaged counted together.
        $goods = $this->correct($this->envelopeOf($sale, [self::line($line, '0', '6', '5')]))->assertStatus(422)->assertJsonPath('code', 'RETURN_BOUND_EXCEEDED')->assertJsonPath('messageKey', 'ux.state.refund_bound.goods');
        $this->assertJsonChannel($goods, 'commands.refused.return_bound', 'owner');
        $this->assertSame([['jumlah' => '10 pcs'], [0 => ['saleLineId' => $line, 'remainingAmount' => '0', 'remainingQuantity' => '10']]], [$goods->json('params'), $goods->json('lines')]);
        $this->correct($this->envelopeOf($sale, [self::line($line, '0', '6', '4')]))->assertStatus(201);
        $this->correct($this->envelopeOf($sale, [self::line($line, '0', '1')]))->assertStatus(422)->assertJsonPath('code', 'RETURN_BOUND_EXCEEDED')->assertJsonPath('lines.0.remainingQuantity', '0');
        $this->assertSame([[$line, 75000, '10.000', 75000, '10.000']], $this->rowsOf('sale_line_correction_totals', ['sale_line_id', 'line_total', 'issued_quantity', 'refunded_amount', 'returned_quantity']));
        $this->assertSame([3, 2, ['6.000', '4.000']], [$this->watcher()->table('sale_corrections')->count(), $this->watcher()->table('refunds')->count(), [$this->watcher()->table('return_lines')->where('condition', 'SALEABLE')->value('quantity'), $this->watcher()->table('return_lines')->where('condition', 'NOT_SALEABLE')->value('quantity')]]);
    }

    public function test_a_refund_without_return_adds_no_stock_and_a_return_without_money_has_no_payout(): void
    {
        // AC-033-03, AC-033-04; BR-RFD-04, BR-RFD-11; BR-COST-17, BR-COST-19: a refund paid from the Owner's
        // own funds adds no stock and restores no cost — the issue's projection stays cost of goods sold; a
        // damaged return, without money, adds no saleable stock and restores no cost; neither touches a
        // session (D-035).
        TokoContoh::stock($this->pen, '10', '50000');
        $sale = TokoContoh::sell('Rina', $this->session, [self::sold($this->pen, '4')]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $kept = $this->saleRows($sale);
        $this->as('Hendra');
        $this->confirm();

        $this->correct($this->envelopeOf($sale, [self::line($line, '15000')], ['method' => 'CASH', 'reference' => 'Tunai dari Pemilik']))->assertStatus(201);
        $this->assertSame([15000, 'CASH', 'OWNER_FUNDS', null, 'Tunai dari Pemilik'], array_values((array) $this->watcher()->table('refunds')->sole(['amount', 'payout_method', 'funding_source', 'cash_session_id', 'payout_reference'])));

        $this->correct($this->envelopeOf($sale, [self::line($line, '0', '0', '2')]))->assertStatus(201);
        $this->assertSame([1, 2, 0, 0], [$this->watcher()->table('refunds')->count(), $this->watcher()->table('sale_corrections')->count(), $this->watcher()->table('stock_movements')->where('movement_type', 'RETURN_RECEIPT')->count(), $this->watcher()->table('return_cost_allocations')->count()]);
        $this->assertSame([['6.000', 2], 'KNOWN|30000.000000|2|2'], [$this->balanceOf($this->pen), $this->valuationOf($this->pen)]);
        $this->assertSame([[$line, 30000, '4.000', 15000, '2.000']], $this->rowsOf('sale_line_correction_totals', ['sale_line_id', 'line_total', 'issued_quantity', 'refunded_amount', 'returned_quantity']));
        $this->assertSame($kept, $this->saleRows($sale));
    }

    public function test_onboarding_a_block_and_an_inactive_product_refuse_a_saleable_return_and_nothing_else(): void
    {
        // RC-06 under the locks; G-10, G-11; BR-INV-11, BR-INV-24, BR-INV-45: a saleable return needs
        // onboarding off (ONBOARDING_FREEZE), its product not blocked (POSTING_BLOCKED) and active to receive
        // saleable stock (PRODUCT_INACTIVE), after the bounds; a refund and a damaged return post nothing and
        // need none of them.
        TokoContoh::stock($this->pen, '10', '50000');
        $sale = TokoContoh::sell('Rina', $this->session, [self::sold($this->pen, '5')]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $this->as('Hendra');
        $this->confirm();
        $saleable = $this->envelopeOf($sale, [self::line($line, '0', '1')]);

        $this->runtime()->table('onboarding_state')->where('id', 1)->update(['state' => 'ACTIVE', 'version' => 2]);
        $before = $this->factsAndRows();
        $this->correct($saleable)->assertStatus(422)->assertJsonPath('code', 'ONBOARDING_FREEZE');
        // The bounds come first: a return beyond the line is RETURN_BOUND_EXCEEDED even while frozen.
        $this->correct($this->envelopeOf($sale, [self::line($line, '0', '6')]))->assertStatus(422)->assertJsonPath('code', 'RETURN_BOUND_EXCEEDED');
        $this->assertSame($before, $this->factsAndRows());
        $this->correct($this->envelopeOf($sale, [self::line($line, '7500', '0', '1')], self::transfer('TRF-1')))->assertStatus(201);
        $this->runtime()->table('onboarding_state')->where('id', 1)->update(['state' => 'NOT_STARTED', 'version' => 3]);

        $block = TokoContoh::postingBlock($this->pen);
        $before = $this->factsAndRows();
        $blocked = $this->correct($this->envelopeOf($sale, [self::line($line, '0', '1')]))->assertStatus(422)->assertJsonPath('code', 'POSTING_BLOCKED');
        $this->assertJsonChannel($blocked, 'commands.refused.lines', 'owner');
        $this->assertSame([0 => ['productId' => $this->pen]], $blocked->json('lines'));
        $this->assertSame($before, $this->factsAndRows());
        $this->correct($this->envelopeOf($sale, [self::line($line, '0', '0', '1')]))->assertStatus(201);
        TokoContoh::reopenPostings($block);

        $this->postJson('/commands/product.deactivate', $this->envelope(['productId' => $this->pen], ['operation' => 'product.deactivate', 'expected' => ['productVersion' => self::number($this->watcher()->table('products')->where('id', $this->pen)->value('version'))]]))->assertStatus(201);
        $before = $this->factsAndRows();
        $this->correct($this->envelopeOf($sale, [self::line($line, '0', '1')]))->assertStatus(422)->assertJsonPath('code', 'PRODUCT_INACTIVE')->assertJsonPath('lines.0.productId', $this->pen);
        $this->assertSame($before, $this->factsAndRows());
        $this->correct($this->envelopeOf($sale, [self::line($line, '7500')], self::transfer('TRF-2')))->assertStatus(201);
        $this->assertSame([0, 3], [$this->watcher()->table('stock_movements')->where('movement_type', 'RETURN_RECEIPT')->count(), $this->watcher()->table('sale_corrections')->count()]);
    }

    public function test_each_refusal_before_any_lock_writes_nothing(): void
    {
        // RC-06, "Before locks"; TR-VAL-01 to TR-VAL-07; COPY_DECK section 16.1: the envelope's allowlist,
        // a Sale line named twice, a line of another Sale, nothing to correct, money without a payout or a
        // payout without money, the amounts' forms, a quantity beyond its product's precision; a Sale that
        // does not exist is NOT_FOUND. Each writes nothing.
        TokoContoh::stock($this->pen, '10', '50000');
        TokoContoh::stock($this->rice, '5', '30000');
        $sale = TokoContoh::sell('Rina', $this->session, [self::sold($this->pen, '2'), self::sold($this->rice, '1.5')]);
        [$penLine, $riceLine] = TokoContoh::saleLinesOf($sale);
        $otherLine = TokoContoh::saleLinesOf(TokoContoh::sell('Rina', $this->session, [self::sold($this->pen, '1')]))[0] ?? 0;
        $this->as('Hendra');
        $this->confirm();
        $before = $this->factsAndRows();
        $valid = $this->envelopeOf($sale, [self::line($penLine, '7500')], self::transfer('TRF-1'));

        // The envelope: exactly its fields; no expected version; no field of RC-05's payout.
        foreach ([['version' => 2], ['tambahan' => 'x'], ['operation' => 'sale.checkout'], ['expected' => ['lines' => [['priceRevisionId' => 1]]]], ['payload' => [...self::payloadOf($valid), 'payout' => [...self::transfer('TRF-1'), 'sessionId' => $this->session]]], ['payload' => [...self::payloadOf($valid), 'total' => '7500']]] as $changes) {
            $this->correct([...$valid, ...$changes])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $repeated = $this->correct($this->envelopeOf($sale, [self::line($penLine, '1000'), self::line($penLine, '0', '1')], self::transfer('TRF-1')))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame([[], [1 => ['saleLineId' => $penLine]]], [$repeated->json('fields'), $repeated->json('lines')]);

        foreach ([
            [[], self::transfer('TRF-1'), 'payload.lines', 'ux.validation.lines_min', []],
            [[self::line($penLine), self::line($riceLine)], null, 'payload.lines', 'ux.validation.lines_min', []],
            [[self::line($penLine, '7500')], null, 'payload.payout', 'ux.validation.required', []],
            [[self::line($penLine, '7500')], ['method' => 'QRIS', 'reference' => 'TRF-1'], 'payload.payout.method', 'ux.validation.choose_one', []],
            [[self::line($penLine, '7500')], ['method' => 'BANK_TRANSFER', 'reference' => str_repeat('r', 101)], 'payload.payout.reference', 'ux.validation.text_max', ['maks' => 100]],
            [[self::line($penLine, '-1')], self::transfer('TRF-1'), 'payload.lines.0.refundAmount', 'ux.validation.money_min', []],
            [[self::line($penLine, '1.5')], self::transfer('TRF-1'), 'payload.lines.0.refundAmount', 'ux.validation.money', []],
            [[self::line($penLine, '0', '-1')], null, 'payload.lines.0.returnedSaleable', 'ux.validation.quantity_positive', []],
            [[self::line($riceLine, '0', '0', '0.0001')], null, 'payload.lines.0.returnedNotSaleable', 'ux.validation.quantity_decimals', ['desimal' => '3']],
            // The Action, from facts and before any lock: a line of another Sale; a pen is counted whole.
            [[self::line($otherLine, '0', '1')], null, 'payload.lines.0.saleLineId', 'ux.validation.choose_one', []],
            [[self::line($riceLine, '0', '0.5'), self::line($penLine, '0', '0.5')], null, 'payload.lines.1.returnedSaleable', 'ux.validation.quantity_whole', []],
        ] as [$lines, $payout, $field, $key, $params]) {
            $this->correct($this->envelopeOf($sale, $lines, $payout))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')->assertJsonPath('fields', [$field => [['key' => $key, 'params' => $params]]]);
        }

        // A payout without money is refused, and so is an identifier that is not a JSON whole number.
        $this->correct($this->envelopeOf($sale, [self::line($penLine, '0', '1')], self::transfer('TRF-1')))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')->assertJsonPath('fields', []);
        $this->correct($this->envelopeOf($sale, [[...self::line($penLine, '7500'), 'saleLineId' => (string) $penLine]], self::transfer('TRF-1')))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->correct($this->envelopeOf(999999, [self::line($penLine, '7500')], self::transfer('TRF-1')))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        $this->assertSame($before, $this->factsAndRows());
    }

    public function test_an_envelope_is_refused_as_every_command_refuses_one_and_a_repeat_or_a_status_check_answers_the_correction(): void
    {
        // CMD01; G-12, G-25; RC-50; ADR-007 section 3; RACE-01 and RACE-02 in sequence (their races are
        // CorrectionRaceTest's): an earlier epoch is held; the command runs once; a repeat with the same or
        // another key of the intent answers the original; another payload under either is held.
        TokoContoh::stock($this->pen, '10', '50000');
        $sale = TokoContoh::sell('Rina', $this->session, [self::sold($this->pen, '4')]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $this->as('Hendra');
        $this->confirm();
        $envelope = $this->envelopeOf($sale, [self::line($line, '7500', '1')], self::transfer('TRF-1'));
        $before = $this->factsAndRows();

        $this->correct([...$envelope, 'sourceIntentId' => (string) Str::uuid(), 'recoveryEpoch' => str_repeat('ab', 32)])->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE')->assertJsonPath('outcome', 'HELD');
        $this->assertSame($before, $this->factsAndRows());

        $committed = $this->correct($envelope)->assertStatus(201);
        $correction = self::number($committed->json('result.id'));
        $facts = $this->factsAndRows();
        $other = $this->envelopeOf($sale, [self::line($line, '7500', '2')], self::transfer('TRF-1'));

        $this->correct($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $correction);
        $this->correct([...$envelope, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $correction);

        foreach ([[...$envelope, 'payload' => self::payloadOf($other)], [...$envelope, 'idempotencyKey' => (string) Str::uuid(), 'payload' => self::payloadOf($other)]] as $conflicting) {
            $this->correct($conflicting)->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT')->assertJsonPath('outcome', 'HELD');
        }

        $this->assertSame($facts, $this->factsAndRows());
        $this->assertSame([[$line, 30000, '4.000', 7500, '1.000']], $this->rowsOf('sale_line_correction_totals', ['sale_line_id', 'line_total', 'issued_quantity', 'refunded_amount', 'returned_quantity']));
        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.id', $correction);

        // Another user's intent is not theirs to check (CAP-34, RC-50).
        $this->as('Rina');
        $this->check($envelope)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_a_failure_after_every_write_step_leaves_nothing(): void
    {
        // AC-033-05; TS-21; BR-RFD-10: a refund on two lines with a saleable and a damaged return writes 15
        // steps — the correction, the refund, its two lines, the two return lines, the Sale's running total,
        // the two lines' running totals, the pen's balance and valuation, its RETURN_RECEIPT, its return
        // allocation, the audit event and the result. A failure after each leaves nothing.
        TokoContoh::stock($this->pen, '10', '50000');
        TokoContoh::stock($this->rice, '5', '30000');
        $sale = TokoContoh::sell('Rina', $this->session, [self::sold($this->pen, '2'), self::sold($this->rice, '1.5')]);
        [$penLine, $riceLine] = TokoContoh::saleLinesOf($sale);
        $lines = [self::line($penLine, '7500', '1'), self::line($riceLine, '6000', '0', '0.5')];
        $this->as('Hendra');
        $this->confirm();

        for ($step = 1; $step <= 15; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $before = $this->factsAndRows();

            $this->correct($this->envelopeOf($sale, $lines, self::transfer('TRF-1')))->assertStatus(500);

            $this->assertSame($before, $this->factsAndRows(), sprintf('after write step %d', $step));
            $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', RecordCorrection::OPERATION)->orderByDesc('id')->value('outcome'), (string) $step);
        }

        // The step after the last is no step of the command: it commits, through the locks of TR §7.1 in
        // their order — no session's (D-035).
        $points = new FailurePoints(16);
        $this->app->instance(CommandPoints::class, $points);
        $this->correct($this->envelopeOf($sale, $lines, self::transfer('TRF-1')))->assertStatus(201);
        $this->assertSame(['after_lock:2.1', 'after_lock:2.3', 'after_lock:5', 'after_lock:6', 'after_lock:7', ...array_map(static fn (int $step): string => 'after_write:'.$step, range(1, 15))], array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))));
        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->assertSame([1, ['9.000', 3]], [$this->watcher()->table('sale_corrections')->count(), $this->balanceOf($this->pen)]);
    }

    public function test_a_correction_after_its_session_closed_leaves_the_session_and_its_report_unchanged(): void
    {
        // AC-033-05; BR-RFD-12, BR-CASH-19, BR-RPT-03; D-035: a later correction is a linked fact and never
        // rewrites a closed session or its report; an Owner-funded refund changes no session's expected cash.
        TokoContoh::stock($this->pen, '10', '50000');
        $sale = TokoContoh::sell('Rina', $this->session, [self::sold($this->pen, '3')]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        TokoContoh::closeSession($this->session);
        $kept = $this->saleRows($sale);
        $this->assertSame(1, count($kept['cash_session_reports']));
        $this->as('Hendra');
        $this->confirm();

        $this->correct($this->envelopeOf($sale, [self::line($line, '22500', '3')], ['method' => 'CASH', 'reference' => 'Tunai dari Pemilik']))->assertStatus(201);

        $this->assertSame($kept, $this->saleRows($sale));
        $this->assertSame([['10.000', 3], 22500], [$this->balanceOf($this->pen), $this->watcher()->table('sale_correction_totals')->where('sale_id', $sale)->value('refunded_amount')]);
    }

    public function test_each_new_table_has_its_one_writer(): void
    {
        // STK-12, ARC-20, ARC-21; ADR-008 section 4.3: the Sales module's writer of corrections alone names
        // the correction's tables, and changes the running totals by relative increments only (SCH-33);
        // the Costing module's writer alone writes the return allocations, which the replay reads.
        foreach (['sale_corrections', 'refunds', 'refund_lines', 'return_lines'] as $table) {
            $this->assertSame(['Domain/Sales/Corrections/SaleCorrections.php'], self::filesNaming($table), $table);
        }

        foreach (['return_cost_allocations', 'return_cost_allocation_projections'] as $table) {
            $this->assertSame(['Domain/Costing/Allocations/ReturnAllocations.php', 'Domain/Costing/Replay/ReplayLedger.php'], self::filesNaming($table), $table);
        }

        $writer = (string) file_get_contents(app_path('Domain/Sales/Corrections/SaleCorrections.php'));
        $this->assertDoesNotMatchRegularExpression('/->(?:update|upsert|delete|increment|decrement|truncate)\(|DB::(?:delete|statement)\(/', $writer);
        $this->assertSame(2, preg_match_all('/set refunded_amount = refunded_amount \+ \?::bigint/', $writer));
        $this->assertSame(1, preg_match_all('/returned_quantity = returned_quantity \+ \?::numeric/', $writer));
        $this->assertDoesNotMatchRegularExpression('/->(?:insert|insertGetId|update|upsert|delete)\(|DB::(?:insert|update|delete|statement)\(/', (string) file_get_contents(app_path('Domain/Costing/Replay/ReplayLedger.php')));
    }

    /**
     * The payload of an envelope.
     *
     * @param  array<string, mixed>  $envelope
     * @return array<array-key, mixed>
     */
    private static function payloadOf(array $envelope): array
    {
        $payload = $envelope['payload'] ?? null;

        return is_array($payload) ? $payload : [];
    }

    /**
     * A line of RC-01.
     *
     * @return array{productId: int, quantity: string}
     */
    private static function sold(int $product, string $quantity): array
    {
        return ['productId' => $product, 'quantity' => $quantity];
    }

    /**
     * A line of RC-06: the money refunded on a Sale line and the quantities returned, saleable and not.
     *
     * @return array{saleLineId: int, refundAmount: string, returnedSaleable: string, returnedNotSaleable: string}
     */
    private static function line(int $saleLine, string $refund = '0', string $saleable = '0', string $notSaleable = '0'): array
    {
        return ['saleLineId' => $saleLine, 'refundAmount' => $refund, 'returnedSaleable' => $saleable, 'returnedNotSaleable' => $notSaleable];
    }

    /**
     * A payout made by bank transfer, with its reference.
     *
     * @return array{method: string, reference: string}
     */
    private static function transfer(string $reference): array
    {
        return ['method' => 'BANK_TRANSFER', 'reference' => $reference];
    }

    /**
     * An envelope of RC-06 for these lines of this Sale, with this payout or none.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>|null  $payout
     * @return array<string, mixed>
     */
    private function envelopeOf(int $sale, array $lines, ?array $payout = null, string $reason = self::REASON): array
    {
        return $this->envelope(['saleId' => $sale, 'lines' => $lines, 'reason' => $reason, ...($payout === null ? [] : ['payout' => $payout])], ['operation' => RecordCorrection::OPERATION]);
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
     * The Owner's recent authentication (RA-03; SEC-SESS-05).
     */
    private function confirm(): void
    {
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
    }

    /**
     * The SALE_ISSUE allocation of a Sale line.
     */
    private function issueOf(int $line): int
    {
        return self::number($this->watcher()->table('issue_cost_allocations')->where('sale_line_id', $line)->value('id'));
    }

    /**
     * The rows of a table in key order, with these columns.
     *
     * @param  list<string>  $columns
     * @return list<list<mixed>>
     */
    private function rowsOf(string $table, array $columns): array
    {
        return array_values(array_map(static fn (object $row): array => array_values((array) $row), $this->watcher()->table($table)->orderBy($columns[0])->get($columns)->all()));
    }

    /**
     * A product's balance: on hand and the last sequence.
     *
     * @return list<mixed>
     */
    private function balanceOf(int $product): array
    {
        return array_values((array) $this->watcher()->table('stock_balances')->where('product_id', $product)->first(['on_hand', 'last_sequence']));
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
     * Every row of a Sale and its records, its session, and every issue allocation with its projection,
     * which no correction changes (BR-RFD-12; D-035).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function saleRows(int $sale): array
    {
        $rows = [];

        foreach (['sales' => 'id', 'sale_lines' => 'id', 'payments' => 'id', 'receipt_snapshots' => 'id', 'cash_sessions' => 'id', 'cash_session_closes' => 'session_id', 'cash_session_reports' => 'session_id', 'issue_cost_allocations' => 'id', 'issue_cost_allocation_projections' => 'allocation_id'] as $table => $key) {
            $query = $this->watcher()->table($table)->orderBy($key);

            if (in_array($table, ['sales', 'payments', 'receipt_snapshots'], true)) {
                $query->where($table === 'sales' ? 'id' : 'sale_id', $sale);
            }

            $rows[$table] = array_values($query->get()->map(static fn (object $row): array => (array) $row)->all());
        }

        return $rows;
    }

    /**
     * How many rows each table of a correction holds, with the rows that a correction changes: the
     * balances, the valuations, the running totals and the sessions.
     *
     * @return array<string, mixed>
     */
    private function factsAndRows(): array
    {
        $counts = [];

        foreach (self::FACTS as $table) {
            $counts[$table] = $this->watcher()->table($table)->count();
        }

        $rows = [];

        foreach (['stock_balances' => 'product_id', 'inventory_valuations' => 'product_id', 'sale_correction_totals' => 'sale_id', 'sale_line_correction_totals' => 'sale_line_id', 'cash_sessions' => 'id'] as $table => $key) {
            $rows[$table] = array_values($this->watcher()->table($table)->orderBy($key)->get()->map(static fn (object $row): array => (array) $row)->all());
        }

        return ['facts' => $counts, 'rows' => $rows];
    }

    /**
     * An instant read from the database, in UTC with its microseconds.
     */
    private static function instant(mixed $value): string
    {
        return CarbonImmutable::parse(self::text($value))->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * Data of jsonb with its keys sorted at every level, since jsonb keeps no order of its own.
     *
     * @return array<array-key, mixed>
     */
    private static function dataOf(mixed $json): array
    {
        $data = json_decode(is_string($json) ? $json : '', true);
        self::assertIsArray($data);

        return self::sortedKeys($data);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function sortedKeys(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }

        return array_map(static fn (mixed $value): mixed => is_array($value) ? self::sortedKeys($value) : $value, $data);
    }

    /**
     * The files of app/ that name this table in a string, as the query builder takes it; a mention in a
     * comment is no access.
     *
     * @return list<string>
     */
    private static function filesNaming(string $table): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match('/[\'"]'.preg_quote($table, '/').'(?: as [a-z]+)?[\'"]/', (string) file_get_contents($file->getPathname())) === 1) {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen(app_path()) + 1));
            }
        }

        sort($files);

        return $files;
    }

    /** One independent session for every read of the test, so that the test stays within the server's connections. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
