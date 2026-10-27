<?php

namespace Tests\Feature\Access;

use App\Domain\Access\Accounts;
use App\Domain\Access\Role;
use App\Models\User;
use Illuminate\Hashing\Argon2IdHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Harness\Access\HashingProbe;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Http\Browsers;

/**
 * RA-01, sign-in, and RP-001 (AC-006-02; ROUTE_CONTRACTS sections 2 and 4;
 * INTERACTION_CONTRACTS IC-09; SECURITY_MODEL SEC-AUTH-01 to SEC-AUTH-10, section
 * 11; suites SIGNIN01, SEC01, THR-01). An ACTIVE account with a usable password
 * signs in by email, without regard to letter case, with a regenerated session
 * and a full page load to RN-01; every failure is one uniform answer; there is
 * no "remember me"; the sign-in bucket throttles with THROTTLED and Retry-After;
 * and each outcome reaches the security log.
 */
final class SignInTest extends DatabaseTestCase
{
    use Browsers;

    private const string FAILURE = 'Periksa email dan kata sandi Anda, lalu coba lagi.';

    protected function setUp(): void
    {
        parent::setUp();

        // The sign-in page renders the root view, whose assets the PHP tests
        // never read (Tests\TestCase).
        $this->withoutVite();
    }

    public function test_an_active_account_signs_in_with_a_regenerated_session_and_a_full_page_load_to_the_home(): void
    {
        $rina = TokoContoh::person('Rina');
        $this->useDatabaseSessions();

        $this->browse(null);
        $page = $this->get('/login')->assertOk();
        $guestSession = $this->sessionOf($page);

        $this->browse($guestSession);
        $response = $this->post('/login', ['email' => $rina['email'], 'password' => $rina['password']]);

        $response->assertRedirect('/');
        $session = $this->sessionOf($response);
        $this->assertNotSame($guestSession, $session, 'The session identifier is regenerated at sign-in (SEC-AUTH-10).');
        $this->assertSame(0, $this->observer()->table('sessions')->where('id', $guestSession)->count());
        $this->assertSame($rina['id'], $this->observer()->table('sessions')->where('id', $session)->value('user_id'));

        $entry = (array) $this->observer()->table('security_log_entries')->sole();
        $this->assertSame('SIGN_IN_SUCCEEDED', $entry['event_type']);
        $this->assertSame('USER', $entry['actor_kind']);
        $this->assertSame($rina['id'], $entry['actor_user_id']);
        $this->assertSame($rina['id'], $entry['subject_user_id']);
        $this->assertSame('SUCCEEDED', $entry['outcome_code']);
        $this->assertSame('login.store', $entry['route_name']);
        $this->assertSame('127.0.0.1', $entry['client_address']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $entry['request_id']);

        // An Inertia visit gets Inertia's location answer, a full page load.
        $this->browse(null);
        $this->post('/login', ['email' => $rina['email'], 'password' => $rina['password']], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', '/');
    }

    public function test_the_email_matches_without_regard_to_letter_case_and_outer_spaces(): void
    {
        $dewi = TokoContoh::person('Dewi');

        $this->post('/login', ['email' => '  DEWI@Contoh.TEST ', 'password' => $dewi['password']])->assertRedirect('/');
        $this->assertAuthenticatedAs(User::query()->findOrFail($dewi['id']));
    }

    public function test_every_failure_gives_the_one_uniform_answer_and_is_logged(): void
    {
        $rina = TokoContoh::person('Rina');
        $agus = TokoContoh::person('Agus');
        $dewi = TokoContoh::person('Dewi');
        $at = '2026-10-17 09:00:00+00';

        DB::table('users')->where('id', $agus['id'])->update(['status' => 'DEACTIVATED', 'deactivated_at' => $at]);
        DB::table('users')->where('id', $dewi['id'])->update(['status' => 'ANONYMIZED', 'deactivated_at' => $at, 'anonymized_at' => $at, 'password' => null]);
        $hendra = DB::transaction(fn (): User => app(Accounts::class)->create('Hendra', 'hendra@contoh.test', [Role::SuperAdmin]));

        $attempts = [
            'wrong password' => ['email' => $rina['email'], 'password' => 'Salah#'.$rina['password']],
            'unknown email' => ['email' => 'nobody@contoh.test', 'password' => $rina['password']],
            'deactivated account' => ['email' => $agus['email'], 'password' => $agus['password']],
            'anonymized account' => ['email' => $dewi['email'], 'password' => $dewi['password']],
            'account without a password' => ['email' => $hendra->email, 'password' => $rina['password']],
            'missing password' => ['email' => $rina['email']],
            'missing email' => ['password' => $rina['password']],
            'remember me' => ['email' => $rina['email'], 'password' => $rina['password'], 'remember' => '1'],
            'an email that is not text' => ['email' => ['x'], 'password' => $rina['password']],
            'an overlong password' => ['email' => $rina['email'], 'password' => str_repeat('a', 129)],
        ];

        $answers = [];

        foreach ($attempts as $case => $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.(count($answers) + 1)]);
            $response = $this->from('/login')->post('/login', $attempt);

            $response->assertRedirect('/login');
            $this->assertGuest();
            $this->assertSame(['form' => [self::FAILURE]], $this->flashedErrors(), $case);

            foreach ($response->headers->getCookies() as $cookie) {
                $this->assertStringStartsNotWith('remember_', $cookie->getName(), $case);
            }

            $answers[] = $response->getStatusCode();
        }

        $this->assertSame([302], array_values(array_unique($answers)));

        $entries = $this->observer()->table('security_log_entries')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
        $this->assertCount(count($attempts), $entries);

        foreach ($entries as $entry) {
            $this->assertSame(['SIGN_IN_FAILED', 'ANONYMOUS', null, 'REFUSED'], [$entry['event_type'], $entry['actor_kind'], $entry['actor_user_id'], $entry['outcome_code']]);
        }

        // The account an email matched is the subject; the typed email is never stored.
        $this->assertSame(
            [$rina['id'], null, $agus['id'], $dewi['id'], $hendra->id, null, null, null, null, null],
            array_column($entries, 'subject_user_id'),
        );
        $this->assertStringNotContainsString('nobody@contoh.test', json_encode($entries, JSON_THROW_ON_ERROR));
        $this->assertNull(session()->getOldInput('password'));
    }

    /**
     * The Owner's change request on pull request #9 (evidence section 13): a
     * refused sign-in does the password work of a wrong password — one check
     * of a hash at a stored password's cost — and makes no hash, in every
     * request, as PHP-FPM serves each one with fresh static properties.
     */
    public function test_every_refused_sign_in_checks_one_hash_at_a_stored_passwords_cost_and_makes_none(): void
    {
        $rina = TokoContoh::person('Rina');
        $agus = TokoContoh::person('Agus');
        $dewi = TokoContoh::person('Dewi');
        $at = '2026-10-17 09:00:00+00';

        DB::table('users')->where('id', $agus['id'])->update(['status' => 'DEACTIVATED', 'deactivated_at' => $at]);
        DB::table('users')->where('id', $dewi['id'])->update(['status' => 'ANONYMIZED', 'deactivated_at' => $at, 'anonymized_at' => $at, 'password' => null]);
        $hendra = DB::transaction(fn (): User => app(Accounts::class)->create('Hendra', 'hendra@contoh.test', [Role::SuperAdmin]));
        $stored = DB::table('users')->where('id', $rina['id'])->value('password');
        $this->assertIsString($stored);

        $probe = HashingProbe::install($this->app);
        $attempts = [
            'wrong password' => [$rina['email'], 'Salah#'.$rina['password'], $stored],
            'unknown email' => ['nobody@contoh.test', $rina['password'], Accounts::DECOY_HASH],
            'deactivated account' => [$agus['email'], $agus['password'], Accounts::DECOY_HASH],
            'anonymized account' => [$dewi['email'], $dewi['password'], Accounts::DECOY_HASH],
            'account without a password' => [$hendra->email, $rina['password'], Accounts::DECOY_HASH],
        ];
        $address = 0;

        foreach ($attempts as $case => [$email, $password, $checked]) {
            $probe->newRequest();
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.(++$address)]);

            $this->from('/login')->post('/login', ['email' => $email, 'password' => $password])->assertRedirect('/login');
            $this->assertGuest();
            $this->assertSame(['form' => [self::FAILURE]], $this->flashedErrors(), $case);
            $this->assertSame(0, $probe->made, $case.': no hash is made during the request');
            $this->assertSame([$checked], $probe->checked, $case.': exactly one hash is checked');
            $this->assertSame(password_get_info($stored), password_get_info($checked), $case.': at the cost of a stored password');
        }
    }

    public function test_the_sign_in_bucket_throttles_an_email_and_address_after_five_attempts(): void
    {
        $rina = TokoContoh::person('Rina');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', ['email' => $rina['email'], 'password' => 'Salah#1'])->assertRedirect();
        }

        $throttled = $this->post('/login', ['email' => $rina['email'], 'password' => $rina['password']]);

        $throttled->assertStatus(429);
        $throttled->assertHeader('Retry-After');
        $this->assertGreaterThan(0, (int) $throttled->headers->get('Retry-After'));
        $throttled->assertExactJson(['outcome' => 'REJECTED', 'code' => 'THROTTLED', 'messageKey' => 'ux.state.throttled', 'params' => [], 'fields' => [], 'lines' => []]);
        $this->assertGuest();

        $last = (array) $this->observer()->table('security_log_entries')->orderByDesc('id')->first();
        $this->assertSame(['THROTTLED', 'ANONYMOUS', 'REFUSED', 'login.store'], [$last['event_type'], $last['actor_kind'], $last['outcome_code'], $last['route_name']]);

        // The same email from another address, and another email from this
        // one, are not throttled by this bucket.
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50']);
        $this->post('/login', ['email' => $rina['email'], 'password' => $rina['password']])->assertRedirect('/');
    }

    public function test_the_sign_in_bucket_throttles_an_address_after_ten_and_the_installation_after_a_hundred(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->post('/login', ['email' => 'person'.$attempt.'@contoh.test'])->assertRedirect();
        }

        $this->post('/login', ['email' => 'person11@contoh.test'])->assertStatus(429)->assertHeader('Retry-After');

        // The throttled eleventh attempt ran nothing and counts in no bucket:
        // ten attempts so far, and ninety more from other addresses make 100.
        for ($attempt = 12; $attempt <= 101; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.$attempt]);
            $this->post('/login', ['email' => 'person'.$attempt.'@contoh.test'])->assertRedirect();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7']);
        $this->post('/login', ['email' => 'person102@contoh.test'])->assertStatus(429);

        $this->travel(61)->seconds();
        $this->post('/login', ['email' => 'person103@contoh.test'])->assertRedirect();
    }

    public function test_passwords_are_hashed_with_argon2id_and_a_weaker_hash_is_replaced_at_sign_in(): void
    {
        $rina = TokoContoh::person('Rina');
        $stored = DB::table('users')->where('id', $rina['id'])->value('password');
        $this->assertIsString($stored);
        $this->assertStringStartsWith('$argon2id$v=19$m=65536,t=4,p=1$', $stored);

        $weak = (new Argon2IdHasher)->make($rina['password'], ['memory' => 1024, 'time' => 2, 'threads' => 1]);
        DB::table('users')->where('id', $rina['id'])->update(['password' => $weak]);

        $this->post('/login', ['email' => $rina['email'], 'password' => $rina['password']])->assertRedirect('/');

        $rehashed = DB::table('users')->where('id', $rina['id'])->value('password');
        $this->assertIsString($rehashed);
        $this->assertStringStartsWith('$argon2id$v=19$m=65536,t=4,p=1$', $rehashed);
        $this->assertTrue(Hash::check($rina['password'], $rehashed));
    }

    public function test_a_rehash_never_replaces_a_password_changed_meanwhile(): void
    {
        $rina = TokoContoh::person('Rina');
        $weak = (new Argon2IdHasher)->make($rina['password'], ['memory' => 1024, 'time' => 2, 'threads' => 1]);
        DB::table('users')->where('id', $rina['id'])->update(['password' => $weak]);
        $read = User::query()->findOrFail($rina['id']);

        $changed = Hash::make('Baru#'.$rina['password']);
        DB::table('users')->where('id', $rina['id'])->update(['password' => $changed]);

        app(Accounts::class)->rehashIfNeeded($read, $rina['password']);

        $this->assertSame($changed, DB::table('users')->where('id', $rina['id'])->value('password'));
    }

    public function test_the_sign_in_page_shows_no_business_data_and_clears_the_history_key(): void
    {
        $response = $this->get('/login')->assertOk();
        $page = $response->viewData('page');

        $this->assertIsArray($page);
        $this->assertSame('access/SignIn', $page['component']);
        $this->assertIsArray($page['props']);
        $this->assertSame(['auth', 'errors', 'notice'], self::propNames($page['props']));
        $this->assertNull($page['props']['auth'], 'Nobody is signed in: the shared props share nobody (SEC-CH-02; TASK-007).');
        $this->assertNull($page['props']['notice']);
        $this->assertTrue($page['clearHistory']);
    }

    public function test_a_signed_in_visitor_of_the_sign_in_page_goes_to_the_home(): void
    {
        $rina = TokoContoh::person('Rina');
        $this->post('/login', ['email' => $rina['email'], 'password' => $rina['password']])->assertRedirect('/');

        $this->get('/login')->assertRedirect('/');
        $this->post('/login', ['email' => $rina['email'], 'password' => $rina['password']])->assertRedirect('/');
    }
}
