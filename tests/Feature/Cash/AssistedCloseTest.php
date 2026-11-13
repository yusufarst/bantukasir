<?php

namespace Tests\Feature\Cash;

use App\Actions\Access\ConsumeLink;
use App\Actions\Access\ReplaceOwner;
use App\Actions\Cash\CloseCashSessionAssisted;
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
 * RC-04 `cash_session.close_assisted` through its route and its Action (AC-032-01 to AC-032-03;
 * INTERACTION_CONTRACTS RC-04 and section 2.5; TECHNICAL_RULES section 7.2, family Close; SCHEMA section
 * 5.10; BR-CASH-11, BR-CASH-12 to BR-CASH-20; D-069 (d), D-087, D-088, D-089; G-17, G-19, G-27; CAP-23;
 * SEC-OP-03; EXECUTION_CONTEXT section 10, a command; suites SESSION10, SESSION05, AUTH03, CMD01, ACC01 and
 * OPPROC01; TASK-032 section 7, L4): the Owner closes a cashier's session with a count and a reason after
 * his recent authentication, the responsible user unchanged and the Owner recorded as the close actor;
 * validation; the Owner's own session and a missing one NOT_FOUND, a CLOSED one SESSION_NOT_OPEN, a moved
 * version RECOUNT_REQUIRED; the responsible user's unresolved checkout refusing until the Owner's status
 * check settles it; the failure after every write step; repeats; the session of a deactivated cashier and
 * that of an Owner replaced by OP-03, OPEN and unchanged until this close; the other roles refused.
 */
final class AssistedCloseTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string COMMAND = '/commands/cash_session.close_assisted';

    private const string REASON = 'Kasir pulang sakit sebelum menutup sesi.';

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

    public function test_the_owner_closes_a_cashiers_session_with_a_count_and_a_reason_and_she_stays_its_responsible_user(): void
    {
        // AC-032-01; BR-CASH-18: opening Rp20.000, CASH Rp100.000, BANK_TRANSFER Rp50.000, Rp118.000
        // counted: expected Rp120.000 and a variance of −Rp2.000, as an own close derives them.
        $this->travelTo('2026-11-13T02:00:00.000000Z');
        $session = TokoContoh::openSession('Rina', $this->register, '20000');
        TokoContoh::sell('Rina', $session, [self::line($this->product, '2')]);
        TokoContoh::sell('Rina', $session, [self::line($this->product, '1')], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-1']);
        $this->travelTo('2026-11-13T11:00:00.250000Z');
        $number = self::text($this->watcher()->table('cash_sessions')->where('id', $session)->value('session_number'));
        $rina = $this->people['Rina']['id'];
        $hendra = $this->people['Hendra']['id'];

        // AUTH03: without a recent password confirmation nothing runs, not even a receipt.
        $this->as('Hendra');
        $envelope = $this->envelopeOf($session, '118000', 2);
        $this->close($envelope)->assertStatus(423)->assertJsonPath('code', 'RECENT_AUTH_REQUIRED');
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', CloseCashSessionAssisted::OPERATION)->count());

        $this->confirm('Hendra');
        $response = $this->close($envelope)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed.number', 'owner');
        $commandId = self::number($response->json('commandId'));
        $this->assertSame(['cash_session_close', $session, $number, '/cash-sessions/'.$session], [$response->json('result.type'), $response->json('result.id'), $response->json('result.number'), $response->json('result.location')]);

        // The responsible user stays; the close actor is the Owner, its kind OWNER_ASSISTED with the reason.
        $this->assertSame(['CLOSED', $rina, 'Rina', $session], array_values((array) $this->watcher()->table('cash_sessions')->where('id', $session)->first(['status', 'responsible_user_id', 'responsible_display_name', 'close_presence'])));
        $close = (array) $this->watcher()->table('cash_session_closes')->where('session_id', $session)->sole();
        $this->assertSame(
            ['OWNER_ASSISTED', $hendra, 'Hendra', self::REASON, 2, 118000, 120000, -2000, $commandId],
            [$close['close_kind'], $close['close_actor_user_id'], $close['close_actor_display_name'], $close['close_reason'], $close['activity_version_at_close'], $close['physical_cash'], $close['expected_cash'], $close['variance'], $close['command_id']],
        );

        // The report of DC-02 names the responsible user, the Owner as the one who closed, and the reason (G-17).
        $report = (array) $this->watcher()->table('cash_session_reports')->where('session_id', $session)->sole();
        $this->assertSame(
            [$number, 'Rina', 'Hendra', self::REASON, 2, 150000, 100000, 50000, 120000, 118000, -2000, null],
            [$report['session_number'], $report['responsible_display_name'], $report['close_actor_display_name'], $report['close_reason'], $report['completed_sale_count'], $report['completed_sale_value'], $report['cash_applied'], $report['bank_transfer_applied'], $report['expected_cash'], $report['physical_cash'], $report['variance'], $report['notes']],
        );

        // Its audit event, in the same transaction, with the reason in its column.
        $audit = (array) $this->watcher()->table('audit_events')->where('action', 'CASH_SESSION_CLOSE_ASSISTED')->sole();
        $this->assertSame(['BUSINESS', $hendra, 'Hendra', 'cash_session_close', $session, $session, $commandId, self::REASON], [$audit['category'], $audit['actor_user_id'], $audit['actor_display_name'], $audit['subject_type'], $audit['subject_id'], $audit['cash_session_id'], $audit['command_id'], $audit['reason']]);
        $this->assertSame(
            ['cashApplied' => '100000', 'cutoffVersion' => 2, 'expectedCash' => '120000', 'number' => $number, 'openingCash' => '20000', 'physicalCash' => '118000', 'register' => 'LACI-1', 'responsible' => 'Rina', 'saleCount' => 2, 'transferApplied' => '50000', 'variance' => '-2000'],
            self::dataOf($audit['change_data']),
        );

        // The same request answers the original result, and a status check rebuilds it (RC-50).
        $this->close($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.number', $number);
        $resolved = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.location', '/cash-sessions/'.$session);
        $this->assertJsonChannel($resolved, 'commands.resolve.number', 'owner');

        // RACE-09 in sequence (its race is AssistedCloseRaceTest's): her own close afterwards finds the
        // session CLOSED, and a different count never overwrites.
        $this->as('Rina');
        $this->postJson('/commands/cash_session.close', $this->envelope(['sessionId' => $session, 'physicalCash' => '120000'], ['operation' => 'cash_session.close', 'expected' => ['activityVersion' => 2]]))
            ->assertStatus(422)->assertJsonPath('code', 'SESSION_NOT_OPEN');
        $this->assertSame([118000, 1], [$this->watcher()->table('cash_session_closes')->where('session_id', $session)->value('physical_cash'), $this->watcher()->table('cash_session_closes')->count()]);
    }

    public function test_a_blank_count_a_missing_or_malformed_reason_or_a_field_the_server_derives_is_refused(): void
    {
        // RC-04; TR-VAL-03, TR-VAL-05; COPY_DECK section 16.1: the count as RC-03's, a reason of 1 to 1,000
        // characters without a control character; never a total, a variance, a responsible user or an actor.
        $session = TokoContoh::openSession('Rina', $this->register, '20000');
        $this->as('Hendra');
        $this->confirm('Hendra');
        $before = $this->facts();

        foreach ([
            [['physicalCash' => ''], 'payload.physicalCash', 'ux.validation.money_blank'],
            [['physicalCash' => '1.500'], 'payload.physicalCash', 'ux.validation.money'],
            [['physicalCash' => '-5'], 'payload.physicalCash', 'ux.validation.money_min'],
            [['reason' => ''], 'payload.reason', 'ux.validation.required'],
            [['reason' => '   '], 'payload.reason', 'ux.validation.required'],
            [['reason' => str_repeat('a', 1001)], 'payload.reason', 'ux.validation.text_max'],
        ] as $index => [$payload, $field, $key]) {
            $refused = $this->close($this->envelope([...['sessionId' => $session, 'physicalCash' => '0', 'reason' => self::REASON], ...$payload], ['operation' => CloseCashSessionAssisted::OPERATION, 'expected' => ['activityVersion' => 0]]))
                ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $fields = (array) $refused->json('fields');
            $this->assertSame($key, ((array) (((array) ($fields[$field] ?? []))[0] ?? []))['key'] ?? null, (string) $index);
        }

        foreach ([
            ['sessionId' => $session, 'physicalCash' => '0'],
            ['sessionId' => $session, 'physicalCash' => '0', 'reason' => "Baris\nkedua"],
            ['sessionId' => $session, 'physicalCash' => '0', 'reason' => 7],
            ['sessionId' => (string) $session, 'physicalCash' => '0', 'reason' => self::REASON],
            ['sessionId' => $session, 'physicalCash' => '0', 'reason' => self::REASON, 'expectedCash' => '20000'],
            ['sessionId' => $session, 'physicalCash' => '0', 'reason' => self::REASON, 'responsibleUserId' => $this->people['Hendra']['id']],
            ['sessionId' => $session, 'physicalCash' => '0', 'reason' => self::REASON, 'closeActorUserId' => $this->people['Rina']['id']],
        ] as $index => $payload) {
            $this->close($this->envelope($payload, ['operation' => CloseCashSessionAssisted::OPERATION, 'expected' => ['activityVersion' => 0]]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        foreach ([(object) [], ['activityVersion' => '0'], ['activityVersion' => -1]] as $expected) {
            $this->close($this->envelope(['sessionId' => $session, 'physicalCash' => '0', 'reason' => self::REASON], ['operation' => CloseCashSessionAssisted::OPERATION, 'expected' => $expected]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($before, $this->facts());

        // Rp0 is a count, and the reason is kept as written, its outer whitespace trimmed (TR-VAL-05).
        $this->close($this->envelope(['sessionId' => $session, 'physicalCash' => '0', 'reason' => '  '.self::REASON.'  '], ['operation' => CloseCashSessionAssisted::OPERATION, 'expected' => ['activityVersion' => 0]]))->assertStatus(201);
        $this->assertSame([0, -20000, self::REASON], array_values((array) $this->watcher()->table('cash_session_closes')->where('session_id', $session)->first(['physical_cash', 'variance', 'close_reason'])));
    }

    public function test_the_owners_own_session_and_a_missing_one_are_not_found_a_closed_one_is_not_open_and_a_moved_version_needs_a_recount(): void
    {
        // G-19; RC-04's gate: the responsible user is not the Owner, whose own session is closed by RC-03.
        $own = TokoContoh::openSession('Hendra', TokoContoh::register('LACI-H', 'Laci H'));
        $rina = TokoContoh::openSession('Rina', $this->register);
        $this->as('Hendra');
        $this->confirm('Hendra');
        $before = $this->facts();

        $this->close($this->envelopeOf($own, '0', 0))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->close($this->envelopeOf(999999, '0', 0))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->assertSame($before, $this->facts());

        // BR-CASH-14: a Sale after the preview needs a recount, and the session stays OPEN.
        TokoContoh::sell('Rina', $rina, [self::line($this->product, '1')]);
        $before = $this->facts();
        $recount = $this->close($this->envelopeOf($rina, '50000', 0))->assertStatus(409)->assertJsonPath('code', 'RECOUNT_REQUIRED')->assertJsonPath('messageKey', 'ux.state.recount');
        $this->assertJsonChannel($recount, 'commands.refused', 'owner');
        $this->assertSame($before, $this->facts());

        // Counted again, it closes; another close then finds it CLOSED (BR-CASH-17, RACE-09).
        $this->close($this->envelopeOf($rina, '50000', 1))->assertStatus(201);
        $this->close($this->envelopeOf($rina, '40000', 1))->assertStatus(422)->assertJsonPath('code', 'SESSION_NOT_OPEN')->assertJsonPath('messageKey', 'ux.state.session_closed.close');
        $this->assertSame(50000, $this->watcher()->table('cash_session_closes')->where('session_id', $rina)->value('physical_cash'));
    }

    public function test_the_responsible_users_unresolved_checkout_refuses_the_close_until_the_owners_status_check_settles_it(): void
    {
        // AC-032-02; G-27, D-088, D-089; BR-CASH-16: the Owner resolves the responsible user's checkouts,
        // which the server's receipts show; the close reads them after the session's lock.
        $session = TokoContoh::openSession('Rina', $this->register);
        $this->as('Rina');
        $this->app->instance(CommandPoints::class, new FailurePoints(1));
        $lost = $this->checkoutOf($session);
        $this->postJson('/commands/sale.checkout', $lost)->assertStatus(500);
        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->assertSame(['PENDING'], $this->outcomesOf($lost));

        $this->as('Hendra');
        $this->confirm('Hendra');
        $before = $this->facts();
        $refused = $this->close($this->envelopeOf($session, '0', 0))->assertStatus(409)->assertJsonPath('code', 'RECOUNT_REQUIRED')->assertJsonPath('messageKey', 'ux.state.recount.unresolved_checkouts');
        $this->assertJsonChannel($refused, 'commands.refused', 'owner');
        $this->assertSame($before, $this->facts());

        // The Owner's status check of her checkout (CAP-34) settles it NOT_COMMITTED with its fence, naming
        // him as its checker; the close, which reads the responsible user's receipts, then succeeds.
        $this->check($lost)->assertOk()->assertJsonPath('outcome', 'NOT_COMMITTED');
        $this->assertSame(['NOT_COMMITTED'], $this->outcomesOf($lost));
        $this->assertSame($this->people['Hendra']['id'], $this->receiptsOf($lost)[0]['settled_by_user_id'] ?? null);

        $this->close($this->envelopeOf($session, '0', 0))->assertStatus(201);
        $this->assertSame('CLOSED', $this->watcher()->table('cash_sessions')->where('id', $session)->value('status'));
    }

    public function test_a_held_or_earlier_epoch_checkout_refuses_the_close_until_settled_committed_and_the_new_version_is_counted(): void
    {
        // G-27, D-088, D-089 (review R-02): a HELD receipt counts, an earlier-epoch HELD one included; the
        // Owner's status check settles a committed intent COMMITTED; the version its Sale moved then needs
        // the preview read again (RECOUNT_REQUIRED, BR-CASH-14) before the close succeeds.
        $session = TokoContoh::openSession('Rina', $this->register);
        $this->as('Rina');
        $sold = $this->checkoutOf($session);
        $this->postJson('/commands/sale.checkout', $sold)->assertStatus(201);
        $this->postJson('/commands/sale.checkout', [...$sold, 'payload' => ['sessionId' => $session, 'lines' => [self::line($this->product, '2')], 'payment' => ['method' => 'CASH', 'tendered' => '100000']]])
            ->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT')->assertJsonPath('outcome', 'HELD');
        $stale = [...$this->checkoutOf($session), 'recoveryEpoch' => str_repeat('ab', 32)];
        $this->postJson('/commands/sale.checkout', $stale)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE')->assertJsonPath('outcome', 'HELD');

        $this->as('Hendra');
        $this->confirm('Hendra');
        $unresolved = 'ux.state.recount.unresolved_checkouts';
        $this->close($this->envelopeOf($session, '50000', 1))->assertStatus(409)->assertJsonPath('messageKey', $unresolved);

        // The Owner's check settles the HELD repeat COMMITTED; the earlier-epoch receipt still counts.
        $this->check($sold)->assertOk()->assertJsonPath('outcome', 'COMMITTED');
        $this->close($this->envelopeOf($session, '50000', 1))->assertStatus(409)->assertJsonPath('messageKey', $unresolved);
        $this->check($stale)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->close($this->envelopeOf($session, '50000', 1))->assertStatus(409)->assertJsonPath('messageKey', $unresolved);

        // Its intent committed in the current epoch, the Owner's check settles the earlier receipt too; the
        // close against the version of the earlier preview is refused, and against the new one succeeds.
        $this->as('Rina');
        $this->postJson('/commands/sale.checkout', [...$stale, 'recoveryEpoch' => $this->epoch, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(201);
        $this->as('Hendra');
        $this->check([...$stale, 'recoveryEpoch' => $this->epoch])->assertOk()->assertJsonPath('outcome', 'COMMITTED');
        $this->assertSame(['COMMITTED', 'COMMITTED'], $this->outcomesOf($stale));
        $this->close($this->envelopeOf($session, '50000', 1))->assertStatus(409)->assertJsonPath('code', 'RECOUNT_REQUIRED')->assertJsonPath('messageKey', 'ux.state.recount');
        $this->close($this->envelopeOf($session, '100000', 2))->assertStatus(201);
        $this->assertSame([2, 100000, 0], array_values((array) $this->watcher()->table('cash_session_reports')->where('session_id', $session)->first(['completed_sale_count', 'expected_cash', 'variance'])));
    }

    public function test_a_failure_after_every_write_step_leaves_the_session_open_and_every_repeat_answers_the_one_close(): void
    {
        // TS-21, BR-CASH-17: five steps — the session's status, the close, the report, the audit event, the result.
        $session = TokoContoh::openSession('Rina', $this->register, '50000');
        $this->as('Hendra');
        $this->confirm('Hendra');
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

        // The locks of TR §7.2 in their order: the actor, the BusinessProfile pointer, the session.
        $points = new FailurePoints(6);
        $this->app->instance(CommandPoints::class, $points);
        $envelope = $this->envelopeOf($session, '50000', 0);
        $this->close($envelope)->assertStatus(201);
        $this->assertSame(
            ['after_lock:2.1', 'after_lock:2.4', 'after_lock:4', 'after_write:1', 'after_write:2', 'after_write:3', 'after_write:4', 'after_write:5'],
            array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))),
        );
        $this->app->instance(CommandPoints::class, new FailurePoints);

        // RACE-01 and RACE-02 in sequence: the same request, with its key or another, answers the one
        // close; another count under its key is held; another intent finds the session CLOSED.
        $this->close($envelope)->assertStatus(200)->assertJsonPath('repeated', true);
        $this->close([...$envelope, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(200)->assertJsonPath('repeated', true);
        $this->close([...$envelope, 'payload' => ['sessionId' => $session, 'physicalCash' => '49000', 'reason' => self::REASON]])->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        $this->close($this->envelopeOf($session, '49000', 0))->assertStatus(422)->assertJsonPath('code', 'SESSION_NOT_OPEN');
        $this->assertSame([1, 1, 50000], [
            $this->watcher()->table('cash_session_closes')->count(),
            $this->watcher()->table('cash_session_reports')->count(),
            $this->watcher()->table('cash_session_closes')->where('session_id', $session)->value('physical_cash'),
        ]);
    }

    public function test_a_deactivated_cashiers_session_stays_open_and_unchanged_until_the_owners_close(): void
    {
        // AC-032-03; D-069 (d), BR-CASH-11, RACE-28 in sequence (its race is AssistedCloseRaceTest's): the
        // deactivation neither closes, transfers nor changes her session; every command of hers is refused;
        // the Owner-assisted close then succeeds, and she stays its responsible user.
        $session = TokoContoh::openSession('Rina', $this->register, '10000');
        TokoContoh::sell('Rina', $session, [self::line($this->product, '1')]);
        $rina = $this->people['Rina']['id'];
        $kept = $this->sessionRow($session);

        $this->as('Hendra');
        $this->confirm('Hendra');
        $version = self::number($this->watcher()->table('users')->where('id', $rina)->value('version'));
        $this->postJson('/commands/user.deactivate', $this->envelope(['userId' => $rina], ['operation' => 'user.deactivate', 'expected' => ['userVersion' => $version]]))->assertStatus(201);
        $this->assertSame('DEACTIVATED', $this->watcher()->table('users')->where('id', $rina)->value('status'));
        $this->assertSame($kept, $this->sessionRow($session));

        try {
            app(CheckoutSale::class)->execute(new CommandContext($rina, $this->rawCheckout($session), app(Clock::class)->now()));
            $this->fail('A deactivated cashier sold.');
        } catch (ActorNotAllowed) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($kept, $this->sessionRow($session));

        $this->close($this->envelopeOf($session, '60000', 1))->assertStatus(201);
        $this->assertSame(['CLOSED', $rina, 'Rina'], array_values((array) $this->watcher()->table('cash_sessions')->where('id', $session)->first(['status', 'responsible_user_id', 'responsible_display_name'])));
        $this->assertSame(['OWNER_ASSISTED', 'Hendra', 60000, 0], array_values((array) $this->watcher()->table('cash_session_closes')->where('session_id', $session)->first(['close_kind', 'close_actor_display_name', 'expected_cash', 'variance'])));
    }

    public function test_an_owner_replaced_by_op_03_keeps_his_open_session_until_the_new_owner_closes_it(): void
    {
        // AC-032-03; D-087, SEC-OP-03, BR-CASH-11: the outgoing Owner's OPEN session stays OPEN and
        // unchanged through the replacement; the new Owner closes it with an Owner-assisted close.
        $session = TokoContoh::openSession('Hendra', $this->register, '5000');
        $kept = $this->sessionRow($session);

        $new = app(ReplaceOwner::class)($this->people['Hendra']['id'], 'Pemilik Baru', 'pemilik.baru@contoh.test', 'Pergantian Pemilik untuk pengujian.');
        $this->assertSame($kept, $this->sessionRow($session));
        $this->assertSame('DEACTIVATED', $this->watcher()->table('users')->where('id', $this->people['Hendra']['id'])->value('status'));

        $password = TokoContoh::newPassword();
        app(ConsumeLink::class)($new->accountId, TokoContoh::tokenFor($new->accountId), $password);
        $this->browse($this->signInBrowser('pemilik.baru@contoh.test', $password));
        $this->postJson('/user/confirm-password', ['password' => $password])->assertStatus(201);

        $this->close($this->envelopeOf($session, '5000', 0))->assertStatus(201);
        $this->assertSame(['CLOSED', $this->people['Hendra']['id'], 'Hendra'], array_values((array) $this->watcher()->table('cash_sessions')->where('id', $session)->first(['status', 'responsible_user_id', 'responsible_display_name'])));
        $this->assertSame([$new->accountId, 'Pemilik Baru', self::REASON], array_values((array) $this->watcher()->table('cash_session_closes')->where('session_id', $session)->first(['close_actor_user_id', 'close_actor_display_name', 'close_reason'])));
    }

    public function test_only_the_owner_closes_another_users_session_and_every_other_role_is_refused(): void
    {
        // CAP-23 is the Owner's alone (SECURITY_MODEL section 6): staff and the combined account are refused
        // at the gate, with no receipt, and by the guard in the transaction (SEC-AZ-04); a visitor is not
        // signed in.
        $session = TokoContoh::openSession('Rina', $this->register);

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->confirm($person);
            $this->close($this->envelopeOf($session, '0', 0))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
            $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', CloseCashSessionAssisted::OPERATION)->where('actor_user_id', $this->people[$person]['id'])->count(), $person);

            try {
                app(CloseCashSessionAssisted::class)->execute(new CommandContext($this->people[$person]['id'], $this->rawEnvelope($session), app(Clock::class)->now()));
                $this->fail($person.' passed the guard.');
            } catch (ActorNotAllowed) {
                $this->addToAssertionCount(1);
            }
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

    /** The person's recent authentication (RA-03), in the person's browser. */
    private function confirm(string $person): void
    {
        $this->postJson('/user/confirm-password', ['password' => $this->people[$person]['password']])->assertStatus(201);
    }

    /**
     * An envelope of RC-04 for this session, count and reviewed activity version, with the reason.
     *
     * @return array<string, mixed>
     */
    private function envelopeOf(int $session, string $physicalCash, int $version): array
    {
        return $this->envelope(['sessionId' => $session, 'physicalCash' => $physicalCash, 'reason' => self::REASON], ['operation' => CloseCashSessionAssisted::OPERATION, 'expected' => ['activityVersion' => $version]]);
    }

    private function rawEnvelope(int $session): Envelope
    {
        return new Envelope(CloseCashSessionAssisted::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, ['activityVersion' => 0], ['sessionId' => $session, 'physicalCash' => '0', 'reason' => self::REASON]);
    }

    /**
     * An envelope of RC-01 of one unit of the product in this session, paid in CASH.
     *
     * @return array<string, mixed>
     */
    private function checkoutOf(int $session): array
    {
        return $this->envelope(
            ['sessionId' => $session, 'lines' => [self::line($this->product, '1')], 'payment' => ['method' => 'CASH', 'tendered' => '50000']],
            ['operation' => CheckoutSale::OPERATION, 'expected' => ['lines' => [['priceRevisionId' => $this->revision()]]]],
        );
    }

    private function rawCheckout(int $session): Envelope
    {
        return new Envelope(CheckoutSale::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, ['lines' => [['priceRevisionId' => $this->revision()]]], [
            'sessionId' => $session, 'lines' => [self::line($this->product, '1')], 'payment' => ['method' => 'CASH', 'tendered' => '50000'],
        ]);
    }

    private function revision(): int
    {
        return self::number($this->watcher()->table('products')->where('id', $this->product)->value('current_price_revision_id'));
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
     * A session's row as it stands, every column.
     *
     * @return array<string, mixed>
     */
    private function sessionRow(int $session): array
    {
        return (array) $this->watcher()->table('cash_sessions')->where('id', $session)->first();
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
