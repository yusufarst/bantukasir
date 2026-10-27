<?php

namespace Tests\Feature\Access;

use App\Domain\Access\ActorGuard;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Access\Capability;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The in-transaction guard of the actor (SECURITY_MODEL SEC-AZ-04, SEC-AZ-05;
 * TECHNICAL_RULES section 7.1 step 2.1, TR-LOCK-02, TR-LOCK-05, RACE-04;
 * REQ-ACC-09; suite AUTH01; TASK-007 scope item 1): inside the command's
 * transaction, under the lock of the actor's account, the account must still
 * be ACTIVE and still hold every capability the command needs; otherwise the
 * command writes nothing and is answered FORBIDDEN with its security log
 * entry. The share lock makes a deactivation or role change wait until the
 * command has committed, and the change that commits first is the one in
 * force. TASK-008's Action base calls the guard for every command.
 */
final class ActorGuardTest extends DatabaseTestCase
{
    public function test_an_active_account_that_holds_the_capabilities_passes_and_its_row_is_share_locked(): void
    {
        $agus = TokoContoh::person('Agus');

        DB::transaction(function () use ($agus): void {
            $actor = app(ActorGuard::class)->hold($agus['id'], [Capability::Cap04, Capability::Cap11]);
            $this->assertSame($agus['id'], $actor->id);

            // A deactivation waits for the lock; with a short lock timeout of its
            // own it gives up instead of changing the account under the command.
            $other = $this->observer();
            $other->statement("SET lock_timeout = '200ms'");

            try {
                $other->table('users')->where('id', $agus['id'])->update(['status' => 'DEACTIVATED', 'deactivated_at' => now()]);
                $this->fail('The account changed while a command held its share lock.');
            } catch (QueryException $refused) {
                $this->assertSame('55P03', (string) $refused->getCode());
            }
        });

        $this->assertSame('ACTIVE', $this->observer()->table('users')->where('id', $agus['id'])->value('status'));
    }

    public function test_the_own_account_as_target_is_locked_once_for_update(): void
    {
        $rina = TokoContoh::person('Rina');

        DB::transaction(function () use ($rina): void {
            app(ActorGuard::class)->hold($rina['id'], [Capability::Cap01], ownAccountIsTarget: true);

            $other = $this->observer();
            $other->statement("SET lock_timeout = '200ms'");

            try {
                $other->select('SELECT id FROM users WHERE id = ? FOR SHARE', [$rina['id']]);
                $this->fail('A share lock was granted beside the command\'s update lock.');
            } catch (QueryException $refused) {
                $this->assertSame('55P03', (string) $refused->getCode());
            }
        });
    }

    public function test_an_account_no_longer_active_or_no_longer_holding_the_capability_is_refused(): void
    {
        $agus = TokoContoh::person('Agus');
        $dewi = TokoContoh::person('Dewi');

        // Not a capability of Operations: Owner-only.
        $this->assertRefused($agus['id'], [Capability::Cap16]);
        // The combined account never holds an Owner-only capability.
        $this->assertRefused($dewi['id'], [Capability::Cap22]);
        // Every capability must be held, not one of them.
        $this->assertRefused($agus['id'], [Capability::Cap04, Capability::Cap21]);
        // A restricted cell opens nothing: CAP-14 of a cashier.
        $this->assertRefused(TokoContoh::person('Rina')['id'], [Capability::Cap14]);
        // An account that does not exist.
        $last = $this->runtime()->table('users')->max('id');
        $this->assertIsInt($last);
        $this->assertRefused($last + 1, [Capability::Cap01]);

        // A role change that committed first is in force at validation (RACE-04).
        // The user management of TASK-011 does not exist yet: the change is
        // written as the runtime role, as TASK-006's tests deactivate.
        DB::table('users')->where('id', $dewi['id'])->update(['role_cashier' => false]);
        $this->assertRefused($dewi['id'], [Capability::Cap21]);

        // A deactivation that committed first.
        DB::table('users')->where('id', $agus['id'])->update(['status' => 'DEACTIVATED', 'deactivated_at' => now()]);
        $this->assertRefused($agus['id'], [Capability::Cap01]);
    }

    public function test_the_guard_runs_only_inside_a_transaction(): void
    {
        $this->expectException(LogicException::class);

        app(ActorGuard::class)->hold(TokoContoh::person('Rina')['id'], [Capability::Cap01]);
    }

    public function test_a_refused_guard_is_answered_forbidden_after_the_rollback_and_recorded(): void
    {
        $agus = TokoContoh::person('Agus');
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:CAP-01'])
            ->post('/harness/guarded', static function () use ($agus): string {
                DB::transaction(static function () use ($agus): void {
                    DB::table('audit_events')->count();
                    app(ActorGuard::class)->hold($agus['id'], [Capability::Cap16]);
                });

                return 'ran';
            });

        $this->post('/login', ['email' => $agus['email'], 'password' => $agus['password']])->assertRedirect('/');
        $this->postJson('/harness/guarded')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');

        $last = (array) $this->observer()->table('security_log_entries')->orderByDesc('id')->first();
        $this->assertSame(['AUTHORIZATION_REFUSED', 'USER', $agus['id'], 'REFUSED'], [$last['event_type'], $last['actor_kind'], $last['actor_user_id'], $last['outcome_code']]);
    }

    /**
     * @param  list<Capability>  $needed
     */
    private function assertRefused(int $actorId, array $needed): void
    {
        try {
            DB::transaction(static fn () => app(ActorGuard::class)->hold($actorId, $needed));
            $this->fail('The guard let the actor through.');
        } catch (ActorNotAllowed) {
            $this->assertSame(0, DB::transactionLevel());
        }
    }
}
