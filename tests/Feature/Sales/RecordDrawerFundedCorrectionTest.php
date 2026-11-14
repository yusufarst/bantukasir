<?php

namespace Tests\Feature\Sales;

use App\Actions\Sales\RecordDrawerFundedCorrection;
use App\Database\CommandPoints;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-05 `sale_correction.drawer_funded` through its route and its Action (AC-034-01 to AC-034-06;
 * INTERACTION_CONTRACTS RC-05 and sections 1.1 to 1.6 and 2.6; ROUTE_CONTRACTS RC-05; TECHNICAL_RULES sections
 * 7.1 and 7.2; SCHEMA G-04, G-05 and section 5.10; SALES_CASH BR-CASH-07, BR-CASH-10, BR-CASH-12 to BR-CASH-14,
 * BR-CASH-19, BR-CASH-20, BR-RFD-05 to BR-RFD-09, BR-RFD-15 to BR-RFD-17; D-035, D-045, D-046, D-068, D-087;
 * CAP-24; EXECUTION_CONTEXT section 10, a route and a command; suites RET02, SESSION04, SESSION05, CMD01,
 * AUTH01, AUTH03; TASK-034 section 7, L2 and L4): the Owner's CASH refund from the drawer of an OPEN session
 * that is not the Sale's, every record in one transaction with the session's activity version and the
 * reference `<Sale number>/<k>`; the Owner alone, with recent authentication; the drawer's expected cash as
 * the bound, Rp0 reachable, never switched; a CLOSED session and a missing one; the refusals before any lock;
 * a repeat and a status check; a failure after every write step; and the close: expected cash and the report
 * with the refund apart (SESSION04), a refund after the preview recounted (BR-CASH-14), and the late handover
 * of D-068, which closes with a variance of +R and creates nothing.
 */
final class RecordDrawerFundedCorrectionTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string COMMAND = '/commands/sale_correction.drawer_funded';

    private const string REASON = 'Uang dikembalikan dari laci';

    /** The tables that a refused or failed refund leaves as they were. */
    private const array FACTS = ['sale_corrections', 'refunds', 'refund_lines', 'return_lines', 'return_cost_allocations', 'stock_movements', 'cash_session_closes', 'cash_session_reports', 'command_identities', 'command_results', 'audit_events'];

    /** Counted whole: Rp30.000, its stock at Rp5.000 a unit. */
    private int $pen = 0;

    /** Rina's session, the drawer that pays, opened with Rp50.000. */
    private int $drawer = 0;

    /** Dewi's session, where the refunded Sales are made. */
    private int $other = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->pen = TokoContoh::product('PEN-01', ['categoryId' => TokoContoh::category(), 'name' => 'Pulpen Hitam', 'sellingPrice' => '30000']);
        TokoContoh::stock($this->pen, '20', '100000');
        $this->drawer = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'), '50000');
        $this->other = TokoContoh::openSession('Dewi', TokoContoh::register('LACI-2', 'Laci 2'));
    }

    public function test_the_owner_refunds_from_another_sessions_drawer_with_every_record_in_one_transaction(): void
    {
        // AC-034-01, AC-034-02; BR-RFD-05 to BR-RFD-08, BR-RFD-10; D-087; G-04, G-16, G-18. Dewi sells two pens
        // for Rp60.000; the Owner refunds Rp30.000 of them from Rina's drawer, a session other than the
        // Sale's, and takes one pen back saleable.
        $this->travelTo('2026-11-14T03:00:00.000000Z');
        $sale = TokoContoh::sell('Dewi', $this->other, [self::sold($this->pen, '2')]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $number = self::text($this->watcher()->table('sales')->where('id', $sale)->value('sale_number'));
        $this->travelTo('2026-11-14T04:00:00.250000Z');
        $hendra = $this->people['Hendra']['id'];
        $envelope = $this->envelopeOf($sale, [self::line($line, '30000', '1')], $this->drawer);

        // AUTH03: without a recent password confirmation nothing runs, not even a receipt.
        $this->as('Hendra');
        $this->refund($envelope)->assertStatus(423)->assertJsonPath('code', 'RECENT_AUTH_REQUIRED');
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', RecordDrawerFundedCorrection::OPERATION)->count());

        $this->confirm();
        $response = $this->refund($envelope)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed', 'owner');
        $correction = self::number($response->json('result.id'));
        $commandId = self::number($response->json('commandId'));
        // RP-204, where the Owner reads the amount and the drawer to take it from (D-068).
        $this->assertSame(['sale_correction', '/sales/'.$sale.'/corrections/'.$correction], [$response->json('result.type'), $response->json('result.location')]);

        // The correction, dated by its posting; the refund in CASH from the drawer of Rina's session, its
        // reference generated as the first correction of the Sale (BR-RFD-05; D-087).
        $this->assertSame([$sale, '2026-11-14', self::REASON, $hendra, 'Hendra', $commandId], array_values((array) $this->watcher()->table('sale_corrections')->where('id', $correction)->sole(['sale_id', 'business_date', 'reason', 'actor_user_id', 'actor_display_name', 'command_id'])));
        $this->assertSame([$sale, 30000, 'CASH', 'DRAWER', $this->drawer, $number.'/1'], array_values((array) $this->watcher()->table('refunds')->where('correction_id', $correction)->sole(['sale_id', 'amount', 'payout_method', 'funding_source', 'cash_session_id', 'payout_reference'])));
        $this->assertSame([[$line, 30000]], $this->rowsOf('refund_lines', ['sale_line_id', 'amount']));
        $this->assertSame([[$line, '1.000', 'SALEABLE']], $this->rowsOf('return_lines', ['sale_line_id', 'quantity', 'condition']));
        $this->assertSame(1, $this->watcher()->table('stock_movements')->where('movement_type', 'RETURN_RECEIPT')->where('command_id', $commandId)->count());

        // BR-RFD-08: session activity of the drawer that paid, one more version; the Sale's own session as
        // it was.
        $this->assertSame([[$this->drawer, 'OPEN', 1], [$this->other, 'OPEN', 1]], $this->rowsOf('cash_sessions', ['id', 'status', 'activity_version']));

        // One audit event naming the session; the payout in its data.
        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['SALE_CORRECTION', 'USER', $hendra, 'sale_correction', $correction, $number, self::REASON, $this->drawer], [$event['action'], $event['actor_kind'], $event['actor_user_id'], $event['subject_type'], $event['subject_id'], $event['reference'], $event['reason'], $event['cash_session_id']]);
        $this->assertSame(['amount' => '30000', 'fundingSource' => 'DRAWER', 'payoutMethod' => 'CASH', 'payoutReference' => $number.'/1'], self::dataOf($event['change_data'])['refund'] ?? null);
        $this->assertSame(['COMMITTED'], $this->outcomesOf($envelope));

        // The same request again answers the original and writes nothing; a status check rebuilds it.
        $facts = $this->factsAndRows();
        $this->refund($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('commandId', $commandId)->assertJsonPath('result.id', $correction);
        $this->assertSame($facts, $this->factsAndRows());
        $resolved = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.location', '/sales/'.$sale.'/corrections/'.$correction);
        $this->assertJsonChannel($resolved, 'commands.resolve', 'owner');

        // A second correction of the Sale, by RC-06, takes the next ordinal; a third, drawer-funded, the one
        // after (D-087: the ordinal of the correction on that Sale).
        $this->postJson('/commands/sale_correction.other', $this->envelope(['saleId' => $sale, 'lines' => [self::line($line, '0', '0', '1')], 'reason' => self::REASON], ['operation' => 'sale_correction.other']))->assertStatus(201);
        $this->refund($this->envelopeOf($sale, [self::line($line, '10000')], $this->drawer))->assertStatus(201);
        $this->assertSame([$number.'/1', $number.'/3'], $this->watcher()->table('refunds')->orderBy('id')->pluck('payout_reference')->all());
    }

    public function test_only_the_owner_refunds_from_a_drawer_and_every_other_role_and_the_combined_account_is_refused(): void
    {
        // BR-RFD-01; CAP-24; THR-19: the cashier of the drawer, Operations and the combined account are refused
        // at the gate on the server, before any receipt or fact.
        $sale = TokoContoh::sell('Dewi', $this->other, [self::sold($this->pen, '1')]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $before = $this->factsAndRows();

        foreach (['Rina', 'Agus', 'Dewi'] as $person) {
            $this->as($person);
            $this->refund($this->envelopeOf($sale, [self::line($line, '10000')], $this->drawer))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        }

        $this->assertSame($before, $this->factsAndRows());
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', RecordDrawerFundedCorrection::OPERATION)->count());
    }

    public function test_a_drawer_pays_at_most_its_expected_cash_reaching_rp0_and_is_never_switched(): void
    {
        // AC-034-03; BR-RFD-15, BR-RFD-16; D-045; G-05: Rina's drawer, opened with Rp50.000, takes a CASH Sale
        // of Rp30.000: expected Rp80.000. A refund of Rp100.000 from it is refused DRAWER_CASH_INSUFFICIENT
        // with the drawer and its expected cash, nothing written; Rp80.000 leaves Rp0; Rp1 more is refused at
        // Rp0. The server never pays from another drawer: Dewi's pays only when the Owner names it.
        TokoContoh::sell('Rina', $this->drawer, [self::sold($this->pen, '1')]);
        $sale = TokoContoh::sell('Dewi', $this->other, [self::sold($this->pen, '4')]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $this->as('Hendra');
        $this->confirm();
        $before = $this->factsAndRows();

        $short = $this->refund($this->envelopeOf($sale, [self::line($line, '100000')], $this->drawer))->assertStatus(422)->assertJsonPath('code', 'DRAWER_CASH_INSUFFICIENT')->assertJsonPath('messageKey', 'ux.state.drawer_short');
        $this->assertJsonChannel($short, 'commands.refused.drawer_short', 'owner');
        $this->assertSame(['laci' => 'Laci 1', 'uang' => 'Rp80.000'], $short->json('params'));
        $this->assertSame($before, $this->factsAndRows());

        $this->refund($this->envelopeOf($sale, [self::line($line, '80000')], $this->drawer))->assertStatus(201);
        $this->refund($this->envelopeOf($sale, [self::line($line, '1')], $this->drawer))->assertStatus(422)->assertJsonPath('code', 'DRAWER_CASH_INSUFFICIENT')->assertJsonPath('params.uang', 'Rp0');
        $this->refund($this->envelopeOf($sale, [self::line($line, '1')], $this->other))->assertStatus(201);
        $this->assertSame([[1, $this->other], [80000, $this->drawer]], $this->rowsOf('refunds', ['amount', 'cash_session_id']));

        // Her close counts expected cash Rp0: the opening cash, her CASH Sale, less the refund (BR-CASH-12).
        TokoContoh::closeSession($this->drawer);
        $this->assertSame([0, 0, 30000, 80000], array_values((array) $this->watcher()->table('cash_session_reports')->where('session_id', $this->drawer)->first(['expected_cash', 'variance', 'cash_applied', 'drawer_refund_total'])));
    }

    public function test_a_closed_session_accepts_no_payout_and_a_session_that_does_not_exist_is_not_found(): void
    {
        // AC-034-02; BR-RFD-09, BR-CASH-19; RACE-13's sequential form: a refund naming a CLOSED session is
        // refused SESSION_NOT_OPEN with the refund's message before any fact; one naming no session is
        // NOT_FOUND. Neither changes the closed session or its report.
        $sale = TokoContoh::sell('Dewi', $this->other, [self::sold($this->pen, '1')]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        TokoContoh::closeSession($this->drawer);
        $this->as('Hendra');
        $this->confirm();
        $before = $this->factsAndRows();

        $closed = $this->refund($this->envelopeOf($sale, [self::line($line, '10000')], $this->drawer))->assertStatus(422)->assertJsonPath('code', 'SESSION_NOT_OPEN')->assertJsonPath('messageKey', 'ux.state.session_closed.refund');
        $this->assertJsonChannel($closed, 'commands.refused', 'owner');
        $missing = $this->refund($this->envelopeOf($sale, [self::line($line, '10000')], 999999))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->assertJsonChannel($missing, 'commands.refused', 'owner');
        $this->assertSame($before, $this->factsAndRows());
    }

    public function test_the_session04_example_with_a_drawer_funded_refund_closes_at_minus_rp2000_and_a_refund_after_the_preview_is_recounted(): void
    {
        // AC-034-06; SALES_CASH section 6.1's example under D-035; BR-CASH-12 to BR-CASH-14, BR-CASH-20; G-05:
        // opening Rp20.000, CASH Rp100.000 and BANK_TRANSFER Rp50.000 in Rina's session, and a drawer-funded
        // refund of Rp10.000 from it: expected Rp110.000. A refund committed after she reads the preview moves
        // the version, so her close against it is refused RECOUNT_REQUIRED and the session stays OPEN; counted
        // again, Rp108.000 gives −Rp2.000, and the report keeps the refund apart, with the Owner as actor.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => TokoContoh::category('Buku'), 'name' => 'Buku Tulis', 'sellingPrice' => '50000']);
        TokoContoh::stock($book, '10', '100000');
        TokoContoh::closeSession($this->other);
        $session = TokoContoh::openSession('Dewi', TokoContoh::register('LACI-3', 'Laci 3'), '20000');
        $this->travelTo('2026-11-14T03:00:00.000000Z');
        $cash = TokoContoh::sell('Dewi', $session, [self::sold($book, '2')]);
        TokoContoh::sell('Dewi', $session, [self::sold($book, '1')], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-1']);
        $line = TokoContoh::saleLinesOf($cash)[0] ?? 0;
        $preview = self::number($this->watcher()->table('cash_sessions')->where('id', $session)->value('activity_version'));

        // The refund after the preview: Rp10.000 from her drawer, at 11:00 in Asia/Jakarta.
        $this->travelTo('2026-11-14T04:00:00.000000Z');
        $refund = TokoContoh::refundFromDrawer($cash, $session, [['saleLineId' => $line, 'refundAmount' => '10000']]);
        $this->as('Dewi');
        $this->postJson('/commands/cash_session.close', $this->envelope(['sessionId' => $session, 'physicalCash' => '108000'], ['operation' => 'cash_session.close', 'expected' => ['activityVersion' => $preview]]))->assertStatus(409)->assertJsonPath('code', 'RECOUNT_REQUIRED');
        $this->assertSame(['OPEN', $preview + 1, 0], [$this->watcher()->table('cash_sessions')->where('id', $session)->value('status'), $this->watcher()->table('cash_sessions')->where('id', $session)->value('activity_version'), $this->watcher()->table('cash_session_closes')->where('session_id', $session)->count()]);

        $this->postJson('/commands/cash_session.close', $this->envelope(['sessionId' => $session, 'physicalCash' => '108000'], ['operation' => 'cash_session.close', 'expected' => ['activityVersion' => $preview + 1]]))->assertStatus(201);
        $report = (array) $this->watcher()->table('cash_session_reports')->where('session_id', $session)->sole();
        $this->assertSame([20000, 100000, 50000, 10000, 110000, 108000, -2000], [$report['opening_cash'], $report['cash_applied'], $report['bank_transfer_applied'], $report['drawer_refund_total'], $report['expected_cash'], $report['physical_cash'], $report['variance']]);
        $saleNumber = self::text($this->watcher()->table('sales')->where('id', $cash)->value('sale_number'));
        $this->assertSame([['amount' => '10000', 'owner' => 'Hendra', 'recordedAt' => '2026-11-14T04:00:00.000000Z', 'reference' => $saleNumber.'/1', 'saleNumber' => $saleNumber]], self::dataOf($report['drawer_refunds']));
        $sources = self::dataOf($report['source_ids']);
        $this->assertSame([self::number($this->watcher()->table('refunds')->where('correction_id', $refund)->value('id'))], $sources['drawerRefundIds'] ?? null);
        $this->assertSame([110000, 108000, -2000], array_values((array) $this->watcher()->table('cash_session_closes')->where('session_id', $session)->first(['expected_cash', 'physical_cash', 'variance'])));
    }

    public function test_the_d068_scenario_closes_with_a_variance_of_plus_r_creates_nothing_and_keeps_refusing_a_payout(): void
    {
        // AC-034-05; D-068; BR-CASH-19, BR-RFD-09, BR-RFD-13, BR-RFD-17; WF-08, WF-10; RACE-13: a refund of
        // R = Rp20.000 from Rina's drawer commits before her close preview, and its cash is still in the drawer
        // when she counts: Rp50.000 opening + Rp30.000 CASH − R expected is Rp60.000, and she counts Rp80.000.
        // The session closes with a variance of +R and its report as recorded, the refund in it; no fact,
        // column or cash adjustment is created for the late handover; a payout against the CLOSED session
        // stays refused.
        TokoContoh::sell('Rina', $this->drawer, [self::sold($this->pen, '1')]);
        $sale = TokoContoh::sell('Dewi', $this->other, [self::sold($this->pen, '2')]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        TokoContoh::refundFromDrawer($sale, $this->drawer, [['saleLineId' => $line, 'refundAmount' => '20000']]);
        $counts = $this->tableCounts();

        TokoContoh::closeSession($this->drawer, '80000');

        $this->assertSame([60000, 80000, 20000, 20000], array_values((array) $this->watcher()->table('cash_session_reports')->where('session_id', $this->drawer)->first(['expected_cash', 'physical_cash', 'variance', 'drawer_refund_total'])));
        // Only the close and its report are new: no refund, payout, movement or adjustment.
        $this->assertSame([...$counts, 'cash_session_closes' => $counts['cash_session_closes'] + 1, 'cash_session_reports' => $counts['cash_session_reports'] + 1, 'command_identities' => $counts['command_identities'] + 1, 'command_results' => $counts['command_results'] + 1, 'audit_events' => $counts['audit_events'] + 1], $this->tableCounts());

        $this->as('Hendra');
        $this->confirm();
        $this->refund($this->envelopeOf($sale, [self::line($line, '20000')], $this->drawer))->assertStatus(422)->assertJsonPath('code', 'SESSION_NOT_OPEN');
        $this->assertSame(1, $this->watcher()->table('refunds')->count());
    }

    public function test_each_refusal_before_any_lock_writes_nothing(): void
    {
        // RC-05, "Before locks"; TR-VAL-01 to TR-VAL-07: the envelope's allowlist — RC-06's payout fields, a
        // funding source or an amount refused —; the payout required with its session, a JSON whole number;
        // RC-05 refunds money above zero; at least one line; a Sale line named twice. Each writes nothing.
        $sale = TokoContoh::sell('Dewi', $this->other, [self::sold($this->pen, '2')]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $this->as('Hendra');
        $this->confirm();
        $before = $this->factsAndRows();
        $valid = $this->envelopeOf($sale, [self::line($line, '10000')], $this->drawer);
        $payload = (array) ($valid['payload'] ?? []);

        foreach ([
            ['payout' => ['sessionId' => $this->drawer, 'method' => 'CASH']],
            ['payout' => ['sessionId' => $this->drawer, 'reference' => 'TRF-1']],
            ['payout' => ['sessionId' => $this->drawer, 'fundingSource' => 'DRAWER']],
            ['payout' => ['sessionId' => (string) $this->drawer]],
            ['amount' => '10000'],
        ] as $changes) {
            $this->refund([...$valid, 'payload' => [...$payload, ...$changes]])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        foreach ([
            [[self::line($line, '10000')], null, 'payload.payout', 'ux.validation.required'],
            [[self::line($line, '10000')], ['sessionId' => 0], 'payload.payout.sessionId', 'ux.validation.choose_one'],
            [[self::line($line, '10000')], [], 'payload.payout', 'ux.validation.required'],
            [[], ['sessionId' => $this->drawer], 'payload.lines', 'ux.validation.lines_min'],
            [[self::line($line)], ['sessionId' => $this->drawer], 'payload.lines', 'ux.validation.lines_min'],
        ] as [$lines, $payout, $field, $key]) {
            $this->refund($this->envelopeOf($sale, $lines, null, $payout))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')->assertJsonPath('fields', [$field => [['key' => $key, 'params' => []]]]);
        }

        // A return without money is RC-06's: RC-05 refuses it without a field, since no page sends it.
        $this->refund($this->envelopeOf($sale, [self::line($line, '0', '1')], $this->drawer))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')->assertJsonPath('fields', []);
        $repeated = $this->refund($this->envelopeOf($sale, [self::line($line, '1000'), self::line($line, '2000')], $this->drawer))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertJsonChannel($repeated, 'commands.refused.sale_lines', 'owner');
        $this->assertSame($before, $this->factsAndRows());
    }

    public function test_an_envelope_is_refused_as_every_command_refuses_one_and_a_repeat_answers_the_refund(): void
    {
        // CMD01; G-12, G-25; ADR-007 section 3; RACE-01 and RACE-02 in sequence (their races are
        // DrawerRefundRaceTest's): an earlier epoch is held; the refund runs once; another payload under
        // the same identity is held; another user may not check the Owner's intent.
        $sale = TokoContoh::sell('Dewi', $this->other, [self::sold($this->pen, '2')]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $this->as('Hendra');
        $this->confirm();
        $envelope = $this->envelopeOf($sale, [self::line($line, '10000')], $this->drawer);
        $before = $this->factsAndRows();

        $this->refund([...$envelope, 'sourceIntentId' => (string) Str::uuid(), 'recoveryEpoch' => str_repeat('ab', 32)])->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE')->assertJsonPath('outcome', 'HELD');
        $this->assertSame($before, $this->factsAndRows());

        $correction = self::number($this->refund($envelope)->assertStatus(201)->json('result.id'));
        $facts = $this->factsAndRows();
        $other = (array) ($this->envelopeOf($sale, [self::line($line, '20000')], $this->drawer)['payload'] ?? []);

        $this->refund([...$envelope, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $correction);
        $this->refund([...$envelope, 'payload' => $other])->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT')->assertJsonPath('outcome', 'HELD');
        $this->assertSame($facts, $this->factsAndRows());

        $this->as('Rina');
        $this->check($envelope)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_a_failure_after_every_write_step_leaves_nothing(): void
    {
        // AC-034-02, AC-034-04; TS-21; BR-RFD-10: a drawer-funded refund of one line with a saleable return
        // writes 13 steps — the correction, the refund, its line, the return line, the Sale's running total,
        // the line's running total, the pen's balance and valuation, its RETURN_RECEIPT, its return allocation,
        // the session's activity version, the audit event and the result. A failure after each leaves
        // nothing, the session's version among it, and its receipt is not marked committed (TS-21; review
        // R-02).
        $sale = TokoContoh::sell('Dewi', $this->other, [self::sold($this->pen, '2')]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $lines = [self::line($line, '30000', '1')];
        $this->as('Hendra');
        $this->confirm();

        for ($step = 1; $step <= 13; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $before = $this->factsAndRows();

            $this->refund($this->envelopeOf($sale, $lines, $this->drawer))->assertStatus(500);

            $this->assertSame($before, $this->factsAndRows(), sprintf('after write step %d', $step));
            $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', RecordDrawerFundedCorrection::OPERATION)->orderByDesc('id')->value('outcome'), (string) $step);
        }

        // The step after the last is no step of the command: it commits, through the locks of TR §7.1 in
        // their order — the session's at step 4, before the Sale's.
        $points = new FailurePoints(14);
        $this->app->instance(CommandPoints::class, $points);
        $this->refund($this->envelopeOf($sale, $lines, $this->drawer))->assertStatus(201);
        $this->assertSame(['after_lock:2.1', 'after_lock:2.3', 'after_lock:4', 'after_lock:5', 'after_lock:6', 'after_lock:7', ...array_map(static fn (int $step): string => 'after_write:'.$step, range(1, 13))], array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))));
        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->assertSame([1, 1], [$this->watcher()->table('sale_corrections')->count(), $this->watcher()->table('cash_sessions')->where('id', $this->drawer)->value('activity_version')]);
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
     * A line of RC-05: the money refunded on a Sale line and the quantities returned, saleable and not.
     *
     * @return array{saleLineId: int, refundAmount: string, returnedSaleable: string, returnedNotSaleable: string}
     */
    private static function line(int $saleLine, string $refund = '0', string $saleable = '0', string $notSaleable = '0'): array
    {
        return ['saleLineId' => $saleLine, 'refundAmount' => $refund, 'returnedSaleable' => $saleable, 'returnedNotSaleable' => $notSaleable];
    }

    /**
     * An envelope of RC-05 for these lines of this Sale, paid from this session's drawer, or with this payout.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>|null  $payout  the payout as sent, absent when null and no session is named
     * @return array<string, mixed>
     */
    private function envelopeOf(int $sale, array $lines, ?int $session, ?array $payout = null): array
    {
        $sent = $session === null ? $payout : ['sessionId' => $session];

        return $this->envelope(['saleId' => $sale, 'lines' => $lines, 'reason' => self::REASON, ...($sent === null ? [] : ['payout' => $sent])], ['operation' => RecordDrawerFundedCorrection::OPERATION]);
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function refund(array $envelope): TestResponse
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
     * How many rows each table of a refund and a close holds.
     *
     * @return array<string, int>
     */
    private function tableCounts(): array
    {
        $counts = [];

        foreach (self::FACTS as $table) {
            $counts[$table] = $this->watcher()->table($table)->count();
        }

        return $counts;
    }

    /**
     * The counts, with the rows that a refund changes: the balances, the valuations, the running totals and
     * the sessions.
     *
     * @return array<string, mixed>
     */
    private function factsAndRows(): array
    {
        $rows = [];

        foreach (['stock_balances' => 'product_id', 'inventory_valuations' => 'product_id', 'sale_correction_totals' => 'sale_id', 'sale_line_correction_totals' => 'sale_line_id', 'cash_sessions' => 'id'] as $table => $key) {
            $rows[$table] = array_values($this->watcher()->table($table)->orderBy($key)->get()->map(static fn (object $row): array => (array) $row)->all());
        }

        return ['facts' => $this->tableCounts(), 'rows' => $rows];
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

    /** One independent session for every read of the test, so that the test stays within the server's connections. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
