<?php

namespace Tests\Feature\Commands;

use App\Database\CommandPoints;
use App\Domain\Commands\RequestReceipts;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\Commands\Probe;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Race\FailurePoints;

/**
 * RD-04, the unresolved request receipts (AC-008-07; ROUTE_CONTRACTS RD-04,
 * RT-08; INTERACTION_CONTRACTS section 3.6; ADR-009 section 2; CAP-34;
 * EXECUTION_CONTEXT section 10, a read; suites AUTH01 and CMD01; TASK-008
 * scope item 6): exactly the receipts PENDING or HELD in scope, the actor's
 * own or, for the Owner with scope=all, every user's; scope=all refused to
 * anyone else; pages of at most 100 with a cursor that never widens the
 * scope; never a payload.
 */
final class UnresolvedRequestsTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCommands();
    }

    public function test_the_list_holds_exactly_the_unresolved_receipts_in_scope_and_never_a_payload(): void
    {
        $agus = $this->unresolvedOf('Agus');
        $dewi = $this->unresolvedOf('Dewi');

        $this->as('Agus');
        $own = $this->getJson('/data/command-requests')->assertOk();
        $this->assertJsonChannel($own, 'data.requests', 'ops');
        $this->assertSame([[$agus['pending'], 'PENDING', 'current'], [$agus['held'], 'HELD', 'current'], [$agus['stale'], 'HELD', 'earlier']], self::rows($own));
        $this->assertSame([Probe::FAMILY, Probe::OPERATION], [$own->json('rows.0.family'), $own->json('rows.0.operation')]);
        $own->assertDontSee('Rahasia catatan')->assertJsonPath('nextCursor', null)->assertJsonPath('previousCursor', null);

        $this->as('Dewi');
        $this->assertSame([[$dewi['pending'], 'PENDING', 'current'], [$dewi['held'], 'HELD', 'current'], [$dewi['stale'], 'HELD', 'earlier']], self::rows($this->getJson('/data/command-requests?scope=own')->assertOk()));

        // The Owner: his own, none, and with scope=all every user's, with the user.
        $this->as('Hendra');
        $this->getJson('/data/command-requests')->assertOk()->assertExactJson(['rows' => [], 'nextCursor' => null, 'previousCursor' => null]);
        $all = $this->getJson('/data/command-requests?scope=all')->assertOk();
        $this->assertJsonChannel($all, 'data.requests.all', 'owner');
        $rows = self::rowsOf($all);
        $this->assertCount(6, $rows);
        $dewiPending = array_values(array_filter($rows, static fn (array $row): bool => $row['sourceIntentId'] === $dewi['pending']));
        $this->assertSame(['id' => $this->people['Dewi']['id'], 'displayName' => 'Dewi'], $dewiPending[0]['user'] ?? null);
        $all->assertDontSee('Rahasia catatan');
    }

    public function test_scope_all_is_refused_to_anyone_but_the_owner(): void
    {
        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $refusals = $this->observer()->table('security_log_entries')->where('event_type', 'AUTHORIZATION_REFUSED')->count();

            $this->getJson('/data/command-requests?scope=all')->assertStatus(403)
                ->assertExactJson(['outcome' => 'REJECTED', 'code' => 'FORBIDDEN', 'messageKey' => 'ux.state.forbidden', 'params' => [], 'fields' => [], 'lines' => []]);
            $this->assertSame($refusals + 1, $this->observer()->table('security_log_entries')->where('event_type', 'AUTHORIZATION_REFUSED')->count(), $person);
        }
    }

    public function test_a_body_that_disagrees_with_the_query_string_never_widens_the_scope_or_the_page(): void
    {
        // Review finding R-01: the parameters come from the query string
        // alone; a body is refused, so no check reads one value and the
        // query another.
        $this->unresolvedOf('Agus');
        $this->unresolvedOf('Dewi');

        $this->as('Rina');
        $refusals = $this->observer()->table('security_log_entries')->where('event_type', 'AUTHORIZATION_REFUSED')->count();
        $this->json('GET', '/data/command-requests?scope=all&perPage=1000000', ['scope' => 'own', 'perPage' => '10'])->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->assertSame($refusals + 1, $this->observer()->table('security_log_entries')->where('event_type', 'AUTHORIZATION_REFUSED')->count());

        $this->as('Agus');
        foreach ([['scope' => 'all'], ['perPage' => '100'], ['after' => 'x'], ['other' => '1']] as $body) {
            $this->json('GET', '/data/command-requests?scope=own&perPage=2', $body)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // The Owner too gets nothing from a body.
        $this->as('Hendra');
        $this->json('GET', '/data/command-requests', ['scope' => 'all'])->assertStatus(422);
        $this->getJson('/data/command-requests?after=a&before=b')->assertStatus(422);
    }

    public function test_pages_are_bounded_and_the_cursor_never_widens_the_scope(): void
    {
        $this->unresolvedOf('Agus');
        $this->unresolvedOf('Dewi');

        $this->as('Agus');
        $first = $this->getJson('/data/command-requests?perPage=2')->assertOk();
        $this->assertCount(2, self::rowsOf($first));
        $next = $first->json('nextCursor');
        $this->assertIsString($next);
        $second = $this->getJson('/data/command-requests?perPage=2&after='.$next)->assertOk();
        $this->assertCount(1, self::rowsOf($second));
        $second->assertJsonPath('nextCursor', null);
        $previous = $second->json('previousCursor');
        $this->assertIsString($previous);
        $back = $this->getJson('/data/command-requests?perPage=2&before='.$previous)->assertOk();
        $this->assertSame(array_column(self::rowsOf($first), 'sourceIntentId'), array_column(self::rowsOf($back), 'sourceIntentId'));
        $back->assertJsonPath('previousCursor', null);
        $this->assertIsString($back->json('nextCursor'));

        // Dewi with Agus's cursor sees only her own rows after it.
        $this->as('Dewi');
        $dewis = self::rowsOf($this->getJson('/data/command-requests?after='.$next)->assertOk());
        $this->assertNotSame([], $dewis);

        foreach ($dewis as $row) {
            $this->assertArrayNotHasKey('user', $row);
        }

        $agus = array_map(self::text(...), array_column(self::rowsOf($first), 'sourceIntentId'));
        $this->assertSame([], array_intersect($agus, array_map(self::text(...), array_column($dewis, 'sourceIntentId'))));

        // Only the listed parameters, each in its format (TR-VAL-07, RT-08).
        foreach (['perPage=101', 'perPage=0', 'perPage=abc', 'scope=mine', 'after=***', 'after='.Str::random(20), 'sort=received_at', 'userId=1'] as $query) {
            $this->getJson('/data/command-requests?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->getJson('/data/command-requests?perPage=100')->assertOk();
    }

    /**
     * Three unresolved receipts of the person — one PENDING, one HELD by a
     * conflict, one HELD by an earlier epoch — and one of each resolved
     * outcome, which the list never shows; every payload holds a note that
     * must never be listed.
     *
     * @return array{pending: string, held: string, stale: string}
     */
    private function unresolvedOf(string $person): array
    {
        $this->as($person);
        $payload = ['note' => 'Rahasia catatan'];

        // COMMITTED, REJECTED and NOT_COMMITTED: not listed.
        $this->probe($this->envelope($payload))->assertStatus(201);
        $this->probe($this->envelope([]))->assertStatus(422);
        $abandoned = $this->envelope($payload);
        $this->check($abandoned)->assertOk();
        $this->probe($abandoned)->assertStatus(409);

        // PENDING: the marking failed.
        $pending = $this->envelope($payload);
        $this->app->instance(CommandPoints::class, new FailurePoints(failAtOwnWrite: 'marking'));
        $this->probe($pending)->assertStatus(201);
        $this->app->instance(CommandPoints::class, new FailurePoints);

        // HELD by IDEMPOTENCY_CONFLICT; its intent's first receipt COMMITTED.
        $held = $this->envelope($payload);
        $this->probe($held)->assertStatus(201);
        $this->probe([...$held, 'payload' => ['note' => 'Lain']])->assertStatus(409);

        // HELD by EPOCH_STALE.
        $stale = $this->envelope($payload, ['recoveryEpoch' => str_repeat('0c', 32)]);
        $this->probe($stale)->assertStatus(409);

        $this->assertSame(['PENDING'], $this->outcomesOf($pending));
        $this->assertSame([RequestReceipts::COMMITTED, RequestReceipts::HELD], $this->outcomesOf($held));

        return ['pending' => self::text($pending['sourceIntentId']), 'held' => self::text($held['sourceIntentId']), 'stale' => self::text($stale['sourceIntentId'])];
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return list<array{mixed, mixed, mixed}>
     */
    private static function rows(TestResponse $response): array
    {
        return array_map(static fn (array $row): array => [$row['sourceIntentId'] ?? null, $row['outcome'] ?? null, $row['epoch'] ?? null], self::rowsOf($response));
    }
}
