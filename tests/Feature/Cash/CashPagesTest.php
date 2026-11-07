<?php

namespace Tests\Feature\Cash;

use App\Domain\Commands\Operations;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The pages of TASK-027 and the homes they change (AC-027-02, AC-027-06; ROUTE_CONTRACTS RN-01,
 * RP-104, RP-602, RP-609 and RP-610; NAVIGATION_CONTRACTS NC-12; SECURITY_MODEL SEC-CH-01, SEC-AZ-06;
 * EXECUTION_CONTEXT section 10, a route, a read and a page; suites AUTH01, AUTH02, UX01 and
 * CONFIG01; TASK-027 section 7, L4): each page's props per role, exactly as allowlisted; RP-104's
 * available registers — active and without an OPEN session — and none (UXS-48); a user with an OPEN
 * session sent home; RN-01 of a cashier without an OPEN session to RP-104; a guessed register
 * NOT_FOUND; UXP-602's mark of an OPEN session; and no shared drawer, transfer of responsibility,
 * paid-in, paid-out or cash pickup among the routes and operations (BR-CASH-07).
 */
final class CashPagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
    }

    public function test_the_registers_and_their_form_carry_their_allowlisted_props_for_the_owner_alone(): void
    {
        $free = TokoContoh::register('LACI-1', 'Laci 1');
        $used = TokoContoh::register('LACI-2', 'Laci 2');
        TokoContoh::openSession('Rina', $used);
        $secrets = array_values(array_map(static fn (array $person): string => $person['password'], $this->people));
        $this->as('Hendra');

        $list = $this->get('/settings/cash-registers')->assertOk();
        $this->assertSame('cash/Registers', $this->inertiaPage($list)['component'] ?? null);
        $this->assertPageChannel($list, 'owner', $secrets);
        $this->assertSame([
            ['id' => $free, 'code' => 'LACI-1', 'label' => 'Laci 1', 'active' => true, 'version' => 1, 'openBy' => null],
            ['id' => $used, 'code' => 'LACI-2', 'label' => 'Laci 2', 'active' => true, 'version' => 1, 'openBy' => 'Rina'],
        ], $this->propsOf($list)['registers'] ?? null);

        $new = $this->get('/settings/cash-registers/new')->assertOk();
        $props = $this->propsOf($new);
        $this->assertSame('cash/RegisterForm', $this->inertiaPage($new)['component'] ?? null);
        $this->assertTrue(array_key_exists('register', $props) && $props['register'] === null, 'A new register has no record yet.');

        // UXP-610 of a register with an OPEN session carries it, for its reason (UXS-13).
        $edit = $this->get('/settings/cash-registers/'.$used.'/edit')->assertOk();
        $this->assertPageChannel($edit, 'owner', $secrets);
        $this->assertSame('Rina', ((array) ($this->propsOf($edit)['register'] ?? []))['openBy'] ?? null);

        // SEC-AZ-06: a guessed or malformed identifier is not found.
        foreach (['999999', '0', 'abc', '-1'] as $guess) {
            $this->getJson('/settings/cash-registers/'.$guess.'/edit')->assertStatus(404);
        }

        // Each page takes only its path (TR-HTTP-02).
        $this->get('/settings/cash-registers?status=active')->assertStatus(422);
    }

    public function test_the_open_page_offers_the_available_registers_and_none_when_every_one_is_taken(): void
    {
        // RP-104; UXI-094: active registers without an OPEN session; UXS-48 when none is.
        $free = TokoContoh::register('LACI-1', 'Laci 1');
        TokoContoh::register('LACI-X', 'Laci nonaktif', false);
        $used = TokoContoh::register('LACI-2', 'Laci 2');
        TokoContoh::openSession('Dewi', $used);
        $secrets = array_values(array_map(static fn (array $person): string => $person['password'], $this->people));

        foreach (['Rina' => 'cashier', 'Hendra' => 'owner'] as $person => $actor) {
            $this->as($person);
            $page = $this->get('/till/open-session')->assertOk();
            $this->assertSame('cash/OpenSession', $this->inertiaPage($page)['component'] ?? null);
            $this->assertPageChannel($page, $actor, $secrets);
            $this->assertSame([['id' => $free, 'code' => 'LACI-1', 'label' => 'Laci 1']], $this->propsOf($page)['registers'] ?? null, $person);
        }

        // A user who holds an OPEN session is sent to the till, RN-01 until TASK-029.
        $this->as('Dewi');
        $this->get('/till/open-session')->assertRedirect('/');

        TokoContoh::openSession('Hendra', $free);
        $this->as('Rina');
        $this->assertSame([], $this->propsOf($this->get('/till/open-session')->assertOk())['registers'] ?? null);

        // The page takes no parameter, and Operations has no till (CAP-21).
        $this->get('/till/open-session?register='.$free)->assertStatus(422);
        $this->as('Agus');
        $this->get('/till/open-session')->assertStatus(403);
    }

    public function test_the_home_of_a_cashier_without_an_open_session_is_buka_sesi_kas(): void
    {
        // NC-12, RN-01: a cashier without an OPEN session goes to RP-104; with one, to Akun saya until
        // TASK-029 delivers RP-101; the combined account to Stok either way until then; the Owner to
        // Akun saya until TASK-041.
        $first = TokoContoh::register('LACI-1', 'Laci 1');
        $second = TokoContoh::register('LACI-2', 'Laci 2');
        $third = TokoContoh::register('LACI-3', 'Laci 3');

        foreach (['Hendra' => '/account', 'Agus' => '/stock', 'Rina' => '/till/open-session', 'Dewi' => '/stock'] as $person => $home) {
            $this->as($person);
            $this->get('/')->assertRedirect($home);
        }

        TokoContoh::openSession('Rina', $first);
        TokoContoh::openSession('Dewi', $second);
        TokoContoh::openSession('Hendra', $third);

        foreach (['Hendra' => '/account', 'Rina' => '/account', 'Dewi' => '/stock'] as $person => $home) {
            $this->as($person);
            $this->get('/')->assertRedirect($home);
        }
    }

    public function test_the_users_list_marks_the_accounts_that_hold_an_open_session(): void
    {
        // UXI-334: "Memegang sesi kas".
        TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $this->as('Hendra');

        $rows = (array) ($this->propsOf($this->get('/settings/users')->assertOk())['users'] ?? []);
        $marks = [];

        foreach ($rows as $row) {
            $this->assertIsArray($row);
            $marks[self::text($row['displayName'] ?? null)] = $row['holdsSession'] ?? null;
        }

        ksort($marks);
        $this->assertSame(['Agus' => false, 'Dewi' => false, 'Hendra' => false, 'Rina' => true], $marks);
    }

    public function test_no_shared_drawer_transfer_paid_in_paid_out_or_pickup_exists(): void
    {
        // AC-027-02; BR-CASH-07: the routes and the operations hold none of them.
        $paths = array_keys(app('router')->getRoutes()->getRoutesByMethod()['POST'] ?? []);
        $operations = array_keys(Operations::FAMILIES);

        foreach (['shared', 'transfer', 'handover', 'paid_in', 'paid_out', 'pickup', 'expense', 'cash_in', 'cash_out'] as $word) {
            $this->assertSame([], array_values(array_filter($paths, static fn (int|string $path): bool => str_contains(strtolower((string) $path), $word))), $word);
            $this->assertSame([], array_values(array_filter($operations, static fn (string $operation): bool => str_starts_with($operation, 'cash') && str_contains($operation, $word))), $word);
        }

        $this->assertSame(['cash_session.open', 'cash_session.close', 'cash_session.close_assisted', 'cash_register.create', 'cash_register.update'], array_values(array_filter($operations, static fn (string $operation): bool => str_starts_with($operation, 'cash'))));
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<array-key, mixed>
     */
    private function propsOf(TestResponse $response): array
    {
        return (array) ($this->inertiaPage($response)['props'] ?? []);
    }
}
