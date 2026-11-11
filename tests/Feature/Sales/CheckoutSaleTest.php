<?php

namespace Tests\Feature\Sales;

use App\Actions\Commands\CommandContext;
use App\Actions\Sales\CheckoutSale;
use App\Database\CommandPoints;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Operations;
use App\Domain\Sales\PaymentMethods;
use App\Support\Time\Clock;
use Carbon\CarbonImmutable;
use FilesystemIterator;
use Illuminate\Database\Connection;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\Commands\DeadlockPoints;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-01 `sale.checkout` through its route and its Action (AC-028-01 to AC-028-04, AC-028-06,
 * AC-028-08; INTERACTION_CONTRACTS RC-01 and sections 1.1 to 1.6; ROUTE_CONTRACTS RC-01; TECHNICAL_RULES
 * sections 7.1, 7.2, 9 and 11; SCHEMA sections 5.9 and 6; SALES_CASH BR-SALE-01 to BR-SALE-23,
 * BR-PAY-01 to BR-PAY-07, BR-DOC-01, BR-CASH-10; ARCHITECTURE ARC-30 to ARC-33; D-036, D-040, D-062,
 * D-067, D-077; G-01, G-02, G-04, G-10, G-11, G-13, G-14, G-16 to G-18; CAP-21, CAP-22; EXECUTION_CONTEXT
 * section 10, a command; suites POS01, POS02, POS03, PAYEXT01, CMD01, AUTH01, AUTH02; TASK-028 section
 * 7, L2 and L4): the commit with every record in one transaction; the roles in their own sessions and
 * Operations never; the payment arithmetic and every method outside the list; each definite rejection
 * in the order of RC-01, which writes nothing and takes no number; a product named twice and more than
 * 200 lines refused before any lock, 200 products sold; the envelope, a repeat and a status check, also
 * after the session closed; the failure after every write step and the gap it leaves; a deadlock
 * under the bounded retry; the till's search of RD-01, which needs the actor's own OPEN session; the
 * payment boundary of PAYEXT01 by review; and the one writer of the Sale's tables.
 */
final class CheckoutSaleTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string COMMAND = '/commands/sale.checkout';

    /** The tables that a refused or failed checkout leaves as they were. */
    private const array FACTS = ['sales', 'sale_lines', 'payments', 'receipt_snapshots', 'sale_correction_totals', 'stock_movements', 'issue_cost_allocations', 'issue_cost_allocation_projections', 'command_identities', 'command_results', 'audit_events'];

    private int $category = 0;

    /** Counted whole: `pcs`, precision 0, Rp7.500. */
    private int $pen = 0;

    /** Weighed: `kg`, precision 3, Rp12.000. */
    private int $rice = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category();
        $this->pen = TokoContoh::product('PEN-01', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam', 'sellingPrice' => '7500']);
        $this->rice = TokoContoh::product('BERAS-01', ['categoryId' => $this->category, 'name' => 'Beras', 'baseUnit' => 'kg', 'quantityPrecision' => 3, 'sellingPrice' => '12000']);
    }

    public function test_a_cashier_sells_in_her_own_session_with_every_record_in_one_transaction(): void
    {
        // AC-028-01, AC-028-02, AC-028-05; BR-SALE-05 to BR-SALE-09, BR-SALE-17, BR-PAY-02, BR-DOC-01,
        // BR-CASH-10; ADR-008 sections 2 and 4.2; G-01, G-13, G-14, G-16 to G-18. 10:30 in Asia/Jakarta
        // on 7 November: Rina sells two pens and 1,5 kg of rice for Rp33.000, paid with Rp50.000.
        $this->travelTo('2026-11-07T03:30:00.250000Z');
        TokoContoh::stock($this->pen, '10', '50000');
        TokoContoh::stock($this->rice, '5', '30000');
        $session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $sessionNumber = self::text($this->watcher()->table('cash_sessions')->where('id', $session)->value('session_number'));
        $this->as('Rina');
        $rina = $this->people['Rina']['id'];
        $envelope = $this->envelopeOf($session, [self::line($this->pen, '2'), self::line($this->rice, '1.5')], [...self::cash('50000'), 'customerName' => 'Bu Sari']);

        $response = $this->checkout($envelope)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed.number', 'cashier');
        $sale = self::number($response->json('result.id'));
        $commandId = self::number($response->json('commandId'));
        $row = (array) $this->watcher()->table('sales')->where('id', $sale)->sole();
        $counter = self::number($row['sale_number_seq']);
        $number = sprintf('20261107-%06d', $counter);
        // Its location is the Sale's receipt (RP-103; TASK-029).
        $this->assertSame(['sale', $number, '/sales/'.$sale.'/receipt'], [$response->json('result.type'), $response->json('result.number'), $response->json('result.location')]);

        // The Sale: its number by its business date and counter, its session of its actor, its total
        // the sum of its line totals, its cashier's name frozen (G-01, G-16, G-18).
        $this->assertSame(
            [$number, '2026-11-07', $session, $rina, 'Rina', 33000, 2, 'Bu Sari', $commandId],
            [$row['sale_number'], $row['business_date'], $row['cash_session_id'], $row['actor_user_id'], $row['actor_display_name'], $row['total_amount'], $row['line_count'], $row['customer_name'], $row['command_id']],
        );
        $this->assertSame(['2026-11-07T03:30:00.250000', '2026-11-07'], array_values((array) $this->watcher()->selectOne(
            "select to_char(committed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US') as utc, ((committed_at at time zone 'Asia/Jakarta')::date)::text as jakarta from sales where id = ?",
            [$sale],
        )));

        // One line per product, at the revision reviewed, its total half-up once (BR-SALE-05, BR-SALE-06).
        $lines = $this->watcher()->table('sale_lines')->where('sale_id', $sale)->orderBy('line_no')->get(['id', 'line_no', 'product_id', 'selling_price_revision_id', 'unit_price', 'quantity', 'line_total'])->map(static fn (object $line): array => (array) $line)->all();
        $lineIds = array_values(array_map(static fn (array $line): int => self::number($line['id']), $lines));
        $this->assertSame([
            [1, $this->pen, $this->revisionOf($this->pen), 7500, '2.000', 15000],
            [2, $this->rice, $this->revisionOf($this->rice), 12000, '1.500', 18000],
        ], array_values(array_map(static fn (array $line): array => array_values(array_diff_key($line, ['id' => true])), $lines)));

        // One Payment: CASH, the total applied, the change given (BR-PAY-02).
        $this->assertSame(['CASH', 33000, 50000, 17000, null], array_values((array) $this->watcher()->table('payments')->where('sale_id', $sale)->sole(['method', 'applied_amount', 'tendered_amount', 'change_amount', 'reference'])));

        // The receipt: the Sale's number, total, session and cashier; the revision in force by
        // reference; the template; every line as frozen strings; no cost (BR-DOC-01, G-17).
        $receipt = (array) $this->watcher()->table('receipt_snapshots')->where('sale_id', $sale)->sole();
        $this->assertSame(
            [$number, 33000, $session, 'Rina', TokoContoh::shop(), null, 'receipt.1', $sessionNumber, 'CASH', 50000, 17000, 'Bu Sari'],
            [$receipt['sale_number'], $receipt['total_amount'], $receipt['cash_session_id'], $receipt['cashier_display_name'], $receipt['business_profile_revision_id'], $receipt['logo_file_id'], $receipt['template_version'], $receipt['cash_session_number'], $receipt['payment_method'], $receipt['tendered_amount'], $receipt['change_amount'], $receipt['customer_name']],
        );
        $this->assertSame([
            ['lineNo' => '1', 'lineTotal' => '15000', 'name' => 'Pulpen Hitam', 'quantity' => '2', 'sku' => 'PEN-01', 'unit' => 'pcs', 'unitPrice' => '7500'],
            ['lineNo' => '2', 'lineTotal' => '18000', 'name' => 'Beras', 'quantity' => '1.5', 'sku' => 'BERAS-01', 'unit' => 'kg', 'unitPrice' => '12000'],
        ], self::dataOf($receipt['lines']));
        $this->assertSame([33000, 0], array_values((array) $this->watcher()->table('sale_correction_totals')->where('sale_id', $sale)->sole(['sale_total', 'refunded_amount'])));

        // One SALE_ISSUE per line, its line's product and quantity, in its product's sequence; the
        // balances (BR-SALE-09, G-03).
        $movements = $this->watcher()->table('stock_movements')->where('command_id', $commandId)->orderBy('id')->get(['id', 'product_id', 'product_sequence', 'movement_type', 'quantity', 'sale_line_id', 'line_quantity', 'recorded_at'])->map(static fn (object $movement): array => (array) $movement)->all();
        $movementIds = array_values(array_map(static fn (array $movement): int => self::number($movement['id']), $movements));
        $this->assertSame([
            [$this->pen, 2, 'SALE_ISSUE', '-2.000', $lineIds[0], '2.000'],
            [$this->rice, 2, 'SALE_ISSUE', '-1.500', $lineIds[1], '1.500'],
        ], array_values(array_map(static fn (array $movement): array => array_values(array_diff_key($movement, ['id' => true, 'recorded_at' => true])), $movements)));
        $this->assertSame(['8.000', 2], $this->balanceOf($this->pen));
        $this->assertSame(['3.500', 2], $this->balanceOf($this->rice));

        // Each line's allocation, at the average before it, its projection equal (BR-COST-16; G-13,
        // G-14): Rp50.000 over 10 pens, two take Rp10.000; Rp30.000 over 5 kg, 1,5 kg take Rp9.000.
        $allocations = $this->watcher()->table('issue_cost_allocations as a')->join('issue_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->whereIn('a.movement_id', $movementIds)->orderBy('a.id')
            ->get(['a.id', 'a.movement_id', 'a.issue_type', 'a.issued_quantity', 'a.sale_line_id', 'a.at_commit_status', 'a.at_commit_amount', 'a.based_on_publication_no', 'p.current_status', 'p.current_amount', 'p.publication_no'])
            ->map(static fn (object $allocation): array => (array) $allocation)->all();
        $allocationIds = array_values(array_map(static fn (array $allocation): int => self::number($allocation['id']), $allocations));
        $this->assertSame([
            [$movementIds[0], 'SALE_ISSUE', '2.000', $lineIds[0], 'KNOWN', '10000.000000', 1, 'KNOWN', '10000.000000', 2],
            [$movementIds[1], 'SALE_ISSUE', '1.500', $lineIds[1], 'KNOWN', '9000.000000', 1, 'KNOWN', '9000.000000', 2],
        ], array_values(array_map(static fn (array $allocation): array => array_values(array_diff_key($allocation, ['id' => true])), $allocations)));
        $this->assertSame('KNOWN|40000.000000|2|2', $this->valuationOf($this->pen));
        $this->assertSame('KNOWN|21000.000000|2|2', $this->valuationOf($this->rice));

        // BR-CASH-10: the session's activity version one on.
        $this->assertSame(1, $this->activityOf($session));

        // One instant of the server for every fact of the command (SCH-21).
        $at = self::instant($row['committed_at']);
        $this->assertSame($at, self::instant($receipt['issued_at']));

        foreach ($movements as $movement) {
            $this->assertSame($at, self::instant($movement['recorded_at']));
        }

        // One audit event, with its session and no cost where the cashier may read (BR-AUD-02,
        // BR-AUD-04, BR-COST-07): the allocations are the Owner's part of it.
        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['BUSINESS', 'SALE_CHECKOUT', 'USER', $rina, 'Rina', 'sale', $sale, $session, null], [$event['category'], $event['action'], $event['actor_kind'], $event['actor_user_id'], $event['actor_display_name'], $event['subject_type'], $event['subject_id'], $event['cash_session_id'], $event['product_id']]);
        $this->assertSame(['change' => '17000', 'lineCount' => 2, 'lines' => [
            ['lineNo' => 1, 'lineTotal' => '15000', 'movementId' => $movementIds[0], 'priceRevisionId' => $this->revisionOf($this->pen), 'productId' => $this->pen, 'productSequence' => 2, 'quantity' => '2', 'unitPrice' => '7500'],
            ['lineNo' => 2, 'lineTotal' => '18000', 'movementId' => $movementIds[1], 'priceRevisionId' => $this->revisionOf($this->rice), 'productId' => $this->rice, 'productSequence' => 2, 'quantity' => '1.5', 'unitPrice' => '12000'],
        ], 'method' => 'CASH', 'number' => $number, 'reference' => null, 'sessionNumber' => $sessionNumber, 'tendered' => '50000', 'total' => '33000'], self::dataOf($event['change_data']));

        foreach (['cost', 'KNOWN', 'allocation', 'valuation', 'Bu Sari'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, self::text($event['change_data']), $word);
        }

        $this->assertSame(['lines' => [
            ['allocationId' => $allocationIds[0], 'amount' => '10000', 'basedOnPublicationNo' => 1, 'lineNo' => 1, 'status' => 'KNOWN'],
            ['allocationId' => $allocationIds[1], 'amount' => '9000', 'basedOnPublicationNo' => 1, 'lineNo' => 2, 'status' => 'KNOWN'],
        ]], self::dataOf($event['owner_only_data']));

        // The result holds references only, and the receipt of the request is settled.
        $stored = (array) $this->watcher()->table('command_results')->where('command_id', $commandId)->sole();
        $this->assertSame(['sale', $sale, ['number' => $number]], [$stored['result_type'], $stored['result_ref'], self::dataOf($stored['result_detail'])]);
        $this->assertSame(['COMMITTED'], $this->outcomesOf($envelope));

        // The same request again answers the original and writes nothing; a status check rebuilds it.
        $facts = $this->factsAndRows();
        $this->checkout($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('commandId', $commandId)->assertJsonPath('result.id', $sale)->assertJsonPath('result.number', $number);
        $this->assertSame($facts, $this->factsAndRows());
        $resolved = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.number', $number)->assertJsonPath('result.location', '/sales/'.$sale.'/receipt');
        $this->assertJsonChannel($resolved, 'commands.resolve.number', 'cashier');
    }

    public function test_each_holder_of_cap_21_sells_in_its_own_session_by_either_method_and_operations_alone_never(): void
    {
        // AC-028-02; CAP-21; SEC-AZ-04; BR-SALE-01, BR-SALE-02, BR-PAY-01 to BR-PAY-03. The Owner, the
        // cashier and the combined account each sell in their own OPEN session by CASH and by
        // BANK_TRANSFER; an account with OPERATIONS_ADMIN alone is refused at the gate and by the
        // Action's own guard, and nothing is written.
        TokoContoh::stock($this->pen, '20', '20000');
        $sessions = [];

        foreach (['Hendra' => 'LACI-1', 'Rina' => 'LACI-2', 'Dewi' => 'LACI-3'] as $person => $code) {
            $sessions[$person] = TokoContoh::openSession($person, TokoContoh::register($code, 'Laci '.$person));
        }

        foreach (['Hendra' => 'owner', 'Rina' => 'cashier', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);

            foreach ([self::cash('7500'), ['method' => PaymentMethods::BANK_TRANSFER, 'reference' => 'TRF-'.$person]] as $payment) {
                $response = $this->checkout($this->envelopeOf($sessions[$person], [self::line($this->pen, '1')], $payment))->assertStatus(201);
                $this->assertJsonChannel($response, 'commands.committed.number', $actor);
                $sale = (array) $this->watcher()->table('sales')->where('id', $response->json('result.id'))->sole(['actor_user_id', 'actor_display_name', 'cash_session_id']);
                $this->assertSame([$this->people[$person]['id'], $person, $sessions[$person]], array_values($sale), $person);
            }

            $this->assertSame(2, $this->activityOf($sessions[$person]), $person);
        }

        // Each payment as it is held: CASH with its tender and change, BANK_TRANSFER with the exact
        // total, its reference, no tender and no change; no state of any kind (BR-PAY-07).
        $this->assertSame([
            ['CASH', 7500, 7500, 0, null], ['BANK_TRANSFER', 7500, null, null, 'TRF-Hendra'],
            ['CASH', 7500, 7500, 0, null], ['BANK_TRANSFER', 7500, null, null, 'TRF-Rina'],
            ['CASH', 7500, 7500, 0, null], ['BANK_TRANSFER', 7500, null, null, 'TRF-Dewi'],
        ], $this->watcher()->table('payments')->orderBy('id')->get(['method', 'applied_amount', 'tendered_amount', 'change_amount', 'reference'])->map(static fn (object $row): array => array_values((array) $row))->all());
        $this->assertSame([3, 3], [
            $this->watcher()->table('receipt_snapshots')->where('payment_method', 'BANK_TRANSFER')->whereNull('tendered_amount')->whereNull('change_amount')->count(),
            $this->watcher()->table('receipt_snapshots')->where('payment_method', 'CASH')->where('tendered_amount', 7500)->where('change_amount', 0)->count(),
        ]);

        // OPERATIONS_ADMIN alone: FORBIDDEN at the gate, with no receipt of the request.
        $before = $this->factsAndRows();
        $this->as('Agus');
        $this->checkout($this->envelopeOf($sessions['Rina'], [self::line($this->pen, '1')], self::cash('7500')))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', CheckoutSale::OPERATION)->where('actor_user_id', $this->people['Agus']['id'])->count());

        // The Action refuses him even without the route's gate.
        try {
            $this->sellAs($this->people['Agus']['id'], $sessions['Rina'], [self::line($this->pen, '1')], self::cash('7500'));
            $this->fail('An account without CAP-21 passed the guard.');
        } catch (ActorNotAllowed) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($before, $this->factsAndRows());

        $this->browse(null);
        $this->checkout($this->envelopeOf($sessions['Rina'], [self::line($this->pen, '1')], self::cash('7500')))->assertStatus(401);
        $this->assertSame($before, $this->factsAndRows());
    }

    public function test_checkout_needs_the_actors_own_open_session_and_any_other_is_refused_with_nothing_written(): void
    {
        // AC-028-02, AC-028-04; G-04; BR-SALE-02; SEC-AZ-03; RACE-08 in sequence, the close first (the
        // close is TASK-030's, its race CheckoutRaceTest's). Without any OPEN session Rina is refused
        // NO_OPEN_SESSION, whatever session she names; with her own, another cashier's session, one that
        // does not exist and her own once it closed are SESSION_NOT_OPEN. Another's session is never
        // locked or changed.
        TokoContoh::stock($this->pen, '10', '10000');
        $dewi = TokoContoh::openSession('Dewi', TokoContoh::register('LACI-1', 'Laci 1'));
        $this->as('Rina');
        $before = $this->factsAndRows();

        foreach ([$dewi, 999999] as $session) {
            $refused = $this->checkout($this->envelopeOf($session, [self::line($this->pen, '1')], self::cash('7500')))->assertStatus(422)->assertJsonPath('code', 'NO_OPEN_SESSION')->assertJsonPath('messageKey', 'ux.state.no_session');
            $this->assertJsonChannel($refused, 'commands.refused', 'cashier');
        }

        $this->assertSame($before, $this->factsAndRows());
        $own = TokoContoh::openSession('Rina', TokoContoh::register('LACI-2', 'Laci 2'));
        $before = $this->factsAndRows();

        foreach ([$dewi, 999999] as $session) {
            $this->checkout($this->envelopeOf($session, [self::line($this->pen, '1')], self::cash('7500')))->assertStatus(422)->assertJsonPath('code', 'SESSION_NOT_OPEN')->assertJsonPath('messageKey', 'ux.state.session_closed.till');
        }

        $this->assertSame($before, $this->factsAndRows());
        $this->assertSame([0, 0], [$this->activityOf($dewi), $this->activityOf($own)]);

        // Her own session, closed: SESSION_NOT_OPEN before any fact, and again once she holds another.
        $this->closeSession($own);
        $before = $this->factsAndRows();
        $this->checkout($this->envelopeOf($own, [self::line($this->pen, '1')], self::cash('7500')))->assertStatus(422)->assertJsonPath('code', 'SESSION_NOT_OPEN');
        $this->assertSame($before, $this->factsAndRows());

        $next = TokoContoh::openSession('Rina', TokoContoh::register('LACI-3', 'Laci 3'));
        $before = $this->factsAndRows();
        $this->checkout($this->envelopeOf($own, [self::line($this->pen, '1')], self::cash('7500')))->assertStatus(422)->assertJsonPath('code', 'SESSION_NOT_OPEN');
        $this->assertSame($before, $this->factsAndRows());

        // In the session she holds, she sells; the closed one keeps its version.
        $sale = self::number($this->checkout($this->envelopeOf($next, [self::line($this->pen, '1')], self::cash('7500')))->assertStatus(201)->json('result.id'));
        $this->assertSame([$next, 1, 0], [$this->watcher()->table('sales')->where('id', $sale)->value('cash_session_id'), $this->activityOf($next), $this->activityOf($own)]);
    }

    public function test_the_payment_is_one_full_cash_or_bank_transfer_and_every_other_method_shape_or_price_is_refused(): void
    {
        // AC-028-03; BR-PAY-01 to BR-PAY-03, BR-PAY-07; TR-VAL-03; D-036, D-040, D-077; the example of
        // L-17. Ten pens make Rp75.000: Rp70.000 tendered is short; Rp100.000 gives Rp25.000 change and
        // Rp75.000 applied; a transfer takes the exact total. No request names a second instrument, a
        // part of the total, a price or a total of its own.
        TokoContoh::stock($this->pen, '30', '30000');
        $session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $this->as('Rina');
        $ten = [self::line($this->pen, '10')];
        $before = $this->factsAndRows();

        $short = $this->checkout($this->envelopeOf($session, $ten, self::cash('70000')))->assertStatus(422)->assertJsonPath('code', 'TENDER_INSUFFICIENT')->assertJsonPath('messageKey', 'ux.state.tender_low');
        $this->assertJsonChannel($short, 'commands.refused', 'cashier');
        $this->checkout($this->envelopeOf($session, $ten, self::cash('74999')))->assertStatus(422)->assertJsonPath('code', 'TENDER_INSUFFICIENT');

        // Every method outside the list, STATIC_QRIS included, before any lock.
        foreach (['STATIC_QRIS', 'QRIS', 'CARD', 'E_WALLET', 'DEBIT', 'cash', 'Bank_Transfer', 'MIXED', 'SPLIT'] as $method) {
            $refused = $this->checkout($this->envelopeOf($session, $ten, ['method' => $method, 'tendered' => '100000']))->assertStatus(422)->assertJsonPath('code', 'METHOD_NOT_ALLOWED')->assertJsonPath('messageKey', 'ux.state.method_not_allowed');
            $this->assertSame([], $refused->json('fields'), $method);
        }

        // A second instrument, a part of the total, an amount of the client's, a price or a total.
        $revision = $this->revisionOf($this->pen);

        foreach ([
            ['payment' => [self::cash('50000'), ['method' => PaymentMethods::BANK_TRANSFER]]],
            ['payments' => [self::cash('100000')]],
            ['payment' => [...self::cash('100000'), 'amount' => '50000']],
            ['payment' => [...self::cash('100000'), 'applied' => '75000']],
            ['payment' => [...self::cash('100000'), 'change' => '25000']],
            ['payment' => [...self::cash('100000'), 'deposit' => '10000']],
            ['payment' => [...self::cash('100000'), 'second' => ['method' => PaymentMethods::BANK_TRANSFER]]],
            ['payment' => [...self::cash('100000'), 'status' => 'PENDING']],
            ['payment' => ['method' => PaymentMethods::BANK_TRANSFER, 'tendered' => '75000']],
            ['payment' => ['method' => PaymentMethods::BANK_TRANSFER, 'reference' => "TRF\n1"]],
            ['payment' => [...self::cash('100000'), 'reference' => 'TRF-1']],
            ['payment' => ['method' => PaymentMethods::CASH]],
            ['payment' => ['method' => PaymentMethods::CASH, 'tendered' => 100000]],
            ['payment' => ['method' => PaymentMethods::CASH, 'tendered' => '100000.50']],
            ['payment' => ['tendered' => '100000']],
            ['payment' => ['method' => 7, 'tendered' => '100000']],
            ['payment' => null],
            ['lines' => [[...self::line($this->pen, '10'), 'unitPrice' => '1']]],
            ['lines' => [[...self::line($this->pen, '10'), 'lineTotal' => '1']]],
            ['lines' => [[...self::line($this->pen, '10'), 'priceRevisionId' => $revision]]],
            ['total' => '1'],
            ['cashierId' => $this->people['Hendra']['id']],
        ] as $index => $changes) {
            $this->checkoutPayload([...['sessionId' => $session, 'lines' => $ten, 'payment' => self::cash('100000')], ...$changes])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $this->assertSame($before, $this->factsAndRows(), (string) $index);
        }

        // A price revision that is not the product's in force, another product's, is a changed price:
        // the line is refused with the price that stands (D-036, BR-SALE-05, BR-SALE-19).
        $tampered = $this->checkout($this->envelopeOf($session, $ten, self::cash('100000'), [0 => $this->revisionOf($this->rice)]))->assertStatus(409)->assertJsonPath('code', 'PRICE_CHANGED');
        $this->assertSame([['productId' => $this->pen, 'price' => ['amount' => '7500', 'revisionId' => $revision]]], $tampered->json('lines'));
        $this->assertJsonChannel($tampered, 'commands.refused.price', 'cashier');
        $this->assertSame($before, $this->factsAndRows());

        // CASH, Rp100.000 for Rp75.000; the exact cash; a transfer of the exact total with its reference.
        $cash = self::number($this->checkout($this->envelopeOf($session, $ten, self::cash('100000')))->assertStatus(201)->json('result.id'));
        $exact = self::number($this->checkout($this->envelopeOf($session, $ten, self::cash('75000')))->assertStatus(201)->json('result.id'));
        $transfer = self::number($this->checkout($this->envelopeOf($session, $ten, ['method' => PaymentMethods::BANK_TRANSFER, 'reference' => 'TRF-0001']))->assertStatus(201)->json('result.id'));
        $this->assertSame(['CASH', 75000, 100000, 25000, null], $this->paymentOf($cash));
        $this->assertSame(['CASH', 75000, 75000, 0, null], $this->paymentOf($exact));
        $this->assertSame(['BANK_TRANSFER', 75000, null, null, 'TRF-0001'], $this->paymentOf($transfer));
        $this->assertSame([75000, 75000, 75000], array_values($this->watcher()->table('sales')->whereIn('id', [$cash, $exact, $transfer])->orderBy('id')->pluck('total_amount')->all()));
        $this->assertSame(['0.000', 4], $this->balanceOf($this->pen));
    }

    public function test_each_definite_rejection_under_the_locks_in_the_order_of_rc_01_writes_nothing_and_takes_no_number(): void
    {
        // AC-028-04; INTERACTION_CONTRACTS RC-01, "Under locks", and section 1.6; TR-VAL-08; BR-SALE-05,
        // BR-SALE-09, BR-INV-06, BR-INV-07; G-02, G-10, G-11; D-036; RACE-23 in sequence, the change
        // first. Onboarding before the session; then a product that does not exist, inactive, blocked,
        // a quantity beyond its precision, a price revision no longer in force, the stock and the
        // tender: each refused before any fact and before the number, so the next Sale takes the next.
        $sugar = TokoContoh::product('GULA-01', ['categoryId' => $this->category, 'name' => 'Gula', 'baseUnit' => 'kg', 'quantityPrecision' => 2, 'sellingPrice' => '16000']);
        TokoContoh::stock($sugar, '9', '9000');
        TokoContoh::postingBlock($sugar);
        $salt = TokoContoh::product('GARAM-01', ['categoryId' => $this->category, 'name' => 'Garam', 'baseUnit' => 'kg', 'quantityPrecision' => 2, 'sellingPrice' => '3000']);
        TokoContoh::stock($salt, '2.5', '5000');
        TokoContoh::stock($this->pen, '9', '9000');
        TokoContoh::stock($this->rice, '9', '9000');
        $session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $counter = self::number($this->watcher()->table('sales')->where('id', TokoContoh::sell('Rina', $session, [self::line($this->pen, '1')]))->value('sale_number_seq'));
        $old = $this->revisionOf($this->pen);

        // The Owner deactivates the rice and changes the pen's price while the cart is open.
        $this->as('Hendra');
        $this->command('product.deactivate', ['productId' => $this->rice], ['productVersion' => $this->versionOf($this->rice)])->assertStatus(201);
        $this->command('product.update', ['productId' => $this->pen, 'name' => 'Pulpen Hitam', 'categoryId' => $this->category, 'brand' => null, 'baseUnit' => 'pcs', 'quantityPrecision' => 0, 'sellingPrice' => '8000', 'minimumStock' => '0'], ['productVersion' => $this->versionOf($this->pen)])->assertStatus(201);
        $new = $this->revisionOf($this->pen);
        $this->assertNotSame($old, $new);
        $this->as('Rina');
        $before = $this->factsAndRows();

        $missing = $this->checkout($this->envelopeOf($session, [self::line($salt, '1'), self::line(999999, '1')], self::cash('20000'), [1 => $new]))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->assertJsonChannel($missing, 'commands.refused', 'cashier');

        // Inactive before blocked, for every line; then blocked.
        $inactive = $this->checkout($this->envelopeOf($session, [self::line($this->rice, '1'), self::line($sugar, '1'), self::line($salt, '1')], self::cash('50000')))->assertStatus(422)->assertJsonPath('code', 'PRODUCT_INACTIVE')->assertJsonPath('messageKey', 'ux.state.inactive.product');
        $this->assertSame([['productId' => $this->rice]], $inactive->json('lines'));
        $this->assertJsonChannel($inactive, 'commands.refused.lines', 'cashier');
        $this->checkout($this->envelopeOf($session, [self::line($salt, '1'), self::line($sugar, '1')], self::cash('50000')))->assertStatus(422)->assertJsonPath('code', 'POSTING_BLOCKED')->assertJsonPath('lines', [1 => ['productId' => $sugar]]);

        // The precision before the price and the stock: the pen at its old revision, the salt beyond
        // its stock, each refused at its quantity.
        $precision = $this->checkout($this->envelopeOf($session, [self::line($this->pen, '1.5'), self::line($salt, '100.234')], self::cash('50000'), [0 => $old]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame([
            'payload.lines.0.quantity' => [['key' => 'ux.validation.quantity_whole', 'params' => []]],
            'payload.lines.1.quantity' => [['key' => 'ux.validation.quantity_decimals', 'params' => ['desimal' => '2']]],
        ], $precision->json('fields'));

        // The price before the stock: the line the cashier reviewed at the old price is refused with the
        // price in force and its revision, even for more pens than there are.
        $changed = $this->checkout($this->envelopeOf($session, [self::line($this->pen, '100'), self::line($salt, '1')], self::cash('900000'), [0 => $old]))->assertStatus(409)->assertJsonPath('code', 'PRICE_CHANGED')->assertJsonPath('messageKey', 'ux.state.price_changed');
        $this->assertSame([['productId' => $this->pen, 'price' => ['amount' => '8000', 'revisionId' => $new]]], $changed->json('lines'));
        $this->assertJsonChannel($changed, 'commands.refused.price', 'cashier');

        // The stock, with what is available in the product's unit; then the tender.
        $stock = $this->checkout($this->envelopeOf($session, [self::line($this->pen, '9'), self::line($salt, '2.5')], self::cash('90000')))->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_STOCK');
        $this->assertSame([['productId' => $this->pen, 'available' => '8']], $stock->json('lines'));
        $this->assertJsonChannel($stock, 'commands.refused.stock', 'cashier');
        $this->checkout($this->envelopeOf($session, [self::line($salt, '2.51')], self::cash('9000')))->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_STOCK')->assertJsonPath('lines', [['productId' => $salt, 'available' => '2.5']]);
        $this->checkout($this->envelopeOf($session, [self::line($salt, '1')], self::cash('2999')))->assertStatus(422)->assertJsonPath('code', 'TENDER_INSUFFICIENT');

        // The onboarding freeze, before the session and every product (G-10).
        $this->runtime()->table('onboarding_state')->where('id', 1)->update(['state' => 'ACTIVE', 'version' => 2]);
        $frozen = $this->checkout($this->envelopeOf(999999, [self::line(999999, '1')], self::cash('1'), [0 => $new]))->assertStatus(422)->assertJsonPath('code', 'ONBOARDING_FREEZE')->assertJsonPath('messageKey', 'ux.state.onboarding');
        $this->assertJsonChannel($frozen, 'commands.refused', 'cashier');
        $this->runtime()->table('onboarding_state')->where('id', 1)->update(['state' => 'NOT_STARTED', 'version' => 3]);

        $this->assertSame($before, $this->factsAndRows());
        $this->assertSame([0, 1], [$this->watcher()->table('command_request_receipts')->where('operation', CheckoutSale::OPERATION)->where('outcome', '<>', 'REJECTED')->count(), $this->activityOf($session)]);

        // No refusal took a number: the next Sale takes the next counter, at the new price.
        $next = self::number($this->checkout($this->envelopeOf($session, [self::line($this->pen, '2'), self::line($salt, '2.5')], self::cash('23500')))->assertStatus(201)->json('result.id'));
        $this->assertSame([$counter + 1, 23500], array_values((array) $this->watcher()->table('sales')->where('id', $next)->sole(['sale_number_seq', 'total_amount'])));
        $this->assertSame([[$new, 8000, 16000], [$this->revisionOf($salt), 3000, 7500]], $this->watcher()->table('sale_lines')->where('sale_id', $next)->orderBy('line_no')->get(['selling_price_revision_id', 'unit_price', 'line_total'])->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    public function test_the_fields_a_product_named_twice_and_more_than_200_lines_are_refused_before_any_lock_and_200_products_sell(): void
    {
        // AC-028-02; INTERACTION_CONTRACTS RC-01, "Before locks", and section 1.6; TECHNICAL_RULES
        // sections 11.2 and 11.3; TR-VAL-01 to TR-VAL-07; COPY_DECK section 16.1; D-067.
        TokoContoh::stock($this->pen, '20', '20000');
        $session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $line = self::line($this->pen, '1');
        $payload = ['sessionId' => $session, 'lines' => [$line], 'payment' => self::cash('7500')];
        $this->as('Rina');
        $before = $this->factsAndRows();

        foreach ([
            [['sessionId' => null], 'payload.sessionId', 'ux.validation.required', []],
            [['lines' => []], 'payload.lines', 'ux.validation.lines_min', []],
            [['lines' => null], 'payload.lines', 'ux.validation.lines_min', []],
            [['lines' => [[...$line, 'productId' => 0]]], 'payload.lines.0.productId', 'ux.validation.choose_one', []],
            [['lines' => [[...$line, 'productId' => null]]], 'payload.lines.0.productId', 'ux.validation.required', []],
            [['lines' => [[...$line, 'quantity' => '0']]], 'payload.lines.0.quantity', 'ux.validation.quantity_positive', []],
            [['lines' => [[...$line, 'quantity' => '-1']]], 'payload.lines.0.quantity', 'ux.validation.quantity_positive', []],
            [['lines' => [[...$line, 'quantity' => 1]]], 'payload.lines.0.quantity', 'ux.validation.quantity_positive', []],
            [['lines' => [[...$line, 'quantity' => '1.2345']]], 'payload.lines.0.quantity', 'ux.validation.quantity_decimals', ['desimal' => '3']],
            [['lines' => [[...$line, 'quantity' => '100000000']]], 'payload.lines.0.quantity', 'ux.validation.quantity_max', []],
            [['lines' => [[...$line, 'quantity' => null]]], 'payload.lines.0.quantity', 'ux.validation.required', []],
            [['lines' => array_map(static fn (int $product): array => self::line($product, '1'), range(100001, 100201))], 'payload.lines', 'ux.validation.lines_max', ['maks' => CheckoutSale::MAX_LINES]],
            [['payment' => ['method' => PaymentMethods::CASH]], 'payload.payment.tendered', 'ux.validation.required', []],
            [['payment' => self::cash('-1')], 'payload.payment.tendered', 'ux.validation.money_min', []],
            [['payment' => self::cash('1000000000000')], 'payload.payment.tendered', 'ux.validation.money_max', []],
            [['payment' => self::cash('7500.5')], 'payload.payment.tendered', 'ux.validation.money', []],
            [['payment' => ['method' => PaymentMethods::BANK_TRANSFER, 'reference' => str_repeat('r', 101)]], 'payload.payment.reference', 'ux.validation.text_max', ['maks' => 100]],
            [['payment' => [...self::cash('7500'), 'customerName' => str_repeat('c', 101)]], 'payload.payment.customerName', 'ux.validation.text_max', ['maks' => 100]],
            [['payment' => ['tendered' => '7500']], 'payload.payment.method', 'ux.validation.required', []],
        ] as $index => [$changes, $path, $key, $params]) {
            $refused = $this->checkoutPayload([...$payload, ...$changes])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $fields = (array) $refused->json('fields');
            $this->assertSame(['key' => $key, 'params' => $params], ((array) ($fields[$path] ?? []))[0] ?? null, sprintf('case %d at %s', $index, $path));
        }

        // What no page sends: a session or a product as text, a line that is no object, lines with
        // gaps, and reviewed revisions that do not name each line once, as whole numbers.
        foreach ([
            ['sessionId' => (string) $session],
            ['lines' => 'x'],
            ['lines' => ['x']],
            ['lines' => [[...$line, 'productId' => (string) $this->pen]]],
            ['lines' => [0 => $line, 2 => self::line($this->rice, '1')]],
            ['lines' => [[...$line, 'cost' => '1000']]],
        ] as $changes) {
            $this->checkoutPayload([...$payload, ...$changes])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        foreach ([[], [['priceRevisionId' => $this->revisionOf($this->pen)], ['priceRevisionId' => $this->revisionOf($this->pen)]], [['priceRevisionId' => (string) $this->revisionOf($this->pen)]], [['revisionId' => $this->revisionOf($this->pen)]], [['priceRevisionId' => 0]]] as $reviewed) {
            $this->checkout($this->envelope($payload, ['operation' => CheckoutSale::OPERATION, 'expected' => ['lines' => $reviewed]]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // D-067: a product named twice is refused at each line that repeats it, never merged.
        $twice = $this->checkoutPayload([...$payload, 'lines' => [self::line($this->pen, '1'), self::line($this->rice, '1'), self::line($this->pen, '2'), self::line($this->pen, '3')]])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')->assertJsonPath('messageKey', 'ux.state.validation');
        $this->assertSame([2 => ['productId' => $this->pen], 3 => ['productId' => $this->pen]], $twice->json('lines'));
        $this->assertSame([], $twice->json('fields'));
        $this->assertSame($before, $this->factsAndRows());
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', CheckoutSale::OPERATION)->where('outcome', '<>', 'REJECTED')->count());

        // 200 products, one line each, sell in one Sale; the longest names and reference are kept.
        $products = [];

        for ($index = 1; $index <= CheckoutSale::MAX_LINES; $index++) {
            $products[] = TokoContoh::product(sprintf('MASSAL-%03d', $index), ['categoryId' => $this->category, 'name' => sprintf('Produk %03d', $index), 'sellingPrice' => '1000']);
        }

        TokoContoh::receive(array_map(static fn (int $product): array => ['productId' => $product, 'quantity' => '2', 'cost' => ['status' => 'KNOWN', 'amount' => '1000']], $products));
        $this->as('Rina');
        $all = $this->checkout($this->envelopeOf($session, array_map(static fn (int $product): array => self::line($product, '1'), $products), [...self::cash('200000'), 'customerName' => str_repeat('c', 100)]))->assertStatus(201);
        $sale = self::number($all->json('result.id'));
        $commandId = self::number($all->json('commandId'));
        $this->assertSame([200000, 200, str_repeat('c', 100)], array_values((array) $this->watcher()->table('sales')->where('id', $sale)->sole(['total_amount', 'line_count', 'customer_name'])));
        $this->assertSame(range(1, 200), array_values($this->watcher()->table('sale_lines')->where('sale_id', $sale)->orderBy('line_no')->pluck('line_no')->all()));
        $this->assertSame($products, array_values($this->watcher()->table('sale_lines')->where('sale_id', $sale)->orderBy('line_no')->pluck('product_id')->all()));
        $this->assertSame([200, 200], [$this->watcher()->table('stock_movements')->where('command_id', $commandId)->where('movement_type', 'SALE_ISSUE')->count(), $this->watcher()->table('issue_cost_allocations')->whereIn('movement_id', $this->watcher()->table('stock_movements')->select('id')->where('command_id', $commandId))->count()]);
        $this->assertCount(200, (array) json_decode(self::text($this->watcher()->table('receipt_snapshots')->where('sale_id', $sale)->value('lines')), true));
        $this->assertSame(200, $this->watcher()->table('stock_balances')->whereIn('product_id', $products)->where('on_hand', '1.000')->where('last_sequence', 2)->count());
        $this->as('Rina');
        $transfer = $this->checkout($this->envelopeOf($session, [self::line($products[0], '1')], ['method' => PaymentMethods::BANK_TRANSFER, 'reference' => str_repeat('r', 100)]))->assertStatus(201);
        $this->assertSame(str_repeat('r', 100), $this->watcher()->table('payments')->where('sale_id', $transfer->json('result.id'))->value('reference'));
    }

    public function test_an_envelope_is_refused_as_every_command_refuses_one_and_a_repeat_or_a_status_check_answers_the_sale_after_its_session_closed(): void
    {
        // CMD01; G-12, G-25; RC-50; ADR-007 section 3; BR-SALE-18; RACE-01 and RACE-02 in sequence
        // (their races are CheckoutRaceTest's).
        TokoContoh::stock($this->pen, '10', '10000');
        $session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $this->as('Rina');
        $envelope = $this->envelopeOf($session, [self::line($this->pen, '3')], self::cash('22500'));
        $before = $this->factsAndRows();

        foreach ([['version' => 2], ['tambahan' => 'x'], ['operation' => 'manual_issue.confirm'], ['expected' => ['lines' => [['priceRevisionId' => 1.5]]]], ['expected' => ['lines' => [['priceRevisionId' => 1]], 'products' => []]]] as $changes) {
            $this->checkout([...$envelope, 'idempotencyKey' => (string) Str::uuid(), ...$changes])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->checkout([...$envelope, 'sourceIntentId' => (string) Str::uuid(), 'idempotencyKey' => (string) Str::uuid(), 'recoveryEpoch' => str_repeat('ab', 32)])->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE')->assertJsonPath('outcome', 'HELD');
        $this->assertSame($before, $this->factsAndRows());

        // The command runs once; a repeat answers the original; another payload is held.
        $committed = $this->checkout($envelope)->assertStatus(201);
        $sale = self::number($committed->json('result.id'));
        $number = self::text($committed->json('result.number'));
        $facts = $this->factsAndRows();
        $other = ['sessionId' => $session, 'lines' => [self::line($this->pen, '4')], 'payment' => self::cash('30000')];

        $this->checkout($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $sale)->assertJsonPath('result.number', $number);
        $this->checkout([...$envelope, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $sale);

        foreach ([[...$envelope, 'payload' => $other], [...$envelope, 'idempotencyKey' => (string) Str::uuid(), 'payload' => $other]] as $conflicting) {
            $this->checkout($conflicting)->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT')->assertJsonPath('outcome', 'HELD');
        }

        $this->assertSame($facts, $this->factsAndRows());
        $this->assertSame(['7.000', 2], $this->balanceOf($this->pen));

        // The session closes: a repeat and a status check still answer the Sale, whose identity is read
        // before any session lock; a new intent in that session is refused.
        $this->closeSession($session);
        $this->checkout($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $sale)->assertJsonPath('result.number', $number);
        $resolved = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.id', $sale)->assertJsonPath('result.number', $number)->assertJsonPath('result.location', '/sales/'.$sale.'/receipt');
        $this->assertJsonChannel($resolved, 'commands.resolve.number', 'cashier');
        $this->checkout($this->envelopeOf($session, [self::line($this->pen, '1')], self::cash('7500')))->assertStatus(422)->assertJsonPath('code', 'SESSION_NOT_OPEN');
        $this->assertSame([1, ['7.000', 2]], [$this->watcher()->table('sales')->count(), $this->balanceOf($this->pen)]);
    }

    public function test_a_status_check_answers_the_sale_to_the_owner_and_no_result_once_its_cashier_holds_no_cap_21(): void
    {
        // ADR-007 section 3; INTERACTION_CONTRACTS sections 2.1 and 2.5; CAP-21 own, CAP-22, CAP-34;
        // SEC-AZ-06 (review finding R-05). The Owner's status check of a cashier's committed checkout,
        // as before an assisted close, answers it with its number. Once the cashier holds no CAP-21 — a
        // role change, which also ends her sign-in sessions — her own check answers COMMITTED with no
        // result.
        TokoContoh::stock($this->pen, '10', '10000');
        $session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $this->as('Rina');
        $envelope = $this->envelopeOf($session, [self::line($this->pen, '1')], self::cash('7500'));
        $committed = $this->checkout($envelope)->assertStatus(201);
        $sale = self::number($committed->json('result.id'));
        $commandId = self::number($committed->json('commandId'));
        $number = self::text($committed->json('result.number'));

        $this->as('Hendra');
        $resolved = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('commandId', $commandId)->assertJsonPath('result.id', $sale)->assertJsonPath('result.number', $number)->assertJsonPath('result.location', '/sales/'.$sale.'/receipt');
        $this->assertJsonChannel($resolved, 'commands.resolve.number', 'owner');

        $rina = $this->people['Rina'];
        $changed = (new UserCommandScenario)->run([
            'operation' => 'user.update', 'actor' => $this->people['Hendra']['id'], 'intent' => (string) Str::uuid(), 'key' => (string) Str::uuid(), 'epoch' => $this->epoch,
            'expected' => ['userVersion' => self::number($this->watcher()->table('users')->where('id', $rina['id'])->value('version'))],
            'payload' => ['userId' => $rina['id'], 'displayName' => 'Rina', 'email' => $rina['email'], 'roles' => ['operationsAdmin']],
        ]);
        $this->assertSame('COMMITTED', $changed['outcome'] ?? null);

        $this->browse($this->signInBrowser($rina['email'], $rina['password']));
        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('commandId', $commandId)->assertJsonPath('result', null);
        $this->assertSame(1, $this->watcher()->table('sales')->count());
    }

    public function test_a_failure_after_every_write_step_leaves_no_fact_and_the_numbers_taken_leave_a_gap(): void
    {
        // AC-028-01, AC-028-06; TS-21; SCH-21, TR-TX-10. Two lines of two products write 17 steps: the
        // Sale, each line, the Payment, the receipt snapshot, the running total, each product's balance
        // and valuation, each line's SALE_ISSUE and allocation, the session's activity version, the
        // audit event and the result. Each attempt took its number before its first write, so the Sale
        // that commits after them leaves a gap of 17 numbers that no Sale fills.
        TokoContoh::stock($this->pen, '20', '20000');
        TokoContoh::stock($this->rice, '5', '5000');
        $session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $lines = [self::line($this->pen, '2'), self::line($this->rice, '1.5')];
        $first = self::number($this->watcher()->table('sales')->where('id', TokoContoh::sell('Rina', $session, $lines))->value('sale_number_seq'));
        $this->as('Rina');

        for ($step = 1; $step <= 17; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $before = $this->factsAndRows();

            $this->checkout($this->envelopeOf($session, $lines, self::cash('33000')))->assertStatus(500);

            $this->assertSame($before, $this->factsAndRows(), sprintf('after write step %d', $step));
            $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', CheckoutSale::OPERATION)->orderByDesc('id')->value('outcome'), (string) $step);
        }

        // The step after the last is no step of the command: it commits, through the locks of TR §7.1
        // in their order.
        $points = new FailurePoints(18);
        $this->app->instance(CommandPoints::class, $points);
        $committed = $this->checkout($this->envelopeOf($session, $lines, self::cash('33000')))->assertStatus(201);
        $this->assertSame(['after_lock:2.1', 'after_lock:2.3', 'after_lock:2.4', 'after_lock:4', 'after_lock:6', 'after_lock:7', ...array_map(static fn (int $step): string => 'after_write:'.$step, range(1, 17))], array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))));
        $this->app->instance(CommandPoints::class, new FailurePoints);

        $counter = self::number($this->watcher()->table('sales')->where('id', $committed->json('result.id'))->value('sale_number_seq'));
        $this->assertSame($first + 18, $counter);
        $this->assertSame([$first, $counter], array_values($this->watcher()->table('sales')->orderBy('sale_number_seq')->pluck('sale_number_seq')->all()));
        $this->assertSame([['16.000', 3], ['2.000', 3], 2], [$this->balanceOf($this->pen), $this->balanceOf($this->rice), $this->activityOf($session)]);
    }

    public function test_a_deadlock_is_retried_under_the_same_identity_and_each_attempt_takes_its_own_number(): void
    {
        // TR-RETRY-01, TR-TX-10; AC-008-05 for RC-01: two deadlocks, then the third attempt commits once,
        // with the third number taken; three deadlocks answer SERVICE_BUSY with nothing written and the
        // receipt PENDING for the client's repeat.
        TokoContoh::stock($this->pen, '10', '10000');
        $session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $first = self::number($this->watcher()->table('sales')->where('id', TokoContoh::sell('Rina', $session, [self::line($this->pen, '1')]))->value('sale_number_seq'));
        $this->as('Rina');

        $points = new DeadlockPoints(2);
        $this->app->instance(CommandPoints::class, $points);
        $envelope = $this->envelopeOf($session, [self::line($this->pen, '1')], self::cash('7500'));
        $sale = self::number($this->checkout($envelope)->assertStatus(201)->json('result.id'));
        $this->assertSame(3, $points->attempts);
        $this->assertSame(1, $this->watcher()->table('command_identities')->where('source_intent_id', $envelope['sourceIntentId'])->where('idempotency_key', $envelope['idempotencyKey'])->count());
        $this->assertSame([$first + 3, 2], [self::number($this->watcher()->table('sales')->where('id', $sale)->value('sale_number_seq')), $this->watcher()->table('sales')->count()]);

        $points = new DeadlockPoints(3);
        $this->app->instance(CommandPoints::class, $points);
        $envelope = $this->envelopeOf($session, [self::line($this->pen, '1')], self::cash('7500'));
        $before = $this->factsAndRows();
        $this->checkout($envelope)->assertStatus(503)->assertJsonPath('code', 'SERVICE_BUSY');
        $this->assertSame(3, $points->attempts);
        $this->assertSame($before, $this->factsAndRows());
        $this->assertSame(['PENDING'], $this->outcomesOf($envelope));
        $this->app->instance(CommandPoints::class, new FailurePoints);
    }

    public function test_the_till_search_needs_the_cashiers_own_open_session_and_gives_what_checkout_expects(): void
    {
        // AC-028-02; INTERACTION_CONTRACTS section 3.1, the context `till`; ROUTE_CONTRACTS RD-01;
        // BR-CAT-33; CAP-02 in the till, CAP-14 restricted; TASK-028 scope item 6. Each row carries the
        // price revision that RC-01 expects as reviewed and the stock on hand alone, for a cashier.
        TokoContoh::stock($this->pen, '10', '10000');
        $this->as('Rina');
        $this->getJson('/data/products/search?context=till&q=PEN')->assertStatus(422)->assertJsonPath('code', 'NO_OPEN_SESSION')->assertJsonPath('messageKey', 'ux.state.no_session');

        $session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $this->as('Rina');
        $found = $this->getJson('/data/products/search?context=till&q=PEN')->assertOk();
        $this->assertJsonChannel($found, 'data.products.search.till', 'cashier');
        $row = (array) $found->json('rows.0');
        $this->assertSame([$this->pen, ['amount' => '7500', 'revisionId' => $this->revisionOf($this->pen)], ['onHand' => '10']], [$row['id'] ?? null, $row['price'] ?? null, $row['stock'] ?? null]);

        // The row's revision is the one the Sale expects; a scanned code finds the product too.
        $this->checkout($this->envelopeOf($session, [self::line($this->pen, '4')], self::cash('30000'), [0 => self::number($found->json('rows.0.price.revisionId'))]))->assertStatus(201);
        $this->getJson('/data/products/search?context=till&mode=exact&q=PEN-01')->assertOk()->assertJsonPath('rows.0.id', $this->pen)->assertJsonPath('rows.0.stock.onHand', '6');

        // The combined account sees the full stock cell in the till (CAP-14 full).
        TokoContoh::openSession('Dewi', TokoContoh::register('LACI-2', 'Laci 2'));
        $this->as('Dewi');
        $this->assertJsonChannel($this->getJson('/data/products/search?context=till&q=PEN')->assertOk()->assertJsonPath('rows.0.stock.onHand', '6'), 'data.products.search.till', 'combined');

        // So does the Owner, who sells in a session of his own too (CAP-21 own, CAP-14 full; review
        // finding R-04).
        TokoContoh::openSession('Hendra', TokoContoh::register('LACI-3', 'Laci 3'));
        $this->as('Hendra');
        $this->assertJsonChannel($this->getJson('/data/products/search?context=till&q=PEN')->assertOk()->assertJsonPath('rows.0.stock.onHand', '6'), 'data.products.search.till', 'owner');

        // Once her session closes, the till's search is refused again.
        $this->closeSession($session);
        $this->as('Rina');
        $this->getJson('/data/products/search?context=till&q=PEN')->assertStatus(422)->assertJsonPath('code', 'NO_OPEN_SESSION');
    }

    public function test_the_payment_boundary_holds_no_provider_record_route_callback_or_asynchronous_state(): void
    {
        // AC-028-08, PAYEXT01 by review; ARC-30 to ARC-33; BR-PAY-04, BR-PAY-07; D-003, D-077. The schema
        // holds one payment table, of the method and its amounts, with no status, provider or external
        // key; no route is one a provider could call; one place of the code defines the methods.
        $columns = [];

        foreach ($this->watcher()->select("select column_name from information_schema.columns where table_schema = 'public' and table_name = 'payments' order by ordinal_position") as $row) {
            $columns[] = self::text(((array) $row)['column_name'] ?? null);
        }

        $this->assertSame(['id', 'sale_id', 'method', 'applied_amount', 'tendered_amount', 'change_amount', 'reference'], $columns);

        $tables = [];

        foreach ($this->watcher()->select("select table_name || '.' || column_name as name from information_schema.columns where table_schema = 'public' and (table_name ~ '(payment|provider|gateway|webhook|callback|qris)' or column_name ~ '(provider|gateway|webhook|callback|qris|payment_status|payment_state)') order by 1") as $row) {
            $tables[] = self::text(((array) $row)['name'] ?? null);
        }

        $this->assertSame(['payments.applied_amount', 'payments.change_amount', 'payments.id', 'payments.method', 'payments.reference', 'payments.sale_id', 'payments.tendered_amount'], $tables);

        $check = self::text(((array) $this->watcher()->selectOne("select pg_get_constraintdef(oid) as definition from pg_constraint where conname = 'payments_method_allowed'"))['definition'] ?? null);
        preg_match_all("/'([A-Z_]+)'::text/", $check, $methods);
        $this->assertSame(PaymentMethods::ALL, $methods[1]);

        // No route that a provider could call, and RC-01 the only one of the Sales module.
        $uris = [];

        foreach (app(Router::class)->getRoutes()->getRoutes() as $route) {
            $uris[] = $route->uri();

            // RP-102 "Pembayaran" is the till's own payment view, read only and behind CAP-21
            // (ROUTE_CONTRACTS RP-102; TASK-029): a page of the cashier, never one a provider calls.
            if ($route->uri() === 'till/payment') {
                $this->assertSame(['GET', 'HEAD'], $route->methods());
                $this->assertContains('capability:CAP-21', $route->gatherMiddleware());

                continue;
            }

            $this->assertDoesNotMatchRegularExpression('/(webhook|callback|notify|provider|gateway|qris|payment)/i', $route->uri());
        }

        $this->assertContains('commands/sale.checkout', $uris);
        $this->assertSame(['commands/sale.checkout'], array_values(array_filter($uris, static fn (string $uri): bool => str_contains($uri, 'sale.'))));

        // One place of the methods' names, and no file of a provider, a gateway or a callback.
        $this->assertSame(['Domain/Sales/PaymentMethods.php'], self::filesNaming('BANK_TRANSFER'));
        $paths = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && preg_match('/(Payment|Webhook|Callback|Gateway|Qris|PaymentProvider)/', $file->getFilename()) === 1) {
                $paths[] = $file->getFilename();
            }
        }

        sort($paths);
        $this->assertSame(['PaymentMethods.php', 'SalePayment.php'], $paths);
    }

    public function test_each_table_of_the_sale_has_its_one_writer(): void
    {
        // STK-12, ARC-20, ARC-21; ADR-008 section 4.3: the Sale's tables are named by the Sales module's
        // writer alone, which only inserts; their running totals are TASK-033's to change, and nothing
        // names the line totals yet. The language files' group `sales` is no table: the dictionary
        // names it without a query. RP-103's query reads a Sale's receipt snapshot and writes nothing
        // (TASK-029).
        $reader = 'Queries/Sales/Receipts.php';
        $this->assertSame(['Domain/Sales/Sales.php', $reader, 'Support/Localization/Dictionary.php'], self::filesNaming('sales'));
        $this->assertDoesNotMatchRegularExpression('/DB::|->table\(|->insert|->update/', (string) file_get_contents(app_path('Support/Localization/Dictionary.php')));
        $this->assertDoesNotMatchRegularExpression('/->(?:insert|insertGetId|insertOrIgnore|update|upsert|delete|increment|decrement|truncate|lockForUpdate)\(|DB::(?:statement|insert|update|delete|unprepared)/', (string) file_get_contents(app_path($reader)));

        foreach (['sale_lines', 'payments', 'sale_correction_totals'] as $table) {
            $this->assertSame(['Domain/Sales/Sales.php'], self::filesNaming($table), $table);
        }

        $this->assertSame(['Domain/Sales/Sales.php', $reader], self::filesNaming('receipt_snapshots'));

        $this->assertSame([], self::filesNaming('sale_line_correction_totals'));
        $this->assertDoesNotMatchRegularExpression('/->(?:update|upsert|delete|increment|decrement|truncate)\(/', (string) file_get_contents(app_path('Domain/Sales/Sales.php')));
        $this->assertSame([CheckoutSale::OPERATION], array_values(array_filter(array_keys(Operations::FAMILIES), static fn (string $operation): bool => str_starts_with($operation, 'sale.'))));
    }

    /**
     * A line of RC-01.
     *
     * @return array{productId: int, quantity: string}
     */
    private static function line(int $product, string $quantity): array
    {
        return ['productId' => $product, 'quantity' => $quantity];
    }

    /**
     * A CASH payment of RC-01 with this tendered amount.
     *
     * @return array<string, mixed>
     */
    private static function cash(string $tendered): array
    {
        return ['method' => PaymentMethods::CASH, 'tendered' => $tendered];
    }

    /**
     * An envelope of RC-01 for these lines in this session, each line reviewed at its product's price
     * revision in force unless $revisions names others, by index.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $payment
     * @param  array<int, int>  $revisions
     * @return array<string, mixed>
     */
    private function envelopeOf(int $session, array $lines, array $payment, array $revisions = []): array
    {
        $reviewed = [];

        foreach ($lines as $index => $line) {
            $reviewed[] = ['priceRevisionId' => $revisions[$index] ?? $this->revisionOf(self::number($line['productId'] ?? null))];
        }

        return $this->envelope(['sessionId' => $session, 'lines' => $lines, 'payment' => $payment], ['operation' => CheckoutSale::OPERATION, 'expected' => ['lines' => $reviewed]]);
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function checkout(array $envelope): TestResponse
    {
        return $this->postJson(self::COMMAND, $envelope);
    }

    /**
     * RC-01 with this payload, each of its lines reviewed at its product's revision in force, or at 1
     * for a line that names no product of the shop.
     *
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function checkoutPayload(array $payload): TestResponse
    {
        $lines = $payload['lines'] ?? null;
        $reviewed = [];

        foreach (is_array($lines) ? $lines : [] as $line) {
            $product = is_array($line) ? ($line['productId'] ?? null) : null;
            $revision = is_int($product) ? $this->watcher()->table('products')->where('id', $product)->value('current_price_revision_id') : null;
            $reviewed[] = ['priceRevisionId' => is_int($revision) ? $revision : 1];
        }

        return $this->checkout($this->envelope($payload, ['operation' => CheckoutSale::OPERATION, 'expected' => ['lines' => $reviewed]]));
    }

    /**
     * Another command with its envelope.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return TestResponse<Response>
     */
    private function command(string $operation, array $payload, array $expected): TestResponse
    {
        return $this->postJson('/commands/'.$operation, $this->envelope($payload, ['operation' => $operation, 'expected' => $expected]));
    }

    private function versionOf(int $product): int
    {
        return self::number($this->watcher()->table('products')->where('id', $product)->value('version'));
    }

    /**
     * A Sale's payment: its method, its applied, tendered and change amounts, and its reference.
     *
     * @return list<mixed>
     */
    private function paymentOf(int $sale): array
    {
        return array_values((array) $this->watcher()->table('payments')->where('sale_id', $sale)->sole(['method', 'applied_amount', 'tendered_amount', 'change_amount', 'reference']));
    }

    /**
     * RC-01's Action for this actor, received now: the Sale's internal key.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $payment
     *
     * @throws CommandRefused
     */
    private function sellAs(int $actor, int $session, array $lines, array $payment): int
    {
        $envelope = $this->envelopeOf($session, $lines, $payment);
        $expected = $envelope['expected'] ?? [];
        $payload = $envelope['payload'] ?? [];
        $raw = new Envelope(CheckoutSale::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, is_array($expected) ? $expected : [], is_array($payload) ? $payload : []);
        $id = app(CheckoutSale::class)->execute(new CommandContext($actor, $raw, app(Clock::class)->now()))->result['id'] ?? null;

        return is_int($id) ? $id : 0;
    }

    /**
     * Until the close of TASK-030 exists, a session is closed as that close will leave it: CLOSED,
     * its state column written through the runtime role's privilege (SCHEMA section 5.10).
     */
    private function closeSession(int $session): void
    {
        $this->assertSame(1, $this->runtime()->table('cash_sessions')->where('id', $session)->update(['status' => 'CLOSED', 'updated_at' => now()]));
    }

    private function revisionOf(int $product): int
    {
        return self::number($this->watcher()->table('products')->where('id', $product)->value('current_price_revision_id'));
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

    private function activityOf(int $session): int
    {
        return self::number($this->watcher()->table('cash_sessions')->where('id', $session)->value('activity_version'));
    }

    /**
     * @return array<string, int>
     */
    private function facts(): array
    {
        $counts = [];

        foreach (self::FACTS as $table) {
            $counts[$table] = $this->watcher()->table($table)->count();
        }

        return $counts;
    }

    /**
     * The facts, with the rows that a Sale changes: the balances, the valuations and the sessions.
     *
     * @return array<string, mixed>
     */
    private function factsAndRows(): array
    {
        return [
            'facts' => $this->facts(),
            'balances' => $this->tableRows('stock_balances', 'product_id'),
            'valuations' => $this->tableRows('inventory_valuations', 'product_id'),
            'sessions' => $this->tableRows('cash_sessions', 'id'),
        ];
    }

    /**
     * Every row of a table, in key order.
     *
     * @return list<array<string, mixed>>
     */
    private function tableRows(string $table, string $key): array
    {
        return array_values($this->watcher()->table($table)->orderBy($key)->get()->map(static fn (object $row): array => (array) $row)->all());
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
        ksort($data);

        return array_map(static fn (mixed $value): mixed => is_array($value) ? self::sortedKeys($value) : $value, $data);
    }

    /**
     * The files of app/ that name this table in a string, as the query builder takes it; a mention in
     * a comment is no access.
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
