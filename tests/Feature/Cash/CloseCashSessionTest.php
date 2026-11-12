<?php

namespace Tests\Feature\Cash;

use App\Actions\Cash\CloseCashSession;
use App\Actions\Commands\CommandContext;
use App\Actions\Sales\CheckoutSale;
use App\Database\CommandPoints;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Operations;
use App\Support\Time\Clock;
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
 * RC-03 `cash_session.close` through its route and its Action (AC-030-01, AC-030-03 to AC-030-06;
 * INTERACTION_CONTRACTS RC-03 and section 2.5; TECHNICAL_RULES section 7.2, family Close; SCHEMA
 * section 5.10; BR-CASH-12 to BR-CASH-20; D-088, D-089; G-05, G-17, G-19, G-27; CAP-21; EXECUTION_CONTEXT
 * section 10, a command; suites SESSION01, SESSION04, SESSION05, SESSION06 and CMD01; TASK-030 section
 * 7, L2 and L4): the close of the example of AC-030-01 and its report, agreeing with the facts it froze;
 * a blank or malformed count refused and Rp0 valid; another user's session NOT_FOUND, a CLOSED one
 * SESSION_NOT_OPEN, a moved activity version RECOUNT_REQUIRED; an unresolved checkout of the user —
 * PENDING, HELD, of an earlier epoch — refusing until a status check of the user or the Owner settles it;
 * the failure after every write step, which leaves the session OPEN; repeats that answer the one close;
 * two sessions on one business date and one across midnight; the roles.
 */
final class CloseCashSessionTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string COMMAND = '/commands/cash_session.close';

    /** The tables that a refused or failed close leaves as they were. */
    private const array FACTS = ['cash_sessions', 'cash_session_closes', 'cash_session_reports', 'command_identities', 'command_results', 'audit_events', 'sales'];

    private int $register = 0;

    /** Rp50.000 a unit, counted whole. */
    private int $product = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->register = TokoContoh::register('LACI-1', 'Laci 1');
        $this->product = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'sellingPrice' => '50000']);
        TokoContoh::stock($this->product, '50', '500000');
    }

    public function test_a_cashier_closes_her_session_and_its_report_freezes_what_she_counted(): void
    {
        // AC-030-01, AC-030-06; BR-CASH-12, BR-CASH-13, BR-CASH-20; G-05, G-17: opening Rp20.000, CASH
        // Rp100.000 and BANK_TRANSFER Rp50.000, Rp118.000 counted: expected Rp120.000 and a variance of
        // −Rp2.000; BANK_TRANSFER never changes expected cash, and neither the opening cash nor the
        // variance is a sale.
        $this->travelTo('2026-11-11T02:00:00.000000Z');
        $session = TokoContoh::openSession('Rina', $this->register, '20000');
        $cash = TokoContoh::sell('Rina', $session, [self::line($this->product, '2')]);
        $transfer = TokoContoh::sell('Rina', $session, [self::line($this->product, '1')], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-1']);
        $this->travelTo('2026-11-11T10:30:00.500000Z');
        $this->as('Rina');
        $rina = $this->people['Rina']['id'];
        $number = self::text($this->watcher()->table('cash_sessions')->where('id', $session)->value('session_number'));

        $envelope = $this->envelopeOf($session, '118000', 2);
        $response = $this->close($envelope)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed.number', 'cashier');
        $commandId = self::number($response->json('commandId'));

        // Its location is the session's report (RP-206).
        $this->assertSame(['cash_session_close', $session, $number, '/cash-sessions/'.$session], [$response->json('result.type'), $response->json('result.id'), $response->json('result.number'), $response->json('result.location')]);
        $this->assertSame(['CLOSED', 2, $session], array_values((array) $this->watcher()->table('cash_sessions')->where('id', $session)->first(['status', 'activity_version', 'close_presence'])));

        $close = (array) $this->watcher()->table('cash_session_closes')->where('session_id', $session)->sole();
        $this->assertSame(
            ['CLOSED', 'OWN', $rina, 'Rina', null, 2, 118000, 120000, -2000, $commandId],
            [$close['session_status'], $close['close_kind'], $close['close_actor_user_id'], $close['close_actor_display_name'], $close['close_reason'], $close['activity_version_at_close'], $close['physical_cash'], $close['expected_cash'], $close['variance'], $close['command_id']],
        );
        $this->assertSame(['2026-11-11T10:30:00.500000', '2026-11-11T02:00:00.000000', '2026-11-11T10:30:00.500000'], $this->instantsOf($session));

        // The report of DC-02: the frozen identity, names and register, the totals per method, the
        // drawer-funded refunds apart — none before TASK-034 — and the keys of its sources.
        $report = (array) $this->watcher()->table('cash_session_reports')->where('session_id', $session)->sole();
        $this->assertSame(
            [$number, 20000, 'Rina', $this->currentRevision(), null, 'session_report.1', 'LACI-1', 'Laci 1', 2, 118000, 120000, -2000, 'Rina', 2, 150000, 100000, 50000, 0, null, null],
            [
                $report['session_number'], $report['opening_cash'], $report['responsible_display_name'], $report['business_profile_revision_id'], $report['logo_file_id'],
                $report['template_version'], $report['register_code'], $report['register_label'], $report['cutoff_activity_version'], $report['physical_cash'],
                $report['expected_cash'], $report['variance'], $report['close_actor_display_name'], $report['completed_sale_count'], $report['completed_sale_value'],
                $report['cash_applied'], $report['bank_transfer_applied'], $report['drawer_refund_total'], $report['close_reason'], $report['notes'],
            ],
        );
        $payments = $this->watcher()->table('payments')->whereIn('sale_id', [$cash, $transfer])->orderBy('sale_id')->pluck('id')->all();
        $this->assertSame([], self::dataOf($report['drawer_refunds']));
        $this->assertSame(['drawerRefundIds' => [], 'paymentIds' => $payments, 'saleIds' => [$cash, $transfer]], self::dataOf($report['source_ids']));

        // G-17: the report agrees with the facts it froze.
        $this->assertSame([2, 150000, 100000, 50000], array_values((array) $this->watcher()->selectOne(<<<'SQL'
            select count(*) as sales, sum(s.total_amount)::bigint as total,
                (sum(p.applied_amount) filter (where p.method = 'CASH'))::bigint as cash,
                (sum(p.applied_amount) filter (where p.method <> 'CASH'))::bigint as transfer
            from sales s join payments p on p.sale_id = s.id where s.cash_session_id = ?
            SQL, [$session])));

        // The audit event in the same transaction, naming its session (BR-CASH-19).
        $audit = (array) $this->watcher()->table('audit_events')->where('action', 'CASH_SESSION_CLOSE')->sole();
        $this->assertSame(['BUSINESS', 'USER', $rina, 'Rina', 'cash_session_close', $session, $session, $commandId], [$audit['category'], $audit['actor_kind'], $audit['actor_user_id'], $audit['actor_display_name'], $audit['subject_type'], $audit['subject_id'], $audit['cash_session_id'], $audit['command_id']]);
        $this->assertSame(
            ['cashApplied' => '100000', 'cutoffVersion' => 2, 'expectedCash' => '120000', 'number' => $number, 'openingCash' => '20000', 'physicalCash' => '118000', 'register' => 'LACI-1', 'saleCount' => 2, 'transferApplied' => '50000', 'variance' => '-2000'],
            self::dataOf($audit['change_data']),
        );
        $this->assertSame('COMMITTED', $this->watcher()->table('command_request_receipts')->where('operation', CloseCashSession::OPERATION)->value('outcome'));

        // The same request again answers the original result, and a status check rebuilds it (RC-50).
        $this->close($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $session)->assertJsonPath('result.number', $number);
        $resolved = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.number', $number)->assertJsonPath('result.location', '/cash-sessions/'.$session);
        $this->assertJsonChannel($resolved, 'commands.resolve.number', 'cashier');

        // AC-030-06: a later session and its Sales leave the report as it was frozen.
        $frozen = $report;
        $later = TokoContoh::openSession('Rina', $this->register);
        TokoContoh::sell('Rina', $later, [self::line($this->product, '1')]);
        TokoContoh::closeSession($later);
        $this->assertSame($frozen, (array) $this->watcher()->table('cash_session_reports')->where('session_id', $session)->sole());
    }

    public function test_a_blank_or_malformed_count_or_envelope_is_refused_and_a_count_of_rp0_is_valid(): void
    {
        // RC-03; TR-VAL-03, TR-VAL-06; COPY_DECK section 16.1: a blank amount has its own key; a number,
        // a decimal, a separator, a sign or a value above the limit is refused, never repaired. The
        // server derives expected cash, the totals and the variance and takes none of them, nor a
        // reason, which only RC-04 takes.
        $session = TokoContoh::openSession('Rina', $this->register, '20000');
        $this->as('Rina');
        $before = $this->facts();

        foreach ([
            [['physicalCash' => ''], 'ux.validation.money_blank'],
            [['physicalCash' => null], 'ux.validation.money_blank'],
            [['physicalCash' => '1.500'], 'ux.validation.money'],
            [['physicalCash' => '15000.5'], 'ux.validation.money'],
            [['physicalCash' => '1e3'], 'ux.validation.money'],
            [['physicalCash' => '-5'], 'ux.validation.money_min'],
            [['physicalCash' => '1000000000000'], 'ux.validation.money_max'],
            [['physicalCash' => 20000], 'ux.validation.money'],
        ] as $index => [$payload, $key]) {
            $refused = $this->close($this->envelope([...['sessionId' => $session, 'physicalCash' => '0'], ...$payload], ['operation' => CloseCashSession::OPERATION, 'expected' => ['activityVersion' => 0]]))
                ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $fields = (array) $refused->json('fields');
            $this->assertSame(['key' => $key, 'params' => []], ((array) ($fields['payload.physicalCash'] ?? []))[0] ?? null, (string) $index);
        }

        foreach ([
            ['sessionId' => $session],
            ['sessionId' => (string) $session, 'physicalCash' => '0'],
            ['sessionId' => 0, 'physicalCash' => '0'],
            ['sessionId' => $session, 'physicalCash' => '0', 'expectedCash' => '20000'],
            ['sessionId' => $session, 'physicalCash' => '0', 'variance' => '-20000'],
            ['sessionId' => $session, 'physicalCash' => '0', 'reason' => 'Ditutup'],
            ['sessionId' => $session, 'physicalCash' => '0', 'responsibleUserId' => $this->people['Dewi']['id']],
        ] as $payload) {
            $this->close($this->envelope($payload, ['operation' => CloseCashSession::OPERATION, 'expected' => ['activityVersion' => 0]]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // The activity version of the preview is required, a JSON whole number of zero or more.
        foreach ([(object) [], ['activityVersion' => '0'], ['activityVersion' => -1], ['activityVersion' => 1.5], ['activityVersion' => 0, 'totals' => '0']] as $expected) {
            $this->close($this->envelope(['sessionId' => $session, 'physicalCash' => '0'], ['operation' => CloseCashSession::OPERATION, 'expected' => $expected]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($before, $this->facts());

        // Rp0 is a count: the variance is then minus expected cash (BR-CASH-12).
        $this->close($this->envelopeOf($session, '0', 0))->assertStatus(201);
        $this->assertSame([0, 20000, -20000], array_values((array) $this->watcher()->table('cash_session_closes')->where('session_id', $session)->first(['physical_cash', 'expected_cash', 'variance'])));
    }

    public function test_another_users_session_is_not_found_a_closed_one_is_not_open_and_a_moved_version_needs_a_recount(): void
    {
        // SEC-AZ-03, TR-ERR-03; G-19: an own close finds only the actor's own session; another user's,
        // the Owner's included — whose close of it is RC-04 of TASK-032 — is NOT_FOUND like a missing one.
        $rina = TokoContoh::openSession('Rina', $this->register);
        $dewi = TokoContoh::openSession('Dewi', TokoContoh::register('LACI-2', 'Laci 2'));
        $before = $this->facts();

        $this->as('Rina');
        $this->close($this->envelopeOf($dewi, '0', 0))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->close($this->envelopeOf(999999, '0', 0))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->as('Hendra');
        $this->close($this->envelopeOf($rina, '0', 0))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        // The combined account, with an OPEN session of its own, likewise (review R-15).
        $this->as('Dewi');
        $this->close($this->envelopeOf($rina, '0', 0))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->assertSame($before, $this->facts());

        // BR-CASH-14, RACE-08 in sequence: a Sale after the preview moves the activity version; the close
        // against the earlier one is refused before any write and the session stays OPEN.
        TokoContoh::sell('Rina', $rina, [self::line($this->product, '1')]);
        $before = $this->facts();
        $this->as('Rina');
        $recount = $this->close($this->envelopeOf($rina, '50000', 0))->assertStatus(409)->assertJsonPath('code', 'RECOUNT_REQUIRED')->assertJsonPath('messageKey', 'ux.state.recount')->assertJsonPath('outcome', 'REJECTED');
        $this->assertJsonChannel($recount, 'commands.refused', 'cashier');
        $this->assertSame($before, $this->facts());

        // Counted again against the new version, it closes; any other close of the session then finds it
        // CLOSED, and a different count never overwrites (BR-CASH-17, RACE-09 in sequence).
        $this->close($this->envelopeOf($rina, '50000', 1))->assertStatus(201);
        $after = $this->facts();
        $closed = $this->close($this->envelopeOf($rina, '40000', 1))->assertStatus(422)->assertJsonPath('code', 'SESSION_NOT_OPEN')->assertJsonPath('messageKey', 'ux.state.session_closed.close');
        $this->assertJsonChannel($closed, 'commands.refused', 'cashier');
        $this->assertSame($after, $this->facts());
        $this->assertSame([50000, 0], array_values((array) $this->watcher()->table('cash_session_closes')->where('session_id', $rina)->first(['physical_cash', 'variance'])));
    }

    public function test_an_unresolved_checkout_refuses_the_close_until_a_status_check_of_the_user_or_the_owner_settles_it(): void
    {
        // AC-030-03; G-27, D-088, D-089; RACE-35 in sequence (its races are CloseRaceTest's): the close
        // reads the responsible user's `sale.checkout` receipts after the session's lock. The request
        // carries nothing of the client's list of unresolved requests, so the refusal holds when that
        // list is stale.
        $session = TokoContoh::openSession('Rina', $this->register);
        $this->as('Rina');

        // A checkout whose answer was lost: its receipt stays PENDING.
        $this->app->instance(CommandPoints::class, new FailurePoints(1));
        $lost = $this->checkoutOf($session, '1', '50000');
        $this->postJson('/commands/sale.checkout', $lost)->assertStatus(500);
        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->assertSame(['PENDING'], $this->outcomesOf($lost));

        $before = $this->facts();
        $refused = $this->close($this->envelopeOf($session, '0', 0))->assertStatus(409)->assertJsonPath('code', 'RECOUNT_REQUIRED')->assertJsonPath('messageKey', 'ux.state.recount.unresolved_checkouts');
        $this->assertJsonChannel($refused, 'commands.refused', 'cashier');
        $this->assertSame($before, $this->facts());

        // The user's own status check settles it NOT_COMMITTED with its fence.
        $this->check($lost)->assertOk()->assertJsonPath('outcome', 'NOT_COMMITTED');
        $this->assertSame(['NOT_COMMITTED'], $this->outcomesOf($lost));

        // A checkout that committed, then another payload under its key: the repeat is HELD.
        $sold = $this->checkoutOf($session, '1', '50000');
        $this->postJson('/commands/sale.checkout', $sold)->assertStatus(201);
        $this->postJson('/commands/sale.checkout', [...$sold, 'payload' => ['sessionId' => $session, 'lines' => [self::line($this->product, '2')], 'payment' => ['method' => 'CASH', 'tendered' => '100000']]])
            ->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT')->assertJsonPath('outcome', 'HELD');
        $this->close($this->envelopeOf($session, '50000', 1))->assertStatus(409)->assertJsonPath('messageKey', 'ux.state.recount.unresolved_checkouts');

        // The Owner's status check settles it COMMITTED (CAP-34), naming the Owner as its checker.
        $this->as('Hendra');
        $this->check($sold)->assertOk()->assertJsonPath('outcome', 'COMMITTED');
        $this->assertSame([['COMMITTED', null], ['COMMITTED', $this->people['Hendra']['id']]], array_map(static fn (array $row): array => [$row['outcome'], $row['settled_by_user_id']], $this->receiptsOf($sold)));

        // A checkout sent with an earlier epoch is HELD too, and counts (D-088); a status check of it
        // answers EPOCH_STALE and settles nothing, so the close stays refused.
        $this->as('Rina');
        $stale = [...$this->checkoutOf($session, '1', '50000'), 'recoveryEpoch' => str_repeat('ab', 32)];
        $this->postJson('/commands/sale.checkout', $stale)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE')->assertJsonPath('outcome', 'HELD');
        $this->close($this->envelopeOf($session, '50000', 1))->assertStatus(409)->assertJsonPath('messageKey', 'ux.state.recount.unresolved_checkouts');
        $this->check($stale)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->close($this->envelopeOf($session, '50000', 1))->assertStatus(409)->assertJsonPath('messageKey', 'ux.state.recount.unresolved_checkouts');

        // Once its intent committed in the current epoch, a status check settles its earlier receipt
        // COMMITTED, and the close, counted against the new version, succeeds.
        $this->postJson('/commands/sale.checkout', [...$stale, 'recoveryEpoch' => $this->epoch, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(201);
        $this->check([...$stale, 'recoveryEpoch' => $this->epoch])->assertOk()->assertJsonPath('outcome', 'COMMITTED');
        $this->assertSame(['COMMITTED', 'COMMITTED'], $this->outcomesOf($stale));
        $this->close($this->envelopeOf($session, '100000', 2))->assertStatus(201);
        $this->assertSame([2, 100000, 0], array_values((array) $this->watcher()->table('cash_session_reports')->where('session_id', $session)->first(['completed_sale_count', 'expected_cash', 'variance'])));
    }

    public function test_a_failure_after_every_write_step_leaves_the_session_open_and_every_repeat_answers_the_one_close(): void
    {
        // AC-030-04; TS-21, BR-CASH-17: five steps — the session's status, the close, the report, the
        // audit event, the result; there is no CLOSING state. A lost close is recovered by its status
        // check (RC-50), which answers NOT_COMMITTED and leaves the session OPEN.
        $session = TokoContoh::openSession('Rina', $this->register, '50000');
        $this->as('Rina');
        $envelope = [];

        for ($step = 1; $step <= 5; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $before = $this->facts();
            $envelope = $this->envelopeOf($session, '50000', 0);

            $this->close($envelope)->assertStatus(500);

            $this->assertSame($before, $this->facts(), sprintf('after write step %d', $step));
            $this->assertSame(['PENDING'], $this->outcomesOf($envelope), (string) $step);
        }

        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'NOT_COMMITTED');
        $this->assertSame('OPEN', $this->watcher()->table('cash_sessions')->where('id', $session)->value('status'));

        // The step after the last is no step of the command: it commits, through the locks of TR §7.2
        // in their order.
        $points = new FailurePoints(6);
        $this->app->instance(CommandPoints::class, $points);
        $envelope = $this->envelopeOf($session, '50000', 0);
        $committed = $this->close($envelope)->assertStatus(201);
        $this->assertSame(
            ['after_lock:2.1', 'after_lock:2.4', 'after_lock:4', 'after_write:1', 'after_write:2', 'after_write:3', 'after_write:4', 'after_write:5'],
            array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))),
        );
        $this->app->instance(CommandPoints::class, new FailurePoints);
        $number = self::text($committed->json('result.number'));

        // RACE-01 and RACE-02 in sequence (their races are CloseRaceTest's): the same request, with its
        // key or another, answers the one close; another count under its key is held; another intent
        // finds the session CLOSED. One close, one report, one result.
        $this->close($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $session);
        $this->close([...$envelope, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.number', $number);
        $this->close([...$envelope, 'payload' => ['sessionId' => $session, 'physicalCash' => '49000']])->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT')->assertJsonPath('outcome', 'HELD');
        $this->close($this->envelopeOf($session, '49000', 0))->assertStatus(422)->assertJsonPath('code', 'SESSION_NOT_OPEN');
        $this->assertSame([1, 1, 1, 50000], [
            $this->watcher()->table('cash_session_closes')->count(),
            $this->watcher()->table('cash_session_reports')->count(),
            $this->watcher()->table('command_results')->where('result_type', CloseCashSession::RESULT_TYPE)->count(),
            $this->watcher()->table('cash_session_closes')->where('session_id', $session)->value('physical_cash'),
        ]);

        // The result is recoverable after the close (RC-50).
        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.number', $number)->assertJsonPath('result.location', '/cash-sessions/'.$session);
    }

    public function test_two_sessions_on_one_business_date_give_two_reports_and_one_across_midnight_stays_one_document(): void
    {
        // AC-030-05; SCH-22, G-18: closing and opening again on 11 November gives two sessions with a
        // report each; a session opened at 23:30 in Asia/Jakarta keeps its number and its one report
        // with the Sales before and after midnight, closed on 12 November. Through the Actions, the
        // sign-in sessions being no part of it.
        $this->travelTo('2026-11-11T01:00:00.000000Z');
        $first = TokoContoh::openSession('Rina', $this->register);
        TokoContoh::sell('Rina', $first, [self::line($this->product, '1')]);
        $this->travelTo('2026-11-11T05:00:00.000000Z');
        TokoContoh::closeSession($first);
        $second = TokoContoh::openSession('Rina', $this->register);
        $this->travelTo('2026-11-11T09:00:00.000000Z');
        TokoContoh::closeSession($second);

        $this->travelTo('2026-11-11T16:30:00.000000Z');
        $third = TokoContoh::openSession('Rina', $this->register);
        TokoContoh::sell('Rina', $third, [self::line($this->product, '1')]);
        $this->travelTo('2026-11-11T17:30:00.000000Z');
        TokoContoh::sell('Rina', $third, [self::line($this->product, '2')]);
        $this->travelTo('2026-11-11T18:00:00.000000Z');
        TokoContoh::closeSession($third);

        $reports = [];

        foreach ($this->watcher()->select(<<<'SQL'
            select r.session_id, left(r.session_number, 8) as opened_date, r.completed_sale_count, r.completed_sale_value,
                ((r.closed_at at time zone 'Asia/Jakarta')::date)::text as closed_date
            from cash_session_reports r order by r.session_id
            SQL) as $row) {
            $reports[] = array_values((array) $row);
        }

        $this->assertSame([
            [$first, '20261111', 1, 50000, '2026-11-11'],
            [$second, '20261111', 0, 0, '2026-11-11'],
            [$third, '20261111', 2, 150000, '2026-11-12'],
        ], $reports);
        $this->assertSame(['2026-11-11', '2026-11-12'], $this->watcher()->table('sales')->where('cash_session_id', $third)->orderBy('id')->pluck('business_date')->all());
    }

    public function test_the_owner_a_cashier_and_the_combined_account_close_their_own_and_operations_never(): void
    {
        // CAP-21 for the Owner, a cashier and the combined account, each on their own session; no recent
        // authentication (INTERACTION_CONTRACTS section 1.5). Operations is refused at the gate, with no
        // receipt, and by the guard in the transaction (SEC-AZ-04); a visitor is not signed in.
        foreach (['Hendra' => 'LACI-H', 'Dewi' => 'LACI-D', 'Rina' => 'LACI-R'] as $person => $code) {
            $session = TokoContoh::openSession($person, TokoContoh::register($code, 'Laci '.$person));
            $this->as($person);
            $this->close($this->envelopeOf($session, '0', 0))->assertStatus(201);
            $this->assertSame([$this->people[$person]['id'], $person], array_values((array) $this->watcher()->table('cash_session_closes')->where('session_id', $session)->first(['close_actor_user_id', 'close_actor_display_name'])), $person);
        }

        $session = TokoContoh::openSession('Rina', $this->register);
        $this->as('Agus');
        $this->close($this->envelopeOf($session, '0', 0))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', CloseCashSession::OPERATION)->where('actor_user_id', $this->people['Agus']['id'])->count());

        try {
            app(CloseCashSession::class)->execute(new CommandContext($this->people['Agus']['id'], $this->rawEnvelope($session, '0', 0), app(Clock::class)->now()));
            $this->fail('Operations passed the guard.');
        } catch (ActorNotAllowed) {
            $this->addToAssertionCount(1);
        }

        $this->browse(null);
        $this->close($this->envelopeOf($session, '0', 0))->assertStatus(401);
        $this->assertSame('OPEN', $this->watcher()->table('cash_sessions')->where('id', $session)->value('status'));
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
     * An envelope of RC-03 for this session, count and reviewed activity version.
     *
     * @return array<string, mixed>
     */
    private function envelopeOf(int $session, string $physicalCash, int $version): array
    {
        return $this->envelope(['sessionId' => $session, 'physicalCash' => $physicalCash], ['operation' => CloseCashSession::OPERATION, 'expected' => ['activityVersion' => $version]]);
    }

    private function rawEnvelope(int $session, string $physicalCash, int $version): Envelope
    {
        return new Envelope(CloseCashSession::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, ['activityVersion' => $version], ['sessionId' => $session, 'physicalCash' => $physicalCash]);
    }

    /**
     * An envelope of RC-01 of the product in this session, paid in CASH, at its price in force.
     *
     * @return array<string, mixed>
     */
    private function checkoutOf(int $session, string $quantity, string $tendered): array
    {
        $revision = self::number($this->watcher()->table('products')->where('id', $this->product)->value('current_price_revision_id'));

        return $this->envelope(
            ['sessionId' => $session, 'lines' => [self::line($this->product, $quantity)], 'payment' => ['method' => 'CASH', 'tendered' => $tendered]],
            ['operation' => CheckoutSale::OPERATION, 'expected' => ['lines' => [['priceRevisionId' => $revision]]]],
        );
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function close(array $envelope): TestResponse
    {
        return $this->postJson(self::COMMAND, $envelope);
    }

    /**
     * How many rows each fact table holds, and the sessions OPEN.
     *
     * @return array<string, mixed>
     */
    private function facts(): array
    {
        $facts = [];

        foreach (self::FACTS as $table) {
            $facts[$table] = $this->watcher()->table($table)->count();
        }

        $facts['open'] = $this->watcher()->table('cash_sessions')->where('status', 'OPEN')->orderBy('id')->pluck('id')->all();

        return $facts;
    }

    /** The BusinessProfile revision in force. */
    private function currentRevision(): int
    {
        return self::number($this->watcher()->table('business_profile_current')->where('id', 1)->value('revision_id'));
    }

    /**
     * The close's time, and the report's opened and closed times, of a session, in UTC to the microsecond.
     *
     * @return list<mixed>
     */
    private function instantsOf(int $session): array
    {
        return array_values((array) $this->watcher()->selectOne(<<<'SQL'
            select to_char(c.closed_at at time zone 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US') as closed_at,
                to_char(r.opened_at at time zone 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US') as report_opened_at,
                to_char(r.closed_at at time zone 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US') as report_closed_at
            from cash_session_closes c join cash_session_reports r on r.session_id = c.session_id
            where c.session_id = ?
            SQL, [$session]));
    }

    /**
     * Data of jsonb with its keys sorted, since jsonb keeps no order of its own.
     *
     * @return array<array-key, mixed>
     */
    private static function dataOf(mixed $json): array
    {
        $data = json_decode(is_string($json) ? $json : '', true);
        self::assertIsArray($data);
        ksort($data);

        return $data;
    }

    /** One independent session for every read of the test. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
