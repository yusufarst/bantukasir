<?php

namespace Tests\Feature\Access;

use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Http\Browsers;

/**
 * The shared props (AC-007-06; SECURITY_MODEL SEC-CH-02, SEC-SESS-02,
 * SEC-AZ-05; REQ-ACC-04; suite AUTH02; THR-08, THR-18; TASK-007 scope item 8):
 * exactly the signed-in user's identity, display name, roles and capability
 * flags, and the limits of the sign-in session that the shell's timers mirror
 * — the idle limit, the seconds left until it and until the absolute end, and
 * that end's instant — from the sign-in session only, for each role and the
 * combined account; nobody's before sign-in; in every Inertia answer,
 * partial reloads included; and a background request leaves the idle time as
 * it was. The payload inspection compares every field with the allowlists.
 */
final class SharedPropsTest extends DatabaseTestCase
{
    use Browsers;
    use ChannelInspector;

    private const array PEOPLE = [
        'Hendra' => ['owner', ['SUPER_ADMIN']],
        'Agus' => ['ops', ['OPERATIONS_ADMIN']],
        'Rina' => ['cashier', ['CASHIER']],
        'Dewi' => ['combined', ['CASHIER', 'OPERATIONS_ADMIN']],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo('2026-10-17T02:00:00Z');
        $this->useDatabaseSessions();
    }

    public function test_each_role_and_the_combined_account_share_exactly_their_own_identity_flags_and_limits(): void
    {
        /** @var array<string, array{id: int, email: string, password: string}> $accounts */
        $accounts = [];

        foreach (self::PEOPLE as $person => [$actor, $roles]) {
            $accounts[$person] = TokoContoh::person($person);
        }

        foreach (self::PEOPLE as $person => [$actor, $roles]) {
            $account = $accounts[$person];
            $others = array_values(array_map(static fn (array $other): string => $other['email'], array_diff_key($accounts, [$person => true])));
            $passwords = array_values(array_map(static fn (array $other): string => $other['password'], $accounts));

            $this->browse($this->signInBrowser($account['email'], $account['password']));
            $this->travel(10)->minutes();
            $response = $this->get('/account')->assertOk();

            $auth = ((array) ($this->inertiaPage($response)['props'] ?? []))['auth'] ?? null;
            $this->assertSame([
                'user' => [
                    'id' => $account['id'],
                    'displayName' => $person,
                    'roles' => $roles,
                    'capabilities' => self::flags($actor),
                ],
                'session' => [
                    'idleLimit' => 1800,
                    'idleEndsIn' => 1800,
                    'absoluteEndsAt' => '2026-10-17T14:00:00Z',
                    'absoluteEndsIn' => 12 * 3600 - 600,
                ],
            ], $auth);

            // Every field against the allowlists; no other person's email and
            // no password anywhere in the answer.
            $this->assertPageChannel($response, $actor, [...$others, ...$passwords]);
            $this->travelTo('2026-10-17T02:00:00Z');
        }
    }

    public function test_a_background_request_leaves_the_idle_time_and_every_inertia_answer_carries_the_props(): void
    {
        $rina = TokoContoh::person('Rina');
        $this->browse($this->signInBrowser($rina['email'], $rina['password']));
        $version = $this->inertiaPage($this->get('/account')->assertOk())['version'] ?? null;

        $this->travel(5)->minutes();
        $inertia = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => is_string($version) ? $version : ''];
        $background = $this->withHeaders([...$inertia, 'X-Background-Request' => '1', 'X-Inertia-Partial-Component' => 'access/Account', 'X-Inertia-Partial-Data' => 'passwordChanged'])->get('/account');
        $background->assertOk();
        $this->assertSame(1500, $background->json('props.auth.session.idleEndsIn'), 'Not touched: 25 minutes left.');
        $this->assertSame(['auth', 'errors', 'passwordChanged', 'recoveryEpoch'], self::propNames((array) $background->json('props')));

        $this->flushHeaders();
        $visit = $this->withHeaders($inertia)->get('/account');
        $this->assertSame(1800, $visit->json('props.auth.session.idleEndsIn'), 'A user-initiated visit counts from now.');
        $this->assertSame(12 * 3600 - 300, $visit->json('props.auth.session.absoluteEndsIn'));
    }

    public function test_a_signed_in_page_carries_the_current_recovery_epoch_and_a_visitor_none(): void
    {
        $rina = TokoContoh::person('Rina');
        $this->browse($this->signInBrowser($rina['email'], $rina['password']));

        // Before the bootstrap there is no epoch yet: the prop is null.
        $props = (array) ($this->inertiaPage($this->get('/account')->assertOk())['props'] ?? []);
        $this->assertArrayHasKey('recoveryEpoch', $props);
        $this->assertNull($props['recoveryEpoch']);

        // Once issued, the page carries it as lowercase hexadecimal, unchanged
        // (TASK-008 finding F-1).
        $epoch = TokoContoh::recoveryEpoch();
        $props = (array) ($this->inertiaPage($this->get('/account')->assertOk())['props'] ?? []);
        $this->assertSame($epoch, $props['recoveryEpoch']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $epoch);

        // A visitor gets none, also after a signed-in request of the same
        // process, whose shared value must not stay behind.
        $this->browse(null);
        $guest = (array) ($this->inertiaPage($this->get('/login')->assertOk())['props'] ?? []);
        $this->assertArrayHasKey('recoveryEpoch', $guest);
        $this->assertNull($guest['recoveryEpoch']);
    }

    public function test_nobody_is_shared_before_sign_in(): void
    {
        $this->browse(null);

        foreach (['/login', '/set-password'] as $path) {
            $response = $this->get($path)->assertOk();
            $props = (array) ($this->inertiaPage($response)['props'] ?? []);
            $this->assertArrayHasKey('auth', $props);
            $this->assertNull($props['auth']);
            $this->assertPageChannel($response, 'guest');
        }
    }

    /**
     * The flags of each kind of account (SECURITY_MODEL section 6, the cells
     * that open a gate), as written out here.
     *
     * @return list<string>
     */
    private static function flags(string $actor): array
    {
        return match ($actor) {
            'owner' => array_values(array_filter(array_map(static fn (int $number): string => sprintf('CAP-%02d', $number), range(1, 34)), static fn (string $capability): bool => $capability !== 'CAP-33')),
            'ops' => ['CAP-01', 'CAP-02', 'CAP-03', 'CAP-04', 'CAP-05', 'CAP-06', 'CAP-08', 'CAP-09', 'CAP-10', 'CAP-11', 'CAP-13', 'CAP-14', 'CAP-34'],
            'cashier' => ['CAP-01', 'CAP-03', 'CAP-21', 'CAP-34'],
            default => ['CAP-01', 'CAP-02', 'CAP-03', 'CAP-04', 'CAP-05', 'CAP-06', 'CAP-08', 'CAP-09', 'CAP-10', 'CAP-11', 'CAP-13', 'CAP-14', 'CAP-21', 'CAP-34'],
        };
    }
}
