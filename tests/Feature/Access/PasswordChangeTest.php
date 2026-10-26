<?php

namespace Tests\Feature\Access;

use App\Actions\Access\UpdateOwnPassword;
use App\Database\CommandPoints;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Http\Browsers;
use Tests\Harness\Race\FailurePoints;
use Tests\Harness\Race\InjectedFailure;

/**
 * RA-05, the change of one's own password (AC-006-05; ROUTE_CONTRACTS RA-05;
 * INTERACTION_CONTRACTS IC-12; SECURITY_MODEL SEC-AUTH-05, SEC-AUTH-11,
 * SEC-SESS-04; TECHNICAL_RULES TR-LOCK-05). It takes exactly its three fields,
 * needs the current password — a wrong one is one uniform answer, recorded
 * in the security log — enforces AICWDF-COMPAT-8, revokes the user's other
 * sign-in sessions and keeps this one, writes PASSWORD_CHANGE and returns to
 * RP-003 with its message; nothing is written when it fails.
 */
final class PasswordChangeTest extends DatabaseTestCase
{
    use Browsers;

    /** @var array{id: int, email: string, password: string} */
    private array $rina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rina = TokoContoh::person('Rina');
        $this->useDatabaseSessions();
    }

    public function test_the_change_revokes_the_other_sessions_keeps_this_one_and_is_audited(): void
    {
        $here = $this->signInBrowser($this->rina['email'], $this->rina['password']);
        $there = $this->signInBrowser($this->rina['email'], $this->rina['password']);
        $new = TokoContoh::newPassword();

        $this->browse($here);
        $response = $this->put('/user/password', ['current_password' => $this->rina['password'], 'password' => $new, 'password_confirmation' => $new], ['X-Inertia' => 'true']);

        $response->assertStatus(303)->assertRedirect(route('account.show'));
        $this->assertTrue(Hash::check($new, $this->readText($this->observer()->table('users')->where('id', $this->rina['id'])->value('password'))));

        $this->browse($here);
        $page = $this->get('/account')->assertOk()->viewData('page');
        $this->assertIsArray($page);
        $this->assertTrue(((array) $page['props'])['passwordChanged']);

        $this->browse($there);
        $this->get('/account')->assertRedirect('/login');

        $event = (array) $this->observer()->table('audit_events')->where('action', 'PASSWORD_CHANGE')->sole();
        $this->assertSame(['SECURITY', 'USER', $this->rina['id'], 'Rina', 'user', $this->rina['id']], [$event['category'], $event['actor_kind'], $event['actor_user_id'], $event['actor_display_name'], $event['subject_type'], $event['subject_id']]);
        $this->assertSame(['sessionsRevoked' => 1], json_decode((string) $event['change_data'], true));
        $this->assertSame('SESSION_REVOKED', $this->observer()->table('security_log_entries')->orderByDesc('id')->value('event_type'));

        $this->signInBrowser($this->rina['email'], $new);
    }

    public function test_a_wrong_current_password_is_one_uniform_answer_and_changes_nothing(): void
    {
        $session = $this->signInBrowser($this->rina['email'], $this->rina['password']);
        $before = $this->snapshot('users');
        $new = TokoContoh::newPassword();

        $this->browse($session);
        $this->from('/account/password')->put('/user/password', ['current_password' => 'Salah#'.$this->rina['password'], 'password' => $new, 'password_confirmation' => $new])
            ->assertRedirect(route('account.password'));

        $this->assertSame(['form' => ['Kata sandi sekarang tidak cocok']], $this->flashedErrors());
        $this->assertSame($before, $this->snapshot('users'));
        $this->assertSame(0, $this->observer()->table('audit_events')->where('action', 'PASSWORD_CHANGE')->count());

        $entry = (array) $this->observer()->table('security_log_entries')->orderByDesc('id')->first();
        $this->assertSame(['PASSWORD_CONFIRMATION_FAILED', 'USER', $this->rina['id'], 'REFUSED', 'user-password.update'], [$entry['event_type'], $entry['actor_kind'], $entry['actor_user_id'], $entry['outcome_code'], $entry['route_name']]);
    }

    public function test_the_new_password_meets_the_profile_its_repetition_and_the_allowlist(): void
    {
        $session = $this->signInBrowser($this->rina['email'], $this->rina['password']);

        $new = TokoContoh::newPassword();
        $cases = [
            'too short' => [['current_password' => $this->rina['password'], 'password' => 'Aa1#aa', 'password_confirmation' => 'Aa1#aa'], ['password' => ['Kata sandi belum memenuhi syarat: minimal 8 karakter, dengan huruf besar, huruf kecil, angka, dan simbol.']]],
            'no symbol' => [['current_password' => $this->rina['password'], 'password' => 'Aa1aaaaaa', 'password_confirmation' => 'Aa1aaaaaa'], ['password' => ['Kata sandi belum memenuhi syarat: minimal 8 karakter, dengan huruf besar, huruf kecil, angka, dan simbol.']]],
            'too long' => [['current_password' => $this->rina['password'], 'password' => str_repeat('Aa1#', 33), 'password_confirmation' => str_repeat('Aa1#', 33)], ['password' => ['Paling banyak 128 karakter.']]],
            'repetition differs' => [['current_password' => $this->rina['password'], 'password' => $new, 'password_confirmation' => TokoContoh::newPassword()], ['password_confirmation' => ['Kata sandi yang diulang tidak sama.']]],
            'all missing' => [[], ['current_password' => ['Wajib diisi.'], 'password' => ['Wajib diisi.'], 'password_confirmation' => ['Wajib diisi.']]],
            'an unknown field' => [['current_password' => $this->rina['password'], 'password' => $new, 'password_confirmation' => $new, 'role_super_admin' => '1'], ['form' => ['Ada isian yang perlu diperbaiki. Tidak ada yang disimpan.']]],
        ];

        foreach ($cases as $case => [$fields, $errors]) {
            $this->browse($session);
            $this->put('/user/password', $fields)->assertRedirect(route('account.password'));
            $this->assertSame($errors, $this->flashedErrors(), $case);
        }

        $this->assertNull(session()->getOldInput('password'));
        $this->assertSame(0, $this->observer()->table('audit_events')->where('action', 'PASSWORD_CHANGE')->count());
        $this->assertTrue(Hash::check($this->rina['password'], $this->readText($this->observer()->table('users')->where('id', $this->rina['id'])->value('password'))));
    }

    public function test_a_failure_after_any_write_step_changes_nothing(): void
    {
        // Two sign-in sessions of the account, which the step "sessions
        // revoked" deletes and its rollback keeps (review finding R-05).
        $sessions = [$this->signInBrowser($this->rina['email'], $this->rina['password']), $this->signInBrowser($this->rina['email'], $this->rina['password'])];
        $user = User::query()->findOrFail($this->rina['id']);
        $before = $this->snapshot('users');

        foreach ([1, 2, 3] as $step) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $new = TokoContoh::newPassword();

            try {
                app(UpdateOwnPassword::class)->update($user, ['current_password' => $this->rina['password'], 'password' => $new, 'password_confirmation' => $new]);
                $this->fail('No failure after write step '.$step);
            } catch (InjectedFailure) {
                $this->assertSame($before, $this->snapshot('users'));
                $this->assertSame(0, $this->observer()->table('audit_events')->where('action', 'PASSWORD_CHANGE')->count());
                $this->assertEqualsCanonicalizing($sessions, $this->observer()->table('sessions')->where('user_id', $this->rina['id'])->pluck('id')->all());
            }
        }
    }

    public function test_the_account_is_locked_once_for_its_own_update(): void
    {
        $points = new FailurePoints;
        $this->app->instance(CommandPoints::class, $points);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $new = TokoContoh::newPassword();

        app(UpdateOwnPassword::class)->update(User::query()->findOrFail($this->rina['id']), ['current_password' => $this->rina['password'], 'password' => $new, 'password_confirmation' => $new]);

        $locks = array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, ' for ')));
        $this->assertSame(['select * from "users" where "users"."id" = ? limit 1 for no key update'], $locks);
        $this->assertSame(['after_lock:2.2', 'after_write:1', 'after_write:2', 'after_write:3', 'before_commit'], $points->passed);
    }
}
