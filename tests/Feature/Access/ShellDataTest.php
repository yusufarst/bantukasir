<?php

namespace Tests\Feature\Access;

use App\Database\CommandPoints;
use App\Domain\Commands\RequestReceipts;
use Illuminate\Support\Str;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Race\FailurePoints;

/**
 * RD-03, the shell's indicators (AC-009-06; ROUTE_CONTRACTS RD-03, RT-08,
 * RT-10; INTERACTION_CONTRACTS section 3.3; SECURITY_MODEL SEC-SESS-02;
 * EXECUTION_CONTEXT section 10, a read; suites AUTH01, AUTH02 and CMD01;
 * TASK-009 scope item 7): the number of the signed-in user's own request
 * receipts that are PENDING or HELD — never another user's, never a
 * resolved one, never a print request — and the as-of time; for every role
 * and the combined account; no parameter; a background read leaves the
 * idle time as it was and writes nothing.
 */
final class ShellDataTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const array ACTORS = ['Hendra' => 'owner', 'Agus' => 'ops', 'Rina' => 'cashier', 'Dewi' => 'combined'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-28T03:00:00.250000Z');
        $this->setUpCommands();
    }

    public function test_the_count_holds_exactly_the_users_own_unresolved_receipts_with_the_as_of_time(): void
    {
        $this->as('Agus');
        $payload = ['note' => 'Catatan uji'];

        // Resolved: COMMITTED, REJECTED and NOT_COMMITTED are not counted.
        $this->probe($this->envelope($payload))->assertStatus(201);
        $this->probe($this->envelope([]))->assertStatus(422);
        $fenced = $this->envelope($payload);
        $this->check($fenced)->assertOk();

        // PENDING (the marking failed) and HELD (a conflict) are.
        $this->app->instance(CommandPoints::class, new FailurePoints(failAtOwnWrite: 'marking'));
        $this->probe($this->envelope($payload))->assertStatus(201);
        $this->app->instance(CommandPoints::class, new FailurePoints);
        $held = $this->envelope($payload);
        $this->probe($held)->assertStatus(201);
        $this->probe([...$held, 'payload' => ['note' => 'Lain']])->assertStatus(409);

        // A print request's receipt is never counted: print is exempt (NC-13).
        app(RequestReceipts::class)->record($this->people['Agus']['id'], 'document.print_receipt', (string) Str::uuid(), (string) Str::uuid(), $this->epoch, '2026-10-28 03:00:00+00');

        // Another user's unresolved receipt is never counted for Agus.
        app(RequestReceipts::class)->record($this->people['Rina']['id'], 'sale.checkout', (string) Str::uuid(), (string) Str::uuid(), $this->epoch, '2026-10-28 03:00:00+00');

        $this->as('Agus');
        $answer = $this->getJson('/data/shell')->assertOk();
        $answer->assertExactJson(['unresolvedRequests' => 2, 'asOf' => '2026-10-28T03:00:00.250000Z']);
        $this->assertJsonChannel($answer, 'data.shell', 'ops');
        $this->assertStringContainsString('no-store', (string) $answer->headers->get('Cache-Control'));

        $this->as('Rina');
        $this->getJson('/data/shell')->assertOk()->assertJsonPath('unresolvedRequests', 1);

        // The Owner's count is his own, never every user's.
        $this->as('Hendra');
        $this->getJson('/data/shell')->assertOk()->assertJsonPath('unresolvedRequests', 0);
    }

    public function test_every_role_and_the_combined_account_get_their_own_count_on_the_allowlisted_channel(): void
    {
        foreach (self::ACTORS as $person => $actor) {
            $this->as($person);
            $answer = $this->getJson('/data/shell')->assertOk();
            $this->assertJsonChannel($answer, 'data.shell', $actor, [$this->people[$person]['password']]);
            $answer->assertJsonPath('unresolvedRequests', 0);
        }

        $this->browse(null);
        $this->getJson('/data/shell')->assertStatus(401);
    }

    public function test_the_read_takes_no_parameter_and_a_background_read_leaves_the_idle_time_and_writes_nothing(): void
    {
        $this->as('Rina');
        $this->getJson('/data/shell?scope=all')->assertStatus(422)
            ->assertExactJson(['outcome' => 'REJECTED', 'code' => 'VALIDATION_FAILED', 'messageKey' => 'ux.state.validation', 'params' => [], 'fields' => [], 'lines' => []]);
        $this->getJson('/data/shell?user_id=1')->assertStatus(422);

        $page = $this->inertiaPage($this->get('/account')->assertOk());
        $version = $page['version'] ?? null;
        $before = $this->counts();
        $this->travel(5)->minutes();
        $this->withHeaders(['X-Background-Request' => '1'])->getJson('/data/shell')->assertOk();
        $this->flushHeaders();
        $this->assertSame($before, $this->counts(), 'RD-03 writes nothing (TR-HTTP-02).');

        $idle = $this->withHeaders([
            'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => is_string($version) ? $version : '',
            'X-Background-Request' => '1', 'X-Inertia-Partial-Component' => 'access/Account', 'X-Inertia-Partial-Data' => 'passwordChanged',
        ])->get('/account');
        $this->assertSame(1500, $idle->json('props.auth.session.idleEndsIn'), 'The background read did not count as activity: 25 minutes left.');
    }
}
