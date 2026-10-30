<?php

namespace Tests\Feature\Commands;

use App\Database\CommandPoints;
use App\Domain\Commands\CommandIdentities;
use App\Domain\Commands\RecoveryEpoch;
use App\Domain\Commands\RequestReceipts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\Commands\Probe;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-50, the status check, and the settlement of request receipts
 * (AC-008-03, AC-008-04; INTERACTION_CONTRACTS section 2.1; ADR-007 section
 * 4; ADR-009 sections 1 and 3; SCHEMA section 5.3; D-089; CAP-34; suite
 * CMD01; TASK-008 scope item 5). Agus (OPERATIONS_ADMIN) runs the probe,
 * Hendra is the Owner and Dewi a third user who may check her own intents
 * only. The races of the check are tests/Concurrency/CommandRaceTest.php.
 */
final class ResolveIntentTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCommands();
    }

    public function test_the_actors_check_answers_committed_and_settles_a_pending_and_a_held_receipt_keeping_its_code(): void
    {
        $envelope = $this->committedWithPendingAndHeld();

        $this->as('Agus');
        $response = $this->check($envelope)->assertOk();
        $this->assertJsonChannel($response, 'commands.resolve', 'ops');
        $commandId = $response->json('commandId');
        $response->assertExactJson(['outcome' => 'COMMITTED', 'operation' => Probe::OPERATION, 'commandId' => $commandId, 'result' => ['type' => 'harness_probe', 'id' => $response->json('result.id'), 'location' => '/account']]);

        $this->assertSettled($envelope, 'COMMITTED', $commandId, 'Agus');
    }

    public function test_the_owners_check_of_another_users_intent_settles_it_as_the_owner(): void
    {
        $envelope = $this->committedWithPendingAndHeld();

        $this->as('Hendra');
        $commandId = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->json('commandId');

        $this->assertSettled($envelope, 'COMMITTED', $commandId, 'Hendra');
    }

    public function test_not_committed_writes_a_fence_that_settles_both_receipts_in_its_transaction(): void
    {
        [$envelope] = $this->pendingAndHeldWithoutIdentity();

        // A failure after the settlement, inside the fence's transaction:
        // neither the fence nor the settlement remains (SERVICE_BUSY).
        $this->app->instance(CommandPoints::class, new FailurePoints(1));
        $this->as('Agus');
        $this->check($envelope)->assertStatus(503)->assertJsonPath('code', 'SERVICE_BUSY');
        $this->assertSame(0, $this->observer()->table('command_identities')->where('operation', '<>', 'user.create')->count());
        $this->assertSame(['PENDING', 'HELD'], $this->outcomesOf($envelope));

        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->check($envelope)->assertOk()->assertExactJson(['outcome' => 'NOT_COMMITTED', 'operation' => Probe::OPERATION]);

        $fence = (array) $this->observer()->selectOne("select id, kind, actor_user_id, operation_version, idempotency_key, fingerprint, encode(recovery_epoch, 'hex') as epoch from command_identities where kind = 'FENCE'");
        $this->assertSame(['FENCE', $this->people['Agus']['id'], null, null, null, $this->epoch], [$fence['kind'], $fence['actor_user_id'], $fence['operation_version'], $fence['idempotency_key'], $fence['fingerprint'], $fence['epoch']]);
        $this->assertSame(0, $this->observer()->table('command_results')->where('result_type', '<>', 'user')->count());
        $this->assertSettled($envelope, 'NOT_COMMITTED', $fence['id'], 'Agus');

        // An existing fence answers NOT_COMMITTED again and settles nothing more.
        $before = $this->receiptsOf($envelope);
        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'NOT_COMMITTED');
        $this->assertSame($before, $this->receiptsOf($envelope));
    }

    public function test_a_third_users_check_answers_not_found_and_settles_nothing(): void
    {
        $committed = $this->committedWithPendingAndHeld();
        [$open] = $this->pendingAndHeldWithoutIdentity();
        $before = [$this->receiptsOf($committed), $this->receiptsOf($open), $this->counts()];

        $this->as('Dewi');
        foreach ([$committed, $open] as $envelope) {
            $this->check($envelope)->assertStatus(404)->assertExactJson(['outcome' => 'REJECTED', 'code' => 'NOT_FOUND', 'messageKey' => 'ux.state.not_found', 'params' => [], 'fields' => [], 'lines' => []]);
        }

        $this->assertSame($before, [$this->receiptsOf($committed), $this->receiptsOf($open), $this->counts()]);
    }

    public function test_a_settled_receipt_never_changes_outcome(): void
    {
        $envelope = $this->committedWithPendingAndHeld();
        $this->as('Agus');
        $this->check($envelope)->assertOk();
        $settled = $this->receiptsOf($envelope);

        // A late marking of the settled receipts' own requests changes no
        // outcome; it only fills an empty held code (SCHEMA section 5.3).
        $receipts = app(RequestReceipts::class);
        $receipts->mark(self::number($settled[0]['id']), RequestReceipts::REJECTED);
        $receipts->mark(self::number($settled[1]['id']), RequestReceipts::HELD, 'EPOCH_STALE');
        $receipts->mark(self::number($settled[0]['id']), RequestReceipts::HELD, 'IDEMPOTENCY_CONFLICT');

        $after = $this->receiptsOf($envelope);
        $this->assertSame(array_column($settled, 'outcome'), array_column($after, 'outcome'));
        $this->assertSame(['IDEMPOTENCY_CONFLICT', 'IDEMPOTENCY_CONFLICT'], array_column($after, 'held_code'));
        $this->assertSame(array_column($settled, 'settled_by_command_id'), array_column($after, 'settled_by_command_id'));

        // A second check changes nothing either.
        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED');
        $this->assertSame($after, $this->receiptsOf($envelope));
    }

    public function test_an_earlier_epoch_intent_without_identity_is_epoch_stale_with_one_report_and_its_pending_receipts_held(): void
    {
        $earlier = str_repeat('0e', 32);
        $envelope = $this->envelope(changes: ['recoveryEpoch' => $earlier]);
        $this->record($envelope, 'Agus');

        $this->as('Agus');
        foreach ([1, 2] as $attempt) {
            $this->check($envelope)->assertStatus(409)->assertExactJson(['outcome' => 'HELD', 'code' => 'EPOCH_STALE', 'messageKey' => 'ux.state.held.epoch_stale', 'params' => [], 'fields' => [], 'lines' => []]);
        }

        $this->assertSame(1, $this->observer()->table('stale_intent_reports')->count());
        $this->assertSame($this->people['Agus']['id'], $this->observer()->table('stale_intent_reports')->value('actor_user_id'));
        $this->assertSame(['HELD'], $this->outcomesOf($envelope));
        $this->assertSame(['EPOCH_STALE'], array_column($this->receiptsOf($envelope), 'held_code'));
        $this->assertSame(0, $this->observer()->table('command_identities')->where('operation', '<>', 'user.create')->count());

        // The Owner's check of it too, without settling it.
        $this->as('Hendra');
        $this->check($envelope)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->assertSame(1, $this->observer()->table('stale_intent_reports')->count());
    }

    public function test_the_servers_knowledge_of_an_earlier_epoch_decides_whatever_epoch_the_check_sends(): void
    {
        // Review finding R-02: a row that only the server knows is checked
        // with the current epoch; its earlier-epoch receipt still makes it
        // EPOCH_STALE, with no fence and no settlement (D-073, BR-CMD-06).
        $envelope = $this->envelope(changes: ['recoveryEpoch' => str_repeat('0e', 32)]);
        $this->record($envelope, 'Agus');
        $this->as('Agus');
        $this->check($envelope)->assertStatus(409);
        $this->assertSame(['HELD'], $this->outcomesOf($envelope));

        $this->as('Hendra');
        $this->check($envelope, $this->epoch)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->assertSame(0, $this->observer()->table('command_identities')->where('operation', '<>', 'user.create')->count());
        $this->assertSame(['HELD'], $this->outcomesOf($envelope));
        $this->assertSame(1, $this->observer()->table('stale_intent_reports')->count());

        // A report alone, without any receipt, makes it so too.
        $reported = $this->envelope();
        $this->as('Agus');
        $this->check($reported, str_repeat('0d', 32))->assertStatus(409);
        $this->check($reported, $this->epoch)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->assertSame(0, $this->observer()->table('command_identities')->where('operation', '<>', 'user.create')->count());

        // The reverse: an intent whose receipts carry the current epoch is
        // fenced, whatever epoch the check sends, and no report is written.
        $current = $this->envelope();
        $this->record($current, 'Agus');
        $this->check($current, str_repeat('0c', 32))->assertOk()->assertJsonPath('outcome', 'NOT_COMMITTED');
        $this->assertSame(['NOT_COMMITTED'], $this->outcomesOf($current));
        $this->assertSame(2, $this->observer()->table('stale_intent_reports')->count());
    }

    public function test_a_failed_report_or_settlement_answers_service_busy_and_changes_nothing(): void
    {
        // Review finding R-04: the report of an earlier-epoch check.
        $stale = $this->envelope(changes: ['recoveryEpoch' => str_repeat('0e', 32)]);
        $this->record($stale, 'Agus');
        $this->app->instance(CommandPoints::class, new FailurePoints(failAtOwnWrite: 'report'));
        $this->as('Agus');
        $this->check($stale)->assertStatus(503)->assertJsonPath('code', 'SERVICE_BUSY');
        $this->assertSame(0, $this->observer()->table('stale_intent_reports')->count());
        $this->assertSame(['PENDING'], $this->outcomesOf($stale));

        // The settlement after reading an existing identity, then a fence.
        $committed = $this->committedWithPendingAndHeld();
        [$fenced] = $this->pendingAndHeldWithoutIdentity();
        $this->as('Agus');
        $this->check($fenced)->assertOk()->assertJsonPath('outcome', 'NOT_COMMITTED');
        $this->record([...$fenced, 'idempotencyKey' => (string) Str::uuid()], 'Agus');

        $this->app->instance(CommandPoints::class, new FailurePoints(failAtOwnWrite: 'settlement'));
        $before = [$this->receiptsOf($committed), $this->receiptsOf($fenced)];
        $this->check($committed)->assertStatus(503)->assertJsonPath('code', 'SERVICE_BUSY');
        $this->check($fenced)->assertStatus(503)->assertJsonPath('code', 'SERVICE_BUSY');
        $this->assertSame($before, [$this->receiptsOf($committed), $this->receiptsOf($fenced)]);

        // A repeated check then settles them.
        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->check($committed)->assertOk()->assertJsonPath('outcome', 'COMMITTED');
        $this->check($fenced)->assertOk()->assertJsonPath('outcome', 'NOT_COMMITTED');
        $this->assertSame(['COMMITTED', 'COMMITTED'], $this->outcomesOf($committed));
        $this->assertSame(['NOT_COMMITTED', 'NOT_COMMITTED', 'NOT_COMMITTED'], $this->outcomesOf($fenced));
    }

    public function test_an_earlier_epoch_intent_whose_identity_is_in_the_restored_data_answers_committed(): void
    {
        $this->as('Agus');
        $envelope = $this->envelope();
        $commandId = $this->probe($envelope)->assertStatus(201)->json('commandId');

        // A restore replaces the epoch (TASK-037's after-restore procedure).
        DB::transaction(static fn (): string => app(RecoveryEpoch::class)->replace());

        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('commandId', $commandId);
        $this->assertSame(0, $this->observer()->table('stale_intent_reports')->count());
    }

    public function test_a_reconciliations_fence_answers_not_committed_and_settles_the_held_receipt(): void
    {
        $earlier = str_repeat('0d', 32);
        $envelope = $this->envelope(changes: ['recoveryEpoch' => $earlier]);
        $this->record($envelope, 'Agus');
        $this->as('Agus');
        $this->check($envelope)->assertStatus(409);

        // The restore reconciliation (OP-05, TASK-037) writes the fence of the
        // intent in the current epoch, with the Owner as its actor.
        $fence = DB::transaction(fn (): int => app(CommandIdentities::class)->insertFence(Probe::OPERATION, self::text($envelope['sourceIntentId']), $this->people['Hendra']['id'], $this->epoch, now()->format('Y-m-d H:i:s.uP')));

        $this->check($envelope)->assertOk()->assertExactJson(['outcome' => 'NOT_COMMITTED', 'operation' => Probe::OPERATION]);
        $receipt = $this->receiptsOf($envelope)[0];
        $this->assertSame(['NOT_COMMITTED', 'EPOCH_STALE', $fence, $this->people['Agus']['id']], [$receipt['outcome'], $receipt['held_code'], $receipt['settled_by_command_id'], $receipt['settled_by_user_id']]);
    }

    public function test_the_committed_result_is_given_under_the_checkers_current_authorization(): void
    {
        $this->as('Agus');
        $envelope = $this->envelope();
        $this->probe($envelope)->assertStatus(201);

        // Agus loses CAP-04: his check still concludes, without the result.
        $this->assertSame(1, $this->observer()->table('users')->where('id', $this->people['Agus']['id'])->update(['role_operations_admin' => false, 'role_cashier' => true]));
        $this->as('Agus');
        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result', null);
    }

    public function test_the_check_is_refused_for_anything_outside_its_three_fields_and_formats(): void
    {
        $this->as('Agus');
        $valid = ['operation' => 'sale.checkout', 'sourceIntentId' => (string) Str::uuid(), 'recoveryEpoch' => $this->epoch];

        // A scheduled command's intent is never a user's to check (review
        // finding R-03): posting_block.block is refused.
        foreach ([['operation' => 'posting_block.block'], ['operation' => 'sale.refund'], ['operation' => Probe::OPERATION.'x'], ['sourceIntentId' => 'x'], ['recoveryEpoch' => 'XYZ'], ['idempotencyKey' => (string) Str::uuid()], ['payload' => []]] as $change) {
            $this->withServerVariables([])->postJson('/commands/resolve', [...$valid, ...$change])->assertStatus(422)
                ->assertExactJson(['outcome' => 'REJECTED', 'code' => 'VALIDATION_FAILED', 'messageKey' => 'ux.state.validation', 'params' => [], 'fields' => [], 'lines' => []]);
        }

        $this->assertSame(0, $this->observer()->table('command_identities')->where('operation', '<>', 'user.create')->count());
    }

    /**
     * Agus's probe committed with its receipt left PENDING by a failed
     * marking, and a repeat with another payload HELD by IDEMPOTENCY_CONFLICT.
     *
     * @return array<string, mixed>
     */
    private function committedWithPendingAndHeld(): array
    {
        $this->as('Agus');
        $envelope = $this->envelope();
        $this->app->instance(CommandPoints::class, new FailurePoints(failAtOwnWrite: 'marking'));
        $this->probe($envelope)->assertStatus(201);
        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->probe([...$envelope, 'payload' => ['note' => 'Lain']])->assertStatus(409);
        $this->assertSame(['PENDING', 'HELD'], $this->outcomesOf($envelope));

        return $envelope;
    }

    /**
     * An intent of Agus with no identity: one receipt PENDING, one HELD by
     * IDEMPOTENCY_CONFLICT, written through the Commands module's service.
     *
     * @return array{array<string, mixed>}
     */
    private function pendingAndHeldWithoutIdentity(): array
    {
        $envelope = $this->envelope();
        $this->record($envelope, 'Agus');
        $held = $this->record([...$envelope, 'idempotencyKey' => (string) Str::uuid()], 'Agus');
        app(RequestReceipts::class)->mark($held, RequestReceipts::HELD, 'IDEMPOTENCY_CONFLICT');

        return [$envelope];
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function record(array $envelope, string $person): int
    {
        return app(RequestReceipts::class)->record($this->people[$person]['id'], Probe::OPERATION, self::text($envelope['sourceIntentId']), self::text($envelope['idempotencyKey']), self::text($envelope['recoveryEpoch']), now()->format('Y-m-d H:i:s.uP'));
    }

    /**
     * Both receipts of the intent settled with the outcome, the identity or
     * fence and the checker; the held one keeps its code.
     *
     * @param  array<string, mixed>  $envelope
     */
    private function assertSettled(array $envelope, string $outcome, mixed $settledBy, string $checker): void
    {
        $receipts = $this->receiptsOf($envelope);
        $this->assertSame([$outcome, $outcome], array_column($receipts, 'outcome'));
        $this->assertSame([null, 'IDEMPOTENCY_CONFLICT'], array_column($receipts, 'held_code'));
        $this->assertSame([$settledBy, $settledBy], array_column($receipts, 'settled_by_command_id'));
        $this->assertSame([$this->people[$checker]['id'], $this->people[$checker]['id']], array_column($receipts, 'settled_by_user_id'));
    }
}
