<?php

namespace Tests\Feature\Access;

use Illuminate\Support\Facades\Route;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * Recent authentication (AC-006-04; ROUTE_CONTRACTS RA-03, RA-04;
 * INTERACTION_CONTRACTS IC-11 and section 1.5; SECURITY_MODEL SEC-SESS-05,
 * section 11; suite AUTH03; THR-20). Until real operations exist, a route
 * that this test registers stands for one that needs it: without a
 * confirmation within 15 minutes it answers 423 and runs nothing; after
 * RA-03, the same frozen request succeeds. A failed confirmation is one
 * uniform answer, recorded in the security log, and the bucket `confirm`
 * throttles it per user.
 */
final class RecentAuthenticationTest extends DatabaseTestCase
{
    /** @var array{id: int, email: string, password: string} */
    private array $rina;

    private int $ran = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'signin.session', 'password.confirm'])
            ->post('/testing/recent-auth', function () {
                $this->ran++;

                return response()->json(['outcome' => 'COMMITTED'], 201);
            });

        $this->travelTo('2026-10-17T02:00:00Z');
        $this->rina = TokoContoh::person('Rina');
        $this->post('/login', ['email' => $this->rina['email'], 'password' => $this->rina['password']])->assertRedirect('/');
    }

    public function test_an_operation_answers_423_without_a_recent_confirmation_and_succeeds_after_it(): void
    {
        $frozen = ['operation' => 'harness.recent_auth', 'sourceIntentId' => '00000000-0000-4000-8000-000000000001'];

        $this->postJson('/testing/recent-auth', $frozen)->assertStatus(423);
        $this->assertSame(0, $this->ran);
        $this->getJson('/user/confirmed-password-status')->assertOk()->assertExactJson(['confirmed' => false]);

        $this->postJson('/user/confirm-password', ['password' => $this->rina['password']])->assertStatus(201);
        $this->getJson('/user/confirmed-password-status')->assertOk()->assertExactJson(['confirmed' => true]);
        $this->postJson('/testing/recent-auth', $frozen)->assertStatus(201)->assertExactJson(['outcome' => 'COMMITTED']);
        $this->assertSame(1, $this->ran);

        // 15 minutes count; then the operation asks again.
        $this->travel(900)->seconds();
        $this->postJson('/testing/recent-auth', $frozen)->assertStatus(201);
        $this->travel(1)->seconds();
        $this->postJson('/testing/recent-auth', $frozen)->assertStatus(423);
        $this->assertSame(2, $this->ran);
    }

    public function test_a_failed_confirmation_is_one_uniform_answer_and_is_logged(): void
    {
        $this->postJson('/user/confirm-password', ['password' => 'Salah#'.$this->rina['password']])
            ->assertStatus(422)
            ->assertJsonPath('errors.password', ['Periksa kata sandi Anda, lalu coba lagi.']);
        $this->postJson('/user/confirm-password', [])->assertStatus(422)->assertJsonPath('errors.password', ['Periksa kata sandi Anda, lalu coba lagi.']);

        $this->getJson('/user/confirmed-password-status')->assertExactJson(['confirmed' => false]);

        $entries = $this->observer()->table('security_log_entries')->where('event_type', 'PASSWORD_CONFIRMATION_FAILED')->get()->map(fn (object $row): array => (array) $row)->all();
        $this->assertCount(2, $entries);
        $this->assertSame([['USER', $this->rina['id'], $this->rina['id'], 'REFUSED', 'password.confirm.store']], array_values(array_unique(array_map(
            fn (array $entry): array => [$entry['actor_kind'], $entry['actor_user_id'], $entry['subject_user_id'], $entry['outcome_code'], $entry['route_name']],
            $entries,
        ), SORT_REGULAR)));
    }

    public function test_the_confirmation_takes_only_its_allowlisted_field(): void
    {
        // A password that is not a text, an unknown field beside the right
        // password and a password longer than 128 characters: each the one
        // uniform failure, logged, never a server error (TR-VAL-01; review
        // finding R-04).
        $cases = [
            'a list' => ['password' => [$this->rina['password']]],
            'an unknown field' => ['password' => $this->rina['password'], 'remember' => '1'],
            'too long' => ['password' => str_repeat('Aa1#', 33)],
        ];

        foreach ($cases as $case => $fields) {
            $this->postJson('/user/confirm-password', $fields)
                ->assertStatus(422)
                ->assertJsonPath('errors.password', ['Periksa kata sandi Anda, lalu coba lagi.']);
            $this->getJson('/user/confirmed-password-status')->assertExactJson(['confirmed' => false]);
        }

        $this->assertSame(3, $this->observer()->table('security_log_entries')->where('event_type', 'PASSWORD_CONFIRMATION_FAILED')->count());
        $this->postJson('/user/confirm-password', ['password' => $this->rina['password']])->assertStatus(201);
    }

    public function test_the_confirmation_bucket_throttles_five_attempts_per_user(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/user/confirm-password', ['password' => 'Salah#1'])->assertStatus(422);
        }

        $this->postJson('/user/confirm-password', ['password' => $this->rina['password']])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('code', 'THROTTLED');
        $this->getJson('/user/confirmed-password-status')->assertExactJson(['confirmed' => false]);

        $last = (array) $this->observer()->table('security_log_entries')->orderByDesc('id')->first();
        $this->assertSame(['THROTTLED', 'USER', $this->rina['id']], [$last['event_type'], $last['actor_kind'], $last['actor_user_id']]);
    }

    public function test_a_page_gets_the_failure_on_the_validation_channel(): void
    {
        $this->from('/account')->post('/user/confirm-password', ['password' => 'Salah#1'], ['X-Inertia' => 'true'])->assertRedirect('/account');
    }
}
