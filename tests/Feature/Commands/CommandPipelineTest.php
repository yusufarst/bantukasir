<?php

namespace Tests\Feature\Commands;

use App\Actions\Commands\CommandContext;
use App\Database\CommandPoints;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\ErrorCode;
use App\Http\Commands\ReceivedAt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\Commands\DeadlockPoints;
use Tests\Harness\Commands\Probe;
use Tests\Harness\Commands\ProbeAction;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Race\FailurePoints;

/**
 * The server side of a command, end to end on the probe operation
 * (AC-008-01, AC-008-04 to AC-008-06, AC-008-08; INTERACTION_CONTRACTS
 * sections 1.1 and 1.2; ADR-007 sections 1 to 3 and 7; ADR-009 section 1;
 * TECHNICAL_RULES sections 4, 6 and 9, TR-TIME-03; guards G-12 and G-25;
 * suite CMD01; TASK-008 scope items 2 to 4, 7 and 9): every step in its
 * order; the refusals before the receipt, which record none; the failure
 * injected into the receipt and after every write step; the marking; the
 * envelope's refusals; repeats and conflicts; the hard end, the retried
 * deadlock and the lock wait; and the error body of every code.
 */
final class CommandPipelineTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCommands();
    }

    public function test_a_command_runs_every_step_in_order_and_commits_its_facts_audit_result_and_receipt(): void
    {
        $points = new FailurePoints;
        $this->app->instance(CommandPoints::class, $points);
        $this->as('Agus');
        $envelope = $this->envelope();

        $response = $this->probe($envelope)->assertStatus(201);
        $this->assertJsonChannel($response, 'commands.committed', 'ops');
        $commandId = $response->json('commandId');
        $this->assertIsInt($commandId);
        $response->assertExactJson(['outcome' => 'COMMITTED', 'operation' => Probe::OPERATION, 'commandId' => $commandId, 'repeated' => false, 'result' => ['type' => 'harness_probe', 'id' => $response->json('result.id'), 'location' => '/account']]);

        // INTERACTION_CONTRACTS section 1.1: receipt, FormRequest, the Action's
        // identity, epoch and actor guard, its lock, its three writes and the
        // result, the commit, then the marking.
        $this->assertSame(['own_write:receipt', 'after_identity', 'after_lock:2.1', 'after_lock:6', 'after_write:1', 'after_write:2', 'after_write:3', 'after_write:4', 'before_commit', 'own_write:marking'], $points->passed);

        $identity = (array) $this->observer()->selectOne("select kind, operation, operation_version, source_intent_id::text as intent, idempotency_key::text as key, actor_kind, actor_user_id, octet_length(fingerprint) as fingerprint, encode(recovery_epoch, 'hex') as epoch from command_identities where id = ?", [$commandId]);
        $this->assertSame(['kind' => 'COMMAND', 'operation' => Probe::OPERATION, 'operation_version' => 1, 'intent' => $envelope['sourceIntentId'], 'key' => $envelope['idempotencyKey'], 'actor_kind' => 'USER', 'actor_user_id' => $this->people['Agus']['id'], 'fingerprint' => 32, 'epoch' => $this->epoch], $identity);
        $this->assertSame(['harness_probe', $response->json('result.id')], [$this->observer()->table('command_results')->where('command_id', $commandId)->value('result_type'), $this->observer()->table('command_results')->where('command_id', $commandId)->value('result_ref')]);
        $this->assertSame($commandId, $this->observer()->table('audit_events')->where('action', 'HARNESS_PROBE')->value('command_id'));
        $this->assertSame(1, $this->observer()->table('harness_probe_facts')->where('command_id', $commandId)->count());

        $receipt = $this->receiptsOf($envelope);
        $this->assertCount(1, $receipt);
        $this->assertSame(['COMMITTED', null, null], [$receipt[0]['outcome'], $receipt[0]['held_code'], $receipt[0]['settled_by_command_id']]);
        $this->assertNotNull($receipt[0]['outcome_at']);

        // The receipt holds no payload (ADR-009 section 1).
        $columns = $this->observer()->table('information_schema.columns')->where('table_name', 'command_request_receipts')->orderBy('ordinal_position')->pluck('column_name')->all();
        $this->assertSame(['id', 'actor_user_id', 'operation', 'source_intent_id', 'idempotency_key', 'recovery_epoch', 'received_at', 'outcome', 'outcome_at', 'held_code', 'settled_by_command_id', 'settled_by_user_id'], $columns);
    }

    public function test_a_refusal_before_the_receipt_records_no_receipt(): void
    {
        // The capability gate (RT-04): the cashier lacks CAP-04.
        $this->as('Rina');
        $this->probe($this->envelope())->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');

        // No sign-in session.
        $this->browse(null);
        $this->probe($this->envelope())->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');

        // An envelope without a usable identity: operation, intent, key, epoch.
        $this->as('Agus');
        foreach ([['operation' => 'sale.checkout'], ['sourceIntentId' => 'not-a-uuid'], ['idempotencyKey' => null], ['recoveryEpoch' => 'ABCDEF'], ['recoveryEpoch' => str_repeat('ab', 8)]] as $change) {
            $this->probe($this->envelope(changes: $change))->assertStatus(422)->assertExactJson(['outcome' => 'REJECTED', 'code' => 'VALIDATION_FAILED', 'messageKey' => 'ux.state.validation', 'params' => [], 'fields' => [], 'lines' => []]);
        }

        // The hard end: received more than 30 seconds ago.
        $this->probe($this->envelope(), [ReceivedAt::PARAMETER => self::secondsAgo(31)])->assertStatus(503)->assertJsonPath('code', 'SERVICE_BUSY');

        $this->assertSame(0, $this->observer()->table('command_request_receipts')->count());
        $this->assertSame(0, $this->observer()->table('command_identities')->count());
    }

    public function test_a_receipt_that_cannot_be_written_answers_service_busy_with_nothing_run(): void
    {
        $this->app->instance(CommandPoints::class, new FailurePoints(failAtOwnWrite: 'receipt'));
        $this->as('Agus');
        $before = $this->counts();

        $this->probe($this->envelope())->assertStatus(503)->assertExactJson(['outcome' => 'REJECTED', 'code' => 'SERVICE_BUSY', 'messageKey' => 'ux.state.uncertain', 'params' => [], 'fields' => [], 'lines' => []]);

        $this->assertSame($before, $this->counts());
    }

    public function test_a_failure_after_every_write_step_leaves_no_fact_audit_or_result_and_the_receipt_pending(): void
    {
        $this->as('Agus');

        foreach ([1, 2, 3, 4] as $step) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $envelope = $this->envelope();
            $before = $this->counts();

            $response = $this->probe($envelope);

            // A server error, UNCERTAIN for the client (TR-ERR-01), with an
            // empty body: no trace, SQL or path (TR-ERR-02).
            $response->assertStatus(500)->assertExactJson([]);
            $this->assertSame(['command_identities' => $before['command_identities'], 'command_results' => $before['command_results'], 'command_request_receipts' => $before['command_request_receipts'] + 1, 'stale_intent_reports' => 0, 'audit_events' => $before['audit_events'], 'harness_probe_facts' => $before['harness_probe_facts'], 'counter' => $before['counter']], $this->counts(), 'after write step '.$step);
            $this->assertSame(['PENDING'], $this->outcomesOf($envelope));
        }
    }

    public function test_a_failed_marking_leaves_the_receipt_pending_and_the_answer_the_commands_own(): void
    {
        $this->app->instance(CommandPoints::class, new FailurePoints(failAtOwnWrite: 'marking'));
        $this->as('Agus');
        $envelope = $this->envelope();

        $this->probe($envelope)->assertStatus(201)->assertJsonPath('outcome', 'COMMITTED');

        $this->assertSame(['PENDING'], $this->outcomesOf($envelope));
        $this->assertSame(1, $this->observer()->table('command_identities')->count());
    }

    public function test_the_envelope_is_refused_after_its_receipt_for_any_field_out_of_its_allowlist_or_format(): void
    {
        $this->as('Agus');
        $refusals = [
            'an unknown field' => ['changes' => ['actorId' => 1]],
            'an unknown payload field' => ['payload' => ['note' => 'x', 'price' => '1000']],
            'a version as text' => ['changes' => ['version' => '1']],
            'another version' => ['changes' => ['version' => 2]],
            'a decimal version' => ['changes' => ['version' => 1.5]],
            'expected versions the command does not name' => ['changes' => ['expected' => ['productVersion' => 3]]],
            'no expected versions' => ['changes' => ['expected' => null]],
            'no payload' => ['changes' => ['payload' => null]],
            'an unknown code' => ['payload' => ['note' => 'x', 'refuseWith' => 'NOT_A_CODE']],
        ];

        foreach ($refusals as $case => $refusal) {
            $envelope = $this->envelope($refusal['payload'] ?? ['note' => 'Catatan uji'], $refusal['changes'] ?? []);
            $response = $this->probe($envelope);
            $this->assertSame([422, 'VALIDATION_FAILED'], [$response->getStatusCode(), $response->json('code')], $case);
            $this->assertSame(['REJECTED'], $this->outcomesOf($envelope), $case);
        }

        // A field's rule answers its COPY_DECK key with its parameters
        // (INTERACTION_CONTRACTS section 1.6).
        $this->probe($this->envelope(['note' => str_repeat('a', 101)]))->assertStatus(422)
            ->assertExactJson(['outcome' => 'REJECTED', 'code' => 'VALIDATION_FAILED', 'messageKey' => 'ux.state.validation', 'params' => [], 'fields' => ['payload.note' => [['key' => 'ux.validation.text_max', 'params' => ['maks' => 100]]]], 'lines' => []]);
        $this->probe($this->envelope([]))->assertStatus(422)
            ->assertJsonPath('fields', ['payload.note' => [['key' => 'ux.validation.required', 'params' => []]]]);

        $this->assertSame(0, $this->observer()->table('command_identities')->count());
    }

    public function test_a_repeated_identity_returns_the_original_result_and_a_different_payload_is_held(): void
    {
        $this->as('Agus');
        $envelope = $this->envelope();
        $first = $this->probe($envelope)->assertStatus(201);

        // The same key and payload: the original, nothing new (RACE-01).
        $repeat = $this->probe($envelope)->assertStatus(200);
        $this->assertSame([true, $first->json('commandId'), $first->json('result')], [$repeat->json('repeated'), $repeat->json('commandId'), $repeat->json('result')]);

        // Another key of the same intent and payload: the original (RACE-02).
        $this->probe([...$envelope, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(200)->assertJsonPath('commandId', $first->json('commandId'));

        // The same key or the same intent with another payload: HELD.
        $before = $this->counts();
        $this->probe([...$envelope, 'payload' => ['note' => 'Lain']])->assertStatus(409)
            ->assertExactJson(['outcome' => 'HELD', 'code' => 'IDEMPOTENCY_CONFLICT', 'messageKey' => 'ux.state.held.idempotency_conflict', 'params' => [], 'fields' => [], 'lines' => []]);
        $this->probe([...$envelope, 'idempotencyKey' => (string) Str::uuid(), 'payload' => ['note' => 'Lain']])->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');

        // The same intent by another user: another fingerprint.
        $this->as('Dewi');
        $this->probe([...$envelope, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');

        $after = $this->counts();
        $this->assertSame([$before['command_identities'], $before['harness_probe_facts'], $before['audit_events']], [$after['command_identities'], $after['harness_probe_facts'], $after['audit_events']]);
        $this->assertSame(1, $after['counter']);
        $this->assertSame(['COMMITTED', 'COMMITTED', 'COMMITTED', 'HELD', 'HELD', 'HELD'], $this->outcomesOf($envelope));
        $this->assertSame([null, null, null, 'IDEMPOTENCY_CONFLICT', 'IDEMPOTENCY_CONFLICT', 'IDEMPOTENCY_CONFLICT'], array_column($this->receiptsOf($envelope), 'held_code'));
    }

    public function test_a_repeat_by_a_user_who_lost_the_capability_meanwhile_is_refused(): void
    {
        $this->as('Agus');
        $envelope = $this->envelope();
        $this->probe($envelope)->assertStatus(201);

        // Agus becomes a cashier only: the gate refuses the repeat …
        $this->assertSame(1, $this->observer()->table('users')->where('id', $this->people['Agus']['id'])->update(['role_operations_admin' => false, 'role_cashier' => true]));
        $this->as('Agus');
        $this->probe($envelope)->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');

        // … and so does the Action itself, before it returns the original
        // (ADR-007 section 3), for a request that passed the gate before.
        $context = new CommandContext($this->people['Agus']['id'], self::envelopeOf($envelope), CarbonImmutable::now('UTC'));
        $this->assertThrows(fn () => app(ProbeAction::class)->execute($context), ActorNotAllowed::class);
    }

    public function test_an_earlier_epoch_is_refused_before_any_fact_with_its_report_written_once_and_its_receipts_held(): void
    {
        $this->as('Agus');
        $earlier = str_repeat('0f', 32);
        $envelope = $this->envelope(changes: ['recoveryEpoch' => $earlier]);
        $before = $this->counts();

        $this->probe($envelope)->assertStatus(409)->assertExactJson(['outcome' => 'HELD', 'code' => 'EPOCH_STALE', 'messageKey' => 'ux.state.held.epoch_stale', 'params' => [], 'fields' => [], 'lines' => []]);
        $this->probe([...$envelope, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');

        $after = $this->counts();
        $this->assertSame([$before['command_identities'], $before['harness_probe_facts'], $before['audit_events'], 1], [$after['command_identities'], $after['harness_probe_facts'], $after['audit_events'], $after['stale_intent_reports']]);
        $report = (array) $this->observer()->selectOne("select operation, source_intent_id::text as intent, actor_user_id, encode(reported_epoch, 'hex') as epoch from stale_intent_reports");
        $this->assertSame(['operation' => Probe::OPERATION, 'intent' => $envelope['sourceIntentId'], 'actor_user_id' => $this->people['Agus']['id'], 'epoch' => $earlier], $report);
        $this->assertSame(['HELD', 'HELD'], $this->outcomesOf($envelope));
        $this->assertSame(['EPOCH_STALE', 'EPOCH_STALE'], array_column($this->receiptsOf($envelope), 'held_code'));
    }

    public function test_a_failed_stale_intent_report_answers_service_busy_with_no_report_and_the_receipt_pending(): void
    {
        // Review finding R-04: never an EPOCH_STALE refusal without its report.
        $this->app->instance(CommandPoints::class, new FailurePoints(failAtOwnWrite: 'report'));
        $this->as('Agus');
        $envelope = $this->envelope(changes: ['recoveryEpoch' => str_repeat('0f', 32)]);

        $this->probe($envelope)->assertStatus(503)->assertJsonPath('code', 'SERVICE_BUSY');

        $this->assertSame(0, $this->observer()->table('stale_intent_reports')->count());
        $this->assertSame(['PENDING'], $this->outcomesOf($envelope));
        $this->assertSame(0, $this->observer()->table('command_identities')->count());
    }

    public function test_a_committed_command_whose_result_the_viewer_may_not_see_is_still_answered_committed(): void
    {
        // Review finding R-05: a committed command is never answered with a
        // refusal, and its receipt is never REJECTED.
        $this->as('Agus');
        $envelope = $this->envelope(['note' => 'Catatan uji', 'hideResult' => true]);

        $first = $this->probe($envelope)->assertStatus(201)->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result', null);
        $this->probe($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result', null)->assertJsonPath('commandId', $first->json('commandId'));

        $this->assertSame(['COMMITTED', 'COMMITTED'], $this->outcomesOf($envelope));
    }

    public function test_a_floating_point_number_anywhere_in_the_envelope_is_refused_before_the_action(): void
    {
        // Review finding R-08: an integer rule accepts the JSON number 3.0;
        // the base refuses every float (TR-VAL-06, BR-NUM-04).
        $this->as('Agus');

        // The integer 3 runs; the JSON number 3.0, which the integer rule
        // alone would accept, is refused and marks its receipt REJECTED.
        $integer = $this->envelope(['note' => 'Catatan uji', 'count' => 3]);
        $this->assertSame(201, $this->sendWithCount($integer, '3')->getStatusCode());

        $float = $this->envelope(['note' => 'Catatan uji', 'count' => 3]);
        $response = $this->sendWithCount($float, '3.0');
        $this->assertSame([422, 'VALIDATION_FAILED'], [$response->getStatusCode(), $response->json('code')]);
        $this->assertSame(['REJECTED'], $this->outcomesOf($float));
        $this->assertSame(1, $this->observer()->table('command_identities')->count());
    }

    public function test_a_late_original_of_a_fenced_intent_is_refused_intent_abandoned(): void
    {
        $this->as('Agus');
        $envelope = $this->envelope();
        $this->check($envelope)->assertOk()->assertExactJson(['outcome' => 'NOT_COMMITTED', 'operation' => Probe::OPERATION]);

        $this->probe($envelope)->assertStatus(409)->assertExactJson(['outcome' => 'REJECTED', 'code' => 'INTENT_ABANDONED', 'messageKey' => 'ux.state.abandoned', 'params' => [], 'fields' => [], 'lines' => []]);

        $this->assertSame(['NOT_COMMITTED'], $this->outcomesOf($envelope));
        $this->assertSame(0, $this->observer()->table('harness_probe_facts')->count());
    }

    public function test_a_command_starting_more_than_thirty_seconds_after_it_was_received_is_refused_before_any_write(): void
    {
        $this->as('Agus');

        // Received 29 seconds ago by the web server: it runs.
        $this->probe($this->envelope(), [ReceivedAt::PARAMETER => self::secondsAgo(29)])->assertStatus(201);

        // A header named like the parameter is the client's and is never read.
        $this->withHeaders(['Request-Received-At' => self::secondsAgo(60)])->postJson(Probe::PATH, $this->envelope())->assertStatus(201);

        // The Action's own check, before its identity (TR-TIME-03).
        $before = $this->counts();
        $context = new CommandContext($this->people['Agus']['id'], self::envelopeOf($this->envelope()), CarbonImmutable::now('UTC')->subSeconds(31));
        $refusal = $this->catchRefusal(fn () => app(ProbeAction::class)->execute($context));
        $this->assertSame(ErrorCode::ServiceBusy, $refusal->error);
        $this->assertSame($before, $this->counts());
    }

    public function test_an_injected_deadlock_is_retried_with_the_same_identity_at_most_three_times(): void
    {
        $this->as('Agus');

        // Two deadlocks: the third attempt commits, once, under its identity.
        $points = new DeadlockPoints(2);
        $this->app->instance(CommandPoints::class, $points);
        $envelope = $this->envelope();
        $this->probe($envelope)->assertStatus(201);
        $this->assertSame(3, $points->attempts);
        $this->assertSame(1, $this->observer()->table('command_identities')->where('source_intent_id', $envelope['sourceIntentId'])->where('idempotency_key', $envelope['idempotencyKey'])->count());
        $this->assertSame(1, $this->observer()->table('harness_probe_facts')->count());

        // Three deadlocks: no fourth attempt, SERVICE_BUSY, nothing written,
        // the receipt PENDING for the client's repeat.
        $points = new DeadlockPoints(3);
        $this->app->instance(CommandPoints::class, $points);
        $envelope = $this->envelope();
        $before = $this->counts();
        $this->probe($envelope)->assertStatus(503)->assertJsonPath('code', 'SERVICE_BUSY');
        $this->assertSame(3, $points->attempts);
        $this->assertSame(['command_identities' => $before['command_identities'], 'harness_probe_facts' => $before['harness_probe_facts']], array_intersect_key($this->counts(), ['command_identities' => 0, 'harness_probe_facts' => 0]));
        $this->assertSame(['PENDING'], $this->outcomesOf($envelope));
    }

    public function test_a_lock_wait_beyond_the_lock_timeout_answers_service_busy_with_nothing_written(): void
    {
        $this->as('Agus');
        $holder = $this->observer();
        $holder->beginTransaction();
        $holder->table('harness_probe_counters')->where('id', 1)->lockForUpdate()->value('value');

        try {
            $envelope = $this->envelope();
            $before = $this->counts();
            $this->probe($envelope)->assertStatus(503)->assertJsonPath('code', 'SERVICE_BUSY');
            $this->assertSame($before['command_identities'], $this->counts()['command_identities']);
            $this->assertSame(['PENDING'], $this->outcomesOf($envelope));
        } finally {
            $holder->rollBack();
        }
    }

    public function test_every_error_code_answers_its_status_and_message_key_with_safe_values_only(): void
    {
        $this->as('Agus');

        foreach (ErrorCode::cases() as $code) {
            $envelope = $this->envelope(['note' => 'Catatan uji', 'refuseWith' => $code->value]);
            $response = $this->probe($envelope);

            $response->assertStatus($code->status());

            if ($code !== ErrorCode::InsufficientStock) {
                $this->assertJsonChannel($response, 'commands.refused', 'ops');
            }

            $this->assertSame([$code->bodyOutcome(), $code->value, $code->messageKey()], [$response->json('outcome'), $response->json('code'), $response->json('messageKey')], $code->value);
            $this->assertDoesNotMatchRegularExpression('/SQLSTATE|harness_|\/var\/www|\.php|Stack trace|#0 /', (string) $response->getContent());

            $expected = match ($code) {
                ErrorCode::ServiceBusy => ['PENDING'],
                ErrorCode::IdempotencyConflict, ErrorCode::EpochStale => ['HELD'],
                ErrorCode::IntentAbandoned => ['NOT_COMMITTED'],
                default => ['REJECTED'],
            };
            $this->assertSame($expected, $this->outcomesOf($envelope), $code->value);
        }

        $this->assertSame(0, $this->observer()->table('harness_probe_facts')->count());
        // A line carries only its safe values (INTERACTION_CONTRACTS section 1.6).
        $this->probe($this->envelope(['note' => 'x', 'refuseWith' => 'INSUFFICIENT_STOCK']))->assertStatus(422)
            ->assertExactJson(['outcome' => 'REJECTED', 'code' => 'INSUFFICIENT_STOCK', 'messageKey' => 'ux.state.insufficient_stock', 'params' => [], 'fields' => [], 'lines' => ['0' => ['available' => '2']]]);
    }

    private static function secondsAgo(int $seconds): string
    {
        return CarbonImmutable::now('UTC')->subSeconds($seconds)->format('U.v');
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private static function envelopeOf(array $envelope): Envelope
    {
        $payload = $envelope['payload'];
        assert(is_array($payload));

        return new Envelope(Probe::OPERATION, 1, self::text($envelope['sourceIntentId']), self::text($envelope['idempotencyKey']), self::text($envelope['recoveryEpoch']), [], $payload);
    }

    /**
     * The envelope as a raw JSON body whose `count` is written as given.
     *
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function sendWithCount(array $envelope, string $count): TestResponse
    {
        $json = str_replace('"count":3', '"count":'.$count, (string) json_encode($envelope));

        return $this->withServerVariables([])->call('POST', Probe::PATH, [], $this->prepareCookiesForJsonRequest(), [], ['CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => (string) strlen($json), 'HTTP_ACCEPT' => 'application/json'], $json);
    }

    /**
     * @param  callable(): mixed  $call
     */
    private function catchRefusal(callable $call): CommandRefused
    {
        try {
            $call();
        } catch (CommandRefused $refusal) {
            return $refusal;
        }

        $this->fail('The command was not refused.');
    }
}
