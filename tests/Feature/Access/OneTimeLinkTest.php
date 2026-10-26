<?php

namespace Tests\Feature\Access;

use App\Actions\Access\ConsumeLink;
use App\Database\CommandPoints;
use App\Domain\Access\Accounts;
use App\Domain\Access\OneTimeLinks;
use App\Domain\Access\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Http\Browsers;
use Tests\Harness\Race\FailurePoints;
use Tests\Harness\Race\InjectedFailure;

/**
 * The one-time link broker and RA-06 (AC-006-06; ADR-004 item 4; SECURITY_MODEL
 * SEC-ACC-04 to SEC-ACC-06, section 11; ROUTE_CONTRACTS RP-002, RA-06; suite
 * LINK01; THR-01, THR-21). A link is a hashed token, one per account, that a
 * new one replaces; it works once within 60 minutes, sets a password that
 * meets AICWDF-COMPAT-8, revokes every sign-in session of the account and
 * writes LINK_CONSUME. Used, expired, replaced, malformed, foreign and
 * not-ACTIVE links are refused with the one uniform LINK_INVALID, and leave
 * everything as it was; a failure is in the security log. The token is never
 * in a prop, a URL, a log, the audit or the security log, and the link page
 * sends no referrer.
 */
final class OneTimeLinkTest extends DatabaseTestCase
{
    use Browsers;

    private const string INVALID = 'Tautan ini tidak dapat dipakai. Minta tautan baru kepada Pemilik.';

    /** @var list<string> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        Log::listen(function (MessageLogged $message): void {
            $this->logged[] = $message->message.' '.json_encode($message->context);
        });
    }

    public function test_a_link_is_stored_hashed_one_per_account_and_a_new_one_replaces_it(): void
    {
        $rina = TokoContoh::person('Rina');

        $first = TokoContoh::link('Rina');
        $second = TokoContoh::link('Rina');

        $rows = $this->observer()->table('password_reset_tokens')->get();
        $this->assertCount(1, $rows);
        $row = (array) $rows->sole();
        $this->assertSame($rina['email'], $row['email']);
        $this->assertIsString($row['token']);
        $this->assertStringStartsWith('$argon2id$', $row['token']);
        $this->assertNotSame($second['token'], $row['token']);
        $this->assertTrue(Hash::check($second['token'], $row['token']));
        $this->assertSame(64, strlen($second['token']));

        $this->consume($first)->assertRedirect(route('password.link'));
        $this->assertSame(['form' => [self::INVALID]], $this->flashedErrors());
        $this->consume($second)->assertRedirect(route('password.link'));
        $this->assertSame([], $this->flashedErrorsOrNone());
    }

    public function test_the_token_and_the_password_are_never_trimmed(): void
    {
        $rina = TokoContoh::person('Rina');
        $link = TokoContoh::link('Rina');
        $password = ' '.TokoContoh::newPassword().' ';

        // A token padded with a space is another token: refused, the link kept.
        $this->consume(['user' => $link['user'], 'token' => $link['token'].' '])->assertRedirect(route('password.link'));
        $this->assertSame(['form' => [self::INVALID]], $this->flashedErrors());
        $this->assertSame(1, $this->observer()->table('password_reset_tokens')->count());

        // The password is kept exactly as typed, its outer spaces included.
        $this->consume($link, $password)->assertRedirect(route('password.link'));
        $this->assertSame([], $this->flashedErrorsOrNone());
        $this->browse(null);
        $this->post('/login', ['email' => $rina['email'], 'password' => trim($password)])->assertRedirect('/login');
        $this->signInBrowser($rina['email'], $password);
    }

    public function test_a_link_works_once_sets_the_password_revokes_every_session_and_is_audited(): void
    {
        $rina = TokoContoh::person('Rina');
        $this->useDatabaseSessions();
        $signedIn = $this->signInBrowser($rina['email'], $rina['password']);
        $link = TokoContoh::link('Rina');
        $password = TokoContoh::newPassword();

        $this->browse(null);
        $response = $this->consume($link, $password);

        $response->assertRedirect(route('password.link'))->assertHeader('Referrer-Policy', 'no-referrer');
        $this->browse($this->sessionOf($response));
        $page = $this->get('/set-password')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->viewData('page');
        $this->assertIsArray($page);
        $this->assertSame(['passwordSet' => true], array_intersect_key((array) $page['props'], ['passwordSet' => true]));

        $this->assertSame(0, $this->observer()->table('password_reset_tokens')->count());
        $this->assertSame(0, $this->observer()->table('sessions')->where('id', $signedIn)->count());
        $this->browse($signedIn);
        $this->get('/account')->assertRedirect('/login');

        $this->signInBrowser($rina['email'], $password);

        $events = $this->observer()->table('audit_events')->where('action', 'LINK_CONSUME')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
        $last = end($events);
        $this->assertIsArray($last);
        $this->assertSame(['SECURITY', 'USER', $rina['id'], 'Rina', 'user', $rina['id']], [$last['category'], $last['actor_kind'], $last['actor_user_id'], $last['actor_display_name'], $last['subject_type'], $last['subject_id']]);
        $this->assertSame(['kind' => 'RESET', 'sessionsRevoked' => 1], json_decode((string) $last['change_data'], true));
        $this->assertNull($last['command_id']);

        // The same link again: refused, and the new password stays.
        $this->browse(null);
        $this->consume($link, TokoContoh::newPassword())->assertRedirect(route('password.link'));
        $this->assertSame(['form' => [self::INVALID]], $this->flashedErrors());
        $this->signInBrowser($rina['email'], $password);
    }

    public function test_the_first_password_of_a_new_account_is_set_through_its_link(): void
    {
        $account = DB::transaction(fn (): User => app(Accounts::class)->create('Agus', 'agus@contoh.test', [Role::OperationsAdmin]));
        $this->assertNull($account->password);
        $token = DB::transaction(fn (): string => app(OneTimeLinks::class)->issue(app(Accounts::class)->lockForUpdate($account->id) ?? $account));

        $first = TokoContoh::newPassword();
        $this->consume(['user' => $account->id, 'token' => $token], $first)->assertRedirect(route('password.link'));

        $event = (array) $this->observer()->table('audit_events')->where('action', 'LINK_CONSUME')->sole();
        $this->assertSame(['kind' => 'FIRST_PASSWORD', 'sessionsRevoked' => 0], json_decode((string) $event['change_data'], true));
        $this->post('/login', ['email' => 'agus@contoh.test', 'password' => $first])->assertRedirect('/');
    }

    public function test_an_expired_link_is_refused_after_60_minutes(): void
    {
        TokoContoh::person('Rina');
        $this->travelTo('2026-10-17T02:00:00Z');
        $link = TokoContoh::link('Rina');

        $this->travel(60)->minutes();
        $this->travel(1)->seconds();
        $this->consume($link)->assertRedirect(route('password.link'));
        $this->assertSame(['form' => [self::INVALID]], $this->flashedErrors());

        $fresh = TokoContoh::link('Rina');
        $this->travel(59)->minutes();
        $this->consume($fresh)->assertRedirect(route('password.link'));
        $this->assertSame([], $this->flashedErrorsOrNone());
    }

    public function test_every_failure_is_the_uniform_link_invalid_and_leaves_everything_as_it_was(): void
    {
        $rina = TokoContoh::person('Rina');
        $agus = TokoContoh::person('Agus');
        $link = TokoContoh::link('Rina');
        $agusLink = TokoContoh::link('Agus');
        $before = DB::table('users')->where('id', $rina['id'])->value('password');

        $failures = [
            'another account' => ['user' => (string) $agus['id'], 'token' => $link['token']],
            'no such account' => ['user' => (string) ($agus['id'] + 100), 'token' => $link['token']],
            'a wrong token' => ['user' => (string) $rina['id'], 'token' => str_repeat('a', 64)],
            'a padded token' => ['user' => (string) $rina['id'], 'token' => ' '.$link['token']],
            'an overlong token' => ['user' => (string) $rina['id'], 'token' => str_repeat('a', 129)],
            'a malformed account' => ['user' => 'u1', 'token' => $link['token']],
            'a missing token' => ['user' => (string) $rina['id']],
            'a missing account' => ['token' => $link['token']],
            'an unknown field' => ['user' => (string) $rina['id'], 'token' => $link['token'], 'email' => 'rina@contoh.test'],
        ];

        foreach ($failures as $case => $fields) {
            $password = TokoContoh::newPassword();
            $this->post('/set-password', [...$fields, 'password' => $password, 'password_confirmation' => $password])->assertRedirect(route('password.link'));
            $this->assertSame(['form' => [self::INVALID]], $this->flashedErrors(), $case);
        }

        $this->assertSame($before, DB::table('users')->where('id', $rina['id'])->value('password'));
        $this->assertSame(2, $this->observer()->table('password_reset_tokens')->count());
        $this->assertSame(0, $this->observer()->table('audit_events')->where('action', 'LINK_CONSUME')->where('occurred_at', '>', now()->subMinute()->format('Y-m-d H:i:sP'))->whereNotIn('subject_id', [$rina['id'], $agus['id']])->count());

        $entries = $this->observer()->table('security_log_entries')->where('event_type', 'LINK_FAILED')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
        $this->assertCount(count($failures), $entries);
        $this->assertSame([$agus['id'], null, $rina['id'], $rina['id'], null, null, null, null, null], array_column($entries, 'subject_user_id'));

        foreach ($entries as $entry) {
            $this->assertSame(['ANONYMOUS', 'REFUSED', 'password.link.store'], [$entry['actor_kind'], $entry['outcome_code'], $entry['route_name']]);
        }

        // The links are still valid for their own accounts.
        $this->consume($agusLink)->assertRedirect(route('password.link'));
        $this->assertSame([], $this->flashedErrorsOrNone());
    }

    public function test_a_link_of_an_account_that_is_not_active_is_refused(): void
    {
        $agus = TokoContoh::person('Agus');
        $dewi = TokoContoh::person('Dewi');
        $agusLink = TokoContoh::link('Agus');
        $dewiLink = TokoContoh::link('Dewi');
        $at = '2026-10-17 09:00:00+00';

        DB::table('users')->where('id', $agus['id'])->update(['status' => 'DEACTIVATED', 'deactivated_at' => $at]);
        DB::table('users')->where('id', $dewi['id'])->update(['status' => 'ANONYMIZED', 'deactivated_at' => $at, 'anonymized_at' => $at, 'password' => null]);

        foreach ([$agusLink, $dewiLink] as $link) {
            $this->consume($link)->assertRedirect(route('password.link'));
            $this->assertSame(['form' => [self::INVALID]], $this->flashedErrors());
        }

        $this->assertNull(DB::table('users')->where('id', $dewi['id'])->value('password'));
    }

    public function test_no_field_of_a_link_is_ever_flashed_or_kept_in_the_session(): void
    {
        TokoContoh::person('Rina');
        $link = TokoContoh::link('Rina');
        $weak = 'Pnd#1a';

        // A field error, a malformed link and an unknown field: none flashes the
        // account, the token or the passwords (SEC-CH-03, SEC-ACC-05; review
        // finding R-01).
        $attempts = [
            'field error' => ['user' => (string) $link['user'], 'token' => $link['token'], 'password' => $weak, 'password_confirmation' => $weak],
            'malformed link' => ['user' => 'x', 'token' => $link['token'], 'password' => $weak, 'password_confirmation' => $weak],
            'unknown field' => ['user' => (string) $link['user'], 'token' => $link['token'], 'password' => $weak, 'password_confirmation' => $weak, 'role' => 'x'],
        ];

        foreach ($attempts as $case => $fields) {
            $this->browse(null);
            $this->post('/set-password', $fields)->assertRedirect(route('password.link'));
            $this->assertSame([], session()->getOldInput(), $case);
            $this->assertStringNotContainsString($link['token'], (string) json_encode(session()->all()), $case);
        }

        $this->assertSame(1, $this->observer()->table('password_reset_tokens')->count());

        // Any other form's field named token is never flashed either.
        Route::middleware('web')->post('/testing/flash-probe', static function (Request $request): string {
            $request->validate(['token' => ['required'], 'other' => ['required']], ['required' => __('ux.validation.required')]);

            return 'unreachable';
        });
        $this->browse(null);
        $this->post('/testing/flash-probe', ['token' => $link['token'], 'kept' => 'k'])->assertRedirect();
        $this->assertSame(['kept' => 'k'], session()->getOldInput());
    }

    public function test_a_change_of_ones_own_password_leaves_an_outstanding_link_valid(): void
    {
        // SEC-ACC-04 lists what revokes a link; a change of one's own password
        // is not among them, and the link is changed only under the account's
        // lock (review finding R-02).
        $rina = TokoContoh::person('Rina');
        $link = TokoContoh::link('Rina');
        $changed = TokoContoh::newPassword();

        $this->signInBrowser($rina['email'], $rina['password']);
        $this->from('/account/password')->put('/user/password', ['current_password' => $rina['password'], 'password' => $changed, 'password_confirmation' => $changed])->assertRedirect(route('account.show'));
        $this->assertSame(1, $this->observer()->table('password_reset_tokens')->count());

        $this->browse(null);
        $this->consume($link)->assertRedirect(route('password.link'));
        $this->assertSame([], $this->flashedErrorsOrNone());
        $this->assertSame(0, $this->observer()->table('password_reset_tokens')->count());
    }

    public function test_the_new_password_meets_the_profile_and_a_refused_one_leaves_the_link_valid(): void
    {
        TokoContoh::person('Rina');
        $link = TokoContoh::link('Rina');

        $cases = [
            'Pnd#1a' => [],
            'tanpabesar#1' => [],
            'TANPAKECIL#1' => [],
            'TanpaAngka#' => [],
            'TanpaSimbol1' => [],
        ];

        foreach (array_keys($cases) as $password) {
            $this->post('/set-password', ['user' => (string) $link['user'], 'token' => $link['token'], 'password' => $password, 'password_confirmation' => $password]);
            $errors = $this->flashedErrors();
            $this->assertArrayHasKey('password', $errors, $password);
            $this->assertArrayNotHasKey('form', $errors, $password);
        }

        $this->post('/set-password', ['user' => (string) $link['user'], 'token' => $link['token'], 'password' => TokoContoh::newPassword(), 'password_confirmation' => TokoContoh::newPassword()]);
        $this->assertSame(['password_confirmation' => ['Kata sandi yang diulang tidak sama.']], $this->flashedErrors());

        $this->post('/set-password', ['user' => (string) $link['user'], 'token' => $link['token'], 'password' => str_repeat('Aa1#', 33), 'password_confirmation' => str_repeat('Aa1#', 33)]);
        $this->assertSame(['password' => ['Paling banyak 128 karakter.']], $this->flashedErrors());

        $this->assertSame(1, $this->observer()->table('password_reset_tokens')->count());
        $this->consume($link)->assertRedirect(route('password.link'));
        $this->assertSame([], $this->flashedErrorsOrNone());
    }

    public function test_a_failure_after_any_write_step_leaves_the_link_and_the_password_as_they_were(): void
    {
        $rina = TokoContoh::person('Rina');
        $link = TokoContoh::link('Rina');

        // Two sign-in sessions of the account, which the step "sessions
        // revoked" deletes and its rollback keeps (review finding R-05).
        $this->useDatabaseSessions();
        $sessions = [$this->signInBrowser($rina['email'], $rina['password']), $this->signInBrowser($rina['email'], $rina['password'])];
        $before = DB::table('users')->where('id', $rina['id'])->value('password');
        $events = $this->observer()->table('audit_events')->count();

        foreach ([1, 2, 3, 4] as $step) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));

            try {
                app(ConsumeLink::class)($rina['id'], $link['token'], TokoContoh::newPassword());
                $this->fail('No failure after write step '.$step);
            } catch (InjectedFailure) {
                $this->assertSame($before, $this->observer()->table('users')->where('id', $rina['id'])->value('password'));
                $this->assertSame(1, $this->observer()->table('password_reset_tokens')->count());
                $this->assertSame($events, $this->observer()->table('audit_events')->count());
                $this->assertEqualsCanonicalizing($sessions, $this->observer()->table('sessions')->where('user_id', $rina['id'])->pluck('id')->all());
            }
        }
    }

    public function test_the_link_page_carries_no_link_and_a_signed_in_visitor_leaves_with_an_empty_fragment(): void
    {
        $rina = TokoContoh::person('Rina');
        $link = TokoContoh::link('Rina');

        $page = $this->get('/set-password')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->viewData('page');
        $this->assertIsArray($page);
        $props = (array) $page['props'];
        $this->assertSame(['errors', 'passwordSet'], self::propNames($props));
        $this->assertFalse($props['passwordSet']);
        $this->assertTrue($page['clearHistory']);
        $this->assertStringNotContainsString($link['token'], (string) json_encode($page));

        $this->post('/login', ['email' => $rina['email'], 'password' => $rina['password']])->assertRedirect('/');
        // Sent on with no referrer either (review finding R-10).
        $this->get('/set-password')->assertRedirect(url('/').'/#')->assertHeader('Referrer-Policy', 'no-referrer');

        $this->assertSame('http://localhost/set-password#u='.$rina['id'].'&t='.$link['token'], str_replace(url('/'), 'http://localhost', OneTimeLinks::url(User::query()->findOrFail($rina['id']), $link['token'])));
    }

    public function test_the_link_bucket_throttles_ten_submissions_per_address(): void
    {
        TokoContoh::person('Rina');
        $link = TokoContoh::link('Rina');

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->post('/set-password', ['user' => (string) $link['user'], 'token' => 'x', 'password' => 'Aa1#aaaa', 'password_confirmation' => 'Aa1#aaaa'])->assertRedirect();
        }

        $this->consume($link)->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('code', 'THROTTLED');
        $this->assertSame(1, $this->observer()->table('password_reset_tokens')->count());
        $this->assertSame('THROTTLED', $this->observer()->table('security_log_entries')->orderByDesc('id')->value('event_type'));
    }

    public function test_the_link_bucket_throttles_the_installation_after_a_hundred_submissions(): void
    {
        TokoContoh::person('Rina');
        $link = TokoContoh::link('Rina');

        // Ten addresses with ten submissions each: no address reaches its own
        // bucket, and the installation's reaches 100 (SECURITY_MODEL section
        // 11; review finding R-03). A malformed link still counts.
        for ($address = 1; $address <= 10; $address++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.$address]);

            for ($attempt = 1; $attempt <= 10; $attempt++) {
                $this->post('/set-password', ['user' => 'x', 'token' => 'x'])->assertRedirect(route('password.link'));
            }
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.11']);
        $throttled = $this->observer()->table('security_log_entries')->where('event_type', 'THROTTLED')->count();
        $this->consume($link)->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('code', 'THROTTLED');
        $this->assertSame($throttled + 1, $this->observer()->table('security_log_entries')->where('event_type', 'THROTTLED')->count());
        $this->assertSame(1, $this->observer()->table('password_reset_tokens')->count());

        // A minute later the bucket admits the link again.
        $this->travel(61)->seconds();
        $this->consume($link)->assertRedirect(route('password.link'));
        $this->assertSame(0, $this->observer()->table('password_reset_tokens')->count());
    }

    public function test_the_token_never_reaches_a_log_the_audit_or_the_security_log(): void
    {
        TokoContoh::person('Rina');
        $link = TokoContoh::link('Rina');
        $this->consume(['user' => $link['user'], 'token' => str_repeat('b', 64)]);
        $this->consume($link);

        $everything = json_encode([
            $this->logged,
            $this->observer()->table('audit_events')->get(),
            $this->observer()->table('security_log_entries')->get(),
            $this->observer()->table('sessions')->get(),
        ], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($link['token'], $everything);
        $this->assertStringNotContainsString(str_repeat('b', 64), $everything);
        $this->assertNotSame([], $this->logged);
    }

    /**
     * @param  array{user: int, token: string}  $link
     * @return TestResponse<Response>
     */
    private function consume(array $link, ?string $password = null): TestResponse
    {
        $password ??= TokoContoh::newPassword();

        return $this->post('/set-password', ['user' => (string) $link['user'], 'token' => $link['token'], 'password' => $password, 'password_confirmation' => $password]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function flashedErrorsOrNone(): array
    {
        return session('errors') === null ? [] : $this->flashedErrors();
    }
}
