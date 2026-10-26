<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Http\Browsers;

/**
 * The sign-in session (AC-006-03; ROUTE_CONTRACTS RT-04, RT-10, RA-02, RA-07;
 * SECURITY_MODEL SEC-AUTH-09, SEC-AUTH-10, SEC-SESS-02 to SEC-SESS-04; ADR-004
 * item 8; suite SIGNIN01). With the clock controlled: it ends 30 minutes after
 * the last user-initiated request — a request marked as a background request
 * never counts, RA-07 does — and 12 hours after the sign-in at most; an
 * account that is no longer ACTIVE loses its session at once; and a sign-out
 * invalidates the session, rotates the CSRF token and ends with a full page
 * load. An ended sign-in session answers UNAUTHENTICATED — the sign-in page
 * with its note, Inertia's location answer, or 401 for JSON — and changes
 * nothing but the sign-in session and the security log, so no cash session
 * (TASK-027 verifies it again with real sessions).
 */
final class SignInSessionTest extends DatabaseTestCase
{
    use Browsers;

    /** @var array{id: int, email: string, password: string} */
    private array $rina;

    protected function setUp(): void
    {
        parent::setUp();

        // The pages render the root view, whose assets the PHP tests never
        // read (Tests\TestCase).
        $this->withoutVite();
        $this->travelTo('2026-10-17T02:00:00Z');
        $this->rina = TokoContoh::person('Rina');
        $this->useDatabaseSessions();
    }

    public function test_the_idle_limit_counts_from_the_last_user_initiated_request(): void
    {
        $session = $this->signInBrowser($this->rina['email'], $this->rina['password']);

        foreach ([29, 29, 29] as $minutes) {
            $this->travel($minutes)->minutes();
            $this->browse($session);
            $this->get('/account')->assertOk();
        }

        $this->travel(30)->minutes();
        $this->browse($session);
        $this->get('/account')->assertRedirect('/login');

        $this->assertSessionEnded($session);
    }

    public function test_a_background_request_never_counts_as_activity(): void
    {
        $session = $this->signInBrowser($this->rina['email'], $this->rina['password']);

        for ($minute = 5; $minute < 30; $minute += 5) {
            $this->travel(5)->minutes();
            $this->browse($session);
            $this->getJson('/user/confirmed-password-status', ['X-Background-Request' => '1'])->assertOk();
        }

        $this->travel(5)->minutes();
        $this->browse($session);
        $this->getJson('/user/confirmed-password-status', ['X-Background-Request' => '1'])
            ->assertStatus(401)
            ->assertExactJson(['outcome' => 'REJECTED', 'code' => 'UNAUTHENTICATED', 'messageKey' => 'ux.state.signed_out', 'params' => [], 'fields' => [], 'lines' => []]);

        $this->assertSessionEnded($session);
    }

    public function test_a_user_activity_request_counts_and_answers_no_content(): void
    {
        $session = $this->signInBrowser($this->rina['email'], $this->rina['password']);

        $this->travel(25)->minutes();
        $this->browse($session);
        $this->post('/session/activity')->assertNoContent();

        // The header can only make a request not count (RT-10).
        $this->travel(25)->minutes();
        $this->browse($session);
        $this->post('/session/activity', [], ['X-Background-Request' => '1'])->assertNoContent();

        $this->travel(5)->minutes();
        $this->browse($session);
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_the_absolute_limit_ends_the_sign_in_session_after_12_hours_whatever_the_activity(): void
    {
        $session = $this->signInBrowser($this->rina['email'], $this->rina['password']);

        for ($elapsed = 20; $elapsed < 12 * 60; $elapsed += 20) {
            $this->travel(20)->minutes();
            $this->browse($session);
            $this->post('/session/activity')->assertNoContent();
        }

        $this->travel(20)->minutes();
        $this->browse($session);
        $this->post('/session/activity', [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('login'));

        $this->assertSessionEnded($session);
    }

    public function test_an_ended_sign_in_session_leads_to_the_sign_in_page_with_its_note(): void
    {
        $session = $this->signInBrowser($this->rina['email'], $this->rina['password']);

        $this->travel(31)->minutes();
        $this->browse($session);
        $ended = $this->get('/account');
        $ended->assertRedirect('/login');

        $this->browse($this->sessionOf($ended));
        $page = $this->get('/login')->assertOk()->viewData('page');
        $this->assertIsArray($page);
        $this->assertIsArray($page['props']);
        $this->assertSame('signed_out', $page['props']['notice']);
    }

    public function test_an_account_that_is_no_longer_active_loses_its_sign_in_session(): void
    {
        $session = $this->signInBrowser($this->rina['email'], $this->rina['password']);

        DB::table('users')->where('id', $this->rina['id'])->update(['status' => 'DEACTIVATED', 'deactivated_at' => '2026-10-17 02:05:00+00']);

        $this->browse($session);
        $this->get('/account')->assertRedirect('/login');
        $this->assertSessionEnded($session);
    }

    public function test_a_sign_out_invalidates_the_session_rotates_the_token_and_loads_the_sign_in_page(): void
    {
        $session = $this->signInBrowser($this->rina['email'], $this->rina['password']);
        $this->browse($session);
        $token = $this->get('/account')->assertOk()->getCookie('XSRF-TOKEN')?->getValue();

        $this->browse($session);
        $out = $this->post('/logout', [], ['X-Inertia' => 'true']);

        $out->assertStatus(409)->assertHeader('X-Inertia-Location', route('login'));
        $this->assertNotSame($token, $out->getCookie('XSRF-TOKEN')?->getValue());
        $this->assertSame(0, $this->observer()->table('sessions')->where('id', $session)->count());

        $entry = (array) $this->observer()->table('security_log_entries')->orderByDesc('id')->first();
        $this->assertSame(['SESSION_REVOKED', 'USER', $this->rina['id'], $this->rina['id'], 'SUCCEEDED', 'logout'], [
            $entry['event_type'], $entry['actor_kind'], $entry['actor_user_id'], $entry['subject_user_id'], $entry['outcome_code'], $entry['route_name'],
        ]);

        // Without Inertia, a redirect to the sign-in page; the old cookie signs nobody in.
        $again = $this->signInBrowser($this->rina['email'], $this->rina['password']);
        $this->browse($again);
        $this->post('/logout')->assertRedirect(route('login'));
        $this->browse($again);
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_a_visitor_without_a_sign_in_session_is_answered_unauthenticated(): void
    {
        $this->browse(null);
        $this->get('/account')->assertRedirect('/login');
        $this->get('/account', ['X-Inertia' => 'true'])->assertStatus(409)->assertHeader('X-Inertia-Location', route('login'));
        $this->getJson('/user/confirmed-password-status')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->postJson('/session/activity')->assertStatus(401);
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_a_sign_in_session_without_its_times_is_never_trusted(): void
    {
        $this->browse(null);
        $this->actingAs(User::query()->findOrFail($this->rina['id']));

        $this->get('/account')->assertRedirect('/login');
    }

    public function test_signing_out_and_a_session_ending_change_nothing_but_the_sign_in_session_and_the_security_log(): void
    {
        $tables = $this->retainedTables();
        $before = $this->counts($tables);

        $first = $this->signInBrowser($this->rina['email'], $this->rina['password']);
        $this->browse($first);
        $this->post('/logout');
        $second = $this->signInBrowser($this->rina['email'], $this->rina['password']);
        $this->travel(31)->minutes();
        $this->browse($second);
        $this->get('/account');

        $this->assertSame($before, $this->counts($tables));
        $this->assertSame(0, $this->observer()->table('sessions')->whereNotNull('user_id')->count());
    }

    private function assertSessionEnded(string $session): void
    {
        $this->assertSame(0, $this->observer()->table('sessions')->where('id', $session)->whereNotNull('user_id')->count());

        $this->browse($session);
        $this->get('/account')->assertRedirect('/login');
    }

    /**
     * Every table of the schema but the sign-in sessions, the security log and
     * the rate limiter's counters.
     *
     * @return list<string>
     */
    private function retainedTables(): array
    {
        $tables = [];

        foreach ($this->observer()->select("select tablename from pg_catalog.pg_tables where schemaname = 'public' and tablename not in ('sessions', 'security_log_entries', 'cache', 'cache_locks', 'migrations') order by tablename") as $row) {
            $name = ((array) $row)['tablename'];
            $this->assertIsString($name);
            $tables[] = $name;
        }

        return $tables;
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, list<array<string, mixed>>>
     */
    private function counts(array $tables): array
    {
        $rows = [];

        foreach ($tables as $table) {
            $rows[$table] = $this->snapshot($table, $table === 'password_reset_tokens' ? 'email' : 'id');
        }

        return $rows;
    }
}
