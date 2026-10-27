<?php

namespace Tests\Feature\Access;

use App\Models\User;
use App\Policies\ObjectScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The object-scope pattern (AC-007-02; SECURITY_MODEL SEC-AZ-03, SEC-AZ-06;
 * ROUTE_CONTRACTS RT-05; TECHNICAL_RULES TR-ERR-03; THR-02; suite AUTH01;
 * TASK-007 scope item 1). No route of V1 that reads an object by its
 * identifier exists yet, so a test-only route reads an account through the
 * pattern with the scope "own account": an account outside the actor's scope,
 * one that does not exist and an identifier that cannot be one all get the
 * same answer, NOT_FOUND, byte for byte, as JSON and as a page; the account in
 * scope is found. Each later Task's own Queries use the pattern with their
 * scopes, and their tests guess identifiers in the same way.
 */
final class ObjectScopeTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:CAP-01'])
            ->get('/harness/accounts/{account}', static function (Request $request, string $account): string {
                $actor = $request->user();
                $found = ObjectScope::find(User::query()->whereKey($actor instanceof User ? $actor->id : 0), $account);

                $key = $found->getKey();

                return is_int($key) ? (string) $key : '';
            });
    }

    public function test_an_object_outside_the_scope_is_not_found_exactly_like_a_missing_one(): void
    {
        $rina = TokoContoh::person('Rina');
        $agus = TokoContoh::person('Agus');
        $last = $this->runtime()->table('users')->max('id');
        $this->assertIsInt($last);
        $missing = $last + 1000;

        $this->post('/login', ['email' => $rina['email'], 'password' => $rina['password']])->assertRedirect('/');

        $this->getJson('/harness/accounts/'.$rina['id'])->assertOk()->assertSeeText((string) $rina['id']);

        $answers = [];

        foreach ([(string) $agus['id'], (string) $missing, '0', '-1', 'abc', '1e3', '01', '9223372036854775808', $rina['id'].'.0'] as $guess) {
            $answers[$guess] = $this->getJson('/harness/accounts/'.$guess);
            $answers[$guess]->assertStatus(404)->assertExactJson(['outcome' => 'REJECTED', 'code' => 'NOT_FOUND', 'messageKey' => 'ux.state.not_found', 'params' => [], 'fields' => [], 'lines' => []]);
        }

        $bodies = array_unique(array_map(static fn ($answer): string => (string) $answer->getContent(), $answers));
        $this->assertCount(1, $bodies, 'One answer for every guess (TR-ERR-03).');

        // As a page: UXS-08, the same for both.
        $outside = $this->get('/harness/accounts/'.$agus['id'])->assertStatus(404)->viewData('page');
        $absent = $this->get('/harness/accounts/'.$missing)->assertStatus(404)->viewData('page');
        $this->assertIsArray($outside);
        $this->assertIsArray($absent);
        $this->assertSame('shell/Error', $outside['component']);
        $this->assertSame(((array) $outside['props'])['state'] ?? null, ((array) $absent['props'])['state'] ?? null);
        $this->assertSame('not_found', ((array) $outside['props'])['state'] ?? null);
    }

    public function test_an_identifier_becomes_a_key_only_when_it_is_a_positive_whole_number_of_the_range(): void
    {
        $this->assertSame(42, ObjectScope::key('42'));
        $this->assertSame(42, ObjectScope::key(42));
        $this->assertSame(9223372036854775807, ObjectScope::key('9223372036854775807'));

        foreach (['0', '-1', '+1', ' 1', '1 ', '01', '1.0', '1e3', '0x1', '', '9223372036854775808', '99999999999999999999', "1\n"] as $text) {
            $this->assertNull(ObjectScope::key($text), var_export($text, true));
        }

        foreach ([0, -5, 1.0, null, true, ['1']] as $value) {
            $this->assertNull(ObjectScope::key($value));
        }
    }
}
