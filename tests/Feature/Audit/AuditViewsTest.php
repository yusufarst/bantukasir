<?php

namespace Tests\Feature\Audit;

use App\Actions\Access\AnonymizeAccount;
use App\Actions\Access\DeactivateAccount;
use App\Actions\Catalog\CreateCategory;
use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Operations;
use App\Queries\Audit\ActivityLog;
use App\Support\Time\Clock;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * RP-612 "Riwayat aktivitas" and RP-613 "Riwayat keamanan" with their entries UXI-330 and UXI-331 on UXP-601
 * (AC-042-01 to AC-042-04; ROUTE_CONTRACTS RP-612, RP-613, RT-08; INTERACTION_CONTRACTS section 8; BR-AUD-02 to
 * BR-AUD-04; SECURITY_MODEL SEC-LOG-03, CAP-32, SEC-CH-01; SCHEMA section 3.5, G-16; EXECUTION_CONTEXT section
 * 10, a route and a read; suites AUDIT01, AUTH01, AUTH02, SEC01; TASK-042 section 7, L4 filters, scope and
 * payloads per role): only the Owner reaches the views and their entries, with only their allowlisted props
 * and never stored, and a read writes nothing; every other role and the combined account refused; every
 * parameter an allowlist; the Owner filters the business audit by date, user, type, product, session and
 * reference and the security log by date, type and user, each paged both ways; an event keeps the name its
 * actor had, also after an anonymization, and a combined account's events name its one account; the
 * Owner-only data reaches the Owner alone, and the read for anyone else holds neither it nor the security
 * audit; the staff's own pages never carry it.
 */
final class AuditViewsTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string ACTIVITY = '/settings/activity';

    private const string SECURITY = '/settings/security-log';

    /** The day of Asia/Jakarta on which each test acts, apart from the people that setUp() prepares. */
    private const string DAY = '2026-11-15';

    private const string ON_DAY = 'from=2026-11-15&to=2026-11-15';

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->travelTo(self::DAY.'T03:00:00.000000Z');
    }

    public function test_only_the_owner_opens_both_views_and_their_entries_with_only_their_allowlisted_props(): void
    {
        $this->as('Hendra');
        $events = $this->observer()->table('audit_events')->count();

        $activity = $this->get(self::ACTIVITY)->assertOk();
        $this->assertSame('audit/Activity', $this->inertiaPage($activity)['component'] ?? null);
        $this->assertPageChannel($activity, 'owner', $this->secrets());
        $this->assertStringContainsString('no-store', (string) $activity->headers->get('Cache-Control'));

        $security = $this->get(self::SECURITY)->assertOk();
        $this->assertSame('audit/SecurityLog', $this->inertiaPage($security)['component'] ?? null);
        $this->assertPageChannel($security, 'owner', $this->secrets());
        $this->assertStringContainsString('no-store', (string) $security->headers->get('Cache-Control'));

        // A read writes no business fact (EXECUTION_CONTEXT section 10: no GET that writes).
        $this->assertSame($events, $this->observer()->table('audit_events')->count());

        // UXI-330 and UXI-331 on UXP-601, after "Status sistem" (COPY_DECK section 11.1).
        $settings = (array) ($this->propsOf($this->get('/settings')->assertOk())['entries'] ?? []);
        $this->assertSame([['id' => 'activity', 'href' => self::ACTIVITY], ['id' => 'security', 'href' => self::SECURITY]], array_slice($settings, 4));

        // Every other role and the combined account: both views refused (UXS-07), and no entry to them; the
        // security audit and the security log are the Owner's alone (AC-042-01).
        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);

            foreach ([self::ACTIVITY, self::SECURITY] as $path) {
                $refused = $this->get($path)->assertStatus(403)->viewData('page');
                $this->assertIsArray($refused);
                $this->assertSame('shell/Error', $refused['component'] ?? null, $person.' '.$path);
                $this->getJson($path.'?'.self::ON_DAY)->assertStatus(403);
            }
        }

        $this->browse(null);
        $this->get(self::ACTIVITY)->assertRedirect('/login');
        $this->get(self::SECURITY)->assertRedirect('/login');
    }

    public function test_every_parameter_is_an_allowlist(): void
    {
        $this->as('Hendra');

        foreach (['x=1', 'action=NOPE', 'action=sale', 'user=0', 'user=abc', 'product=-1', 'session=2026', 'session=20261115-1', 'reference='.urlencode("A\tB"), 'reference='.str_repeat('a', 101), 'from=2026-11-31', 'after=abc', 'after=x&before=y', 'perPage=0', 'perPage=101'] as $query) {
            $this->getJson(self::ACTIVITY.'?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        foreach (['x=1', 'type=NOPE', 'type=sign_in_failed', 'user=0', 'action=SALE_CHECKOUT', 'after=abc', 'after=x&before=y', 'perPage=101'] as $query) {
            $this->getJson(self::SECURITY.'?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        foreach ([self::ACTIVITY, self::SECURITY] as $path) {
            $refused = $this->getJson($path.'?from=2026-11-16&to=2026-11-15')->assertStatus(422);
            $this->assertSame([['key' => 'ux.validation.date_order', 'params' => []]], $refused->json('fields.to'));
        }

        // A key that names nothing finds nothing, and is no error (TR-PAGE-03).
        $none = $this->propsOf($this->get(self::ACTIVITY.'?product=999999&user=999999')->assertOk());
        $this->assertSame([], $this->rowsIn($none, 'events'));
        $this->assertArrayHasKey('product', $none);
        $this->assertNull($none['product']);
    }

    public function test_the_owner_filters_the_business_audit_by_date_user_type_product_session_and_reference_with_pages_both_ways(): void
    {
        $product = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'sellingPrice' => '5000']);
        $document = TokoContoh::receive([['productId' => $product, 'quantity' => '10', 'cost' => ['status' => 'KNOWN', 'amount' => '3000']]], 'NOTA-7');
        $register = TokoContoh::register('LACI-1', 'Laci 1');
        $session = TokoContoh::openSession('Rina', $register);
        $sale = TokoContoh::sell('Rina', $session, [['productId' => $product, 'quantity' => '1']]);
        $sessionNumber = self::text($this->observer()->table('cash_sessions')->where('id', $session)->value('session_number'));
        $saleNumber = self::text($this->observer()->table('sales')->where('id', $sale)->value('sale_number'));
        $this->as('Hendra');

        // The day, newest first: the events of this test alone (AC-042-01).
        $day = $this->rowsIn($this->propsOf($this->get(self::ACTIVITY.'?'.self::ON_DAY)->assertOk()), 'events');
        $this->assertSame(['SALE_CHECKOUT', 'CASH_SESSION_OPEN', 'REGISTER_CREATE', 'RECEIVING_CONFIRM', 'PRODUCT_CREATE', 'CATEGORY_CREATE'], array_column($day, 'action'));
        $this->assertSame(['Rina', 'Rina', 'Hendra', 'Hendra', 'Hendra', 'Hendra'], array_map(static fn (array $row): mixed => ((array) ($row['actor'] ?? []))['name'] ?? null, $day));
        $this->assertSame([], $this->rowsIn($this->propsOf($this->get(self::ACTIVITY.'?from=2026-11-14&to=2026-11-14')->assertOk()), 'events'));

        // Each reference names its object and its page (UXI-379): the Sale by its number, the session by its
        // number, the receiving document by its reference, the product by its name.
        $this->assertSame([
            ['text' => $saleNumber, 'kind' => 'sale', 'id' => $sale],
            ['text' => $sessionNumber, 'kind' => 'session', 'id' => $session],
            ['text' => null, 'kind' => null, 'id' => null],
            ['text' => 'NOTA-7', 'kind' => 'receiving', 'id' => $document],
            ['text' => 'Pulpen Hitam', 'kind' => 'product', 'id' => $product],
        ], array_slice(array_column($day, 'reference'), 0, 5));

        // The user, the type, the product, the session and the reference.
        $this->assertSame(['SALE_CHECKOUT', 'CASH_SESSION_OPEN'], $this->actions(self::ON_DAY.'&user='.$this->people['Rina']['id']));
        $this->assertSame(['RECEIVING_CONFIRM'], $this->actions('action=RECEIVING_CONFIRM'));

        $chosen = $this->get(self::ACTIVITY.'?product='.$product)->assertOk();
        $this->assertSame(['SALE_CHECKOUT', 'RECEIVING_CONFIRM', 'PRODUCT_CREATE'], array_column($this->rowsIn($this->propsOf($chosen), 'events'), 'action'));
        $this->assertSame(['id' => $product, 'name' => 'Pulpen Hitam', 'sku' => 'PEN-01'], $this->propsOf($chosen)['product'] ?? null);
        $this->assertPageChannel($chosen, 'owner', $this->secrets(), 'product');

        $this->assertSame(['SALE_CHECKOUT', 'CASH_SESSION_OPEN'], $this->actions('session='.$sessionNumber));
        $this->assertSame(['RECEIVING_CONFIRM'], $this->actions('reference=NOTA-7'));
        $this->assertSame(['SALE_CHECKOUT'], $this->actions('reference='.$saleNumber));

        $empty = $this->get(self::ACTIVITY.'?session=20261115-999999')->assertOk();
        $this->assertSame([], $this->rowsIn($this->propsOf($empty), 'events'));
        $this->assertPageChannel($empty, 'owner', $this->secrets(), 'empty');

        // Keyset pages both ways (TR-PAGE-01).
        $first = (array) ($this->propsOf($this->get(self::ACTIVITY.'?'.self::ON_DAY.'&perPage=2')->assertOk())['events'] ?? []);
        $this->assertSame([['SALE_CHECKOUT', 'CASH_SESSION_OPEN'], null], [array_column($this->rowsOfPage($first), 'action'), $first['previousCursor'] ?? null]);
        $second = (array) ($this->propsOf($this->get(self::ACTIVITY.'?'.self::ON_DAY.'&perPage=2&after='.self::text($first['nextCursor'] ?? null))->assertOk())['events'] ?? []);
        $this->assertSame(['REGISTER_CREATE', 'RECEIVING_CONFIRM'], array_column($this->rowsOfPage($second), 'action'));
        $back = (array) ($this->propsOf($this->get(self::ACTIVITY.'?'.self::ON_DAY.'&perPage=2&before='.self::text($second['previousCursor'] ?? null))->assertOk())['events'] ?? []);
        $this->assertSame([['SALE_CHECKOUT', 'CASH_SESSION_OPEN'], null], [array_column($this->rowsOfPage($back), 'action'), $back['previousCursor'] ?? null]);
        $last = (array) ($this->propsOf($this->get(self::ACTIVITY.'?'.self::ON_DAY.'&perPage=2&after='.self::text($second['nextCursor'] ?? null))->assertOk())['events'] ?? []);
        $this->assertSame([['PRODUCT_CREATE', 'CATEGORY_CREATE'], null], [array_column($this->rowsOfPage($last), 'action'), $last['nextCursor'] ?? null]);
        $this->assertIsString($last['previousCursor'] ?? null);
    }

    public function test_an_event_keeps_the_name_of_its_moment_and_a_combined_account_names_one_identity(): void
    {
        // AC-042-02; SCHEMA section 3.5, G-16; BR-USR-12, D-043: Rina's events keep "Rina" after her account is
        // deactivated and anonymized, while the filter offers the account by its neutral label now.
        $rina = $this->people['Rina']['id'];
        $this->ownerRuns(DeactivateAccount::class, ['userId' => $rina], ['userVersion' => $this->userVersion($rina)]);
        $this->ownerRuns(AnonymizeAccount::class, ['userId' => $rina, 'reason' => 'Permintaan karyawan'], ['userVersion' => $this->userVersion($rina)]);
        $label = self::text($this->observer()->table('users')->where('id', $rina)->value('display_name'));
        $this->assertNotSame('Rina', $label);

        // Dewi acts as a cashier and as an operations admin, one account (D-032).
        $register = TokoContoh::register('LACI-1', 'Laci 1');
        TokoContoh::openSession('Dewi', $register);
        $this->runs($this->people['Dewi']['id'], CreateCategory::class, ['name' => 'Kertas']);

        $this->as('Hendra');
        $props = $this->propsOf($this->get(self::ACTIVITY.'?user='.$rina)->assertOk());
        $rows = $this->rowsIn($props, 'events');
        $this->assertNotSame([], $rows);
        $this->assertSame(['Rina'], array_values(array_unique(array_map(static fn (array $row): mixed => ((array) ($row['actor'] ?? []))['name'] ?? null, $rows))));
        $this->assertContains(['id' => $rina, 'displayName' => $label], (array) ($props['users'] ?? []));

        // The anonymization itself is the Owner's event, whose reference is the account by its name now.
        $anonymized = $this->rowsIn($this->propsOf($this->get(self::ACTIVITY.'?'.self::ON_DAY.'&action=USER_ANONYMIZE')->assertOk()), 'events');
        $this->assertCount(1, $anonymized);
        $this->assertSame([['kind' => 'USER', 'name' => 'Hendra'], ['text' => $label, 'kind' => 'user', 'id' => $rina], 'Permintaan karyawan'], [$anonymized[0]['actor'] ?? null, $anonymized[0]['reference'] ?? null, $anonymized[0]['reason'] ?? null]);

        $dewi = $this->rowsIn($this->propsOf($this->get(self::ACTIVITY.'?'.self::ON_DAY.'&user='.$this->people['Dewi']['id'])->assertOk()), 'events');
        $this->assertSame(['CATEGORY_CREATE', 'CASH_SESSION_OPEN'], array_column($dewi, 'action'));
        $this->assertSame([['kind' => 'USER', 'name' => 'Dewi'], ['kind' => 'USER', 'name' => 'Dewi']], array_column($dewi, 'actor'));
    }

    public function test_the_owner_only_data_reaches_the_owner_alone_and_never_a_staff_payload(): void
    {
        // AC-042-04; BR-AUD-02, BR-AUD-04, D-047: the reason and the values of a cost correction are the Owner's.
        $product = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam']);
        $document = TokoContoh::receive([['productId' => $product, 'quantity' => '10', 'cost' => ['status' => 'KNOWN', 'amount' => '3000']]], 'NOTA-8');
        TokoContoh::correctCost(TokoContoh::evidenceOf(TokoContoh::movementOf($document)), '3500', 'Nota pemasok rahasia');

        $this->as('Hendra');
        $correction = $this->rowsIn($this->propsOf($this->get(self::ACTIVITY.'?action=COST_CORRECTION')->assertOk()), 'events');
        $this->assertCount(1, $correction);
        $changes = array_column((array) ($correction[0]['changes'] ?? []), 'value', 'key');
        $this->assertSame(['Nota pemasok rahasia', true], [$changes['reason'] ?? null, array_key_exists('evidenceId', $changes)]);

        // The read for a reader who is not the Owner holds neither the Owner-only data nor any event of the
        // category SECURITY (BR-AUD-04): it is the read itself that keeps the scope, not only the gate.
        $filters = ['from' => null, 'to' => null, 'user' => null, 'action' => null, 'product' => null, 'session' => null, 'reference' => null, 'after' => null, 'before' => null, 'perPage' => 100];
        $staff = app(ActivityLog::class)->list($filters, false);
        $cost = app(ActivityLog::class)->list([...$filters, 'action' => 'COST_CORRECTION'], false);
        $keys = array_column($cost['rows'][0]['changes'] ?? [], 'key');
        sort($keys);
        $this->assertSame(['correctionNo', 'evidenceId', 'marksCleared', 'movementId', 'projectionsRewritten', 'publicationNo'], $keys);
        $this->assertStringNotContainsString('rahasia', (string) json_encode($staff));
        $business = $this->observer()->table('audit_events')->where('category', 'BUSINESS')->orderByDesc('occurred_at')->orderByDesc('id')->limit(100)->pluck('id')->all();
        $this->assertSame($business, array_column($staff['rows'], 'id'));
        $this->assertGreaterThan(0, $this->observer()->table('audit_events')->where('category', 'SECURITY')->count());

        // The staff's own pages within their scope (scope item 3): stock history and the receiving for
        // operations, the combined account's too; nothing of the Owner-only data reaches them.
        foreach (['Agus', 'Dewi'] as $person) {
            $this->as($person);
            $this->assertNoSecret($this->get('/stock/history?product='.$product)->assertOk(), ['Nota pemasok rahasia', 'rahasia']);
            $this->assertNoSecret($this->get('/stock/receivings/'.$document)->assertOk(), ['Nota pemasok rahasia', 'rahasia']);
        }
    }

    public function test_the_owner_filters_the_security_log_by_date_type_and_user_with_pages_both_ways(): void
    {
        $agus = $this->people['Agus']['id'];
        $wrong = 'Kc7#salah'.bin2hex(random_bytes(4));

        // Agus signs in and is refused the log; then a sign-in to his account fails from another address.
        $this->as('Agus');
        $this->get(self::SECURITY)->assertStatus(403);
        $this->browse(null);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.7'])->from('/login')->post('/login', ['email' => TokoContoh::email('Agus'), 'password' => $wrong])->assertRedirect('/login');
        $this->withServerVariables([]);

        $this->as('Hendra');
        $page = $this->get(self::SECURITY.'?'.self::ON_DAY.'&user='.$agus)->assertOk();
        $this->assertPageChannel($page, 'owner', [$wrong, ...$this->secrets()]);
        $rows = $this->rowsIn($this->propsOf($page), 'entries');
        $this->assertSame([
            ['type' => 'SIGN_IN_FAILED', 'actor' => ['kind' => 'ANONYMOUS', 'name' => null], 'subject' => 'Agus', 'outcome' => 'REFUSED', 'address' => '192.0.2.7'],
            ['type' => 'AUTHORIZATION_REFUSED', 'actor' => ['kind' => 'USER', 'name' => 'Agus'], 'subject' => 'Agus', 'outcome' => 'REFUSED', 'address' => '127.0.0.1'],
            ['type' => 'SIGN_IN_SUCCEEDED', 'actor' => ['kind' => 'USER', 'name' => 'Agus'], 'subject' => 'Agus', 'outcome' => 'SUCCEEDED', 'address' => '127.0.0.1'],
        ], array_map(static fn (array $row): array => array_diff_key($row, ['id' => true, 'occurredAt' => true]), $rows));

        // The type; another day; the Owner's own entries apart from his.
        $this->assertSame(['SIGN_IN_FAILED'], array_column($this->rowsIn($this->propsOf($this->get(self::SECURITY.'?'.self::ON_DAY.'&type=SIGN_IN_FAILED')->assertOk()), 'entries'), 'type'));
        $this->assertSame([], $this->rowsIn($this->propsOf($this->get(self::SECURITY.'?from=2026-11-14&to=2026-11-14&user='.$agus)->assertOk()), 'entries'));
        $hendra = $this->rowsIn($this->propsOf($this->get(self::SECURITY.'?'.self::ON_DAY.'&user='.$this->people['Hendra']['id'])->assertOk()), 'entries');
        $this->assertSame(['SIGN_IN_SUCCEEDED'], array_column($hendra, 'type'));

        $empty = $this->get(self::SECURITY.'?from=2026-11-14&to=2026-11-14')->assertOk();
        $this->assertPageChannel($empty, 'owner', $this->secrets(), 'empty');

        // Keyset pages both ways (TR-PAGE-01).
        $query = self::SECURITY.'?'.self::ON_DAY.'&user='.$agus.'&perPage=2';
        $first = (array) ($this->propsOf($this->get($query)->assertOk())['entries'] ?? []);
        $this->assertSame([['SIGN_IN_FAILED', 'AUTHORIZATION_REFUSED'], null], [array_column($this->rowsOfPage($first), 'type'), $first['previousCursor'] ?? null]);
        $second = (array) ($this->propsOf($this->get($query.'&after='.self::text($first['nextCursor'] ?? null))->assertOk())['entries'] ?? []);
        $this->assertSame([['SIGN_IN_SUCCEEDED'], null], [array_column($this->rowsOfPage($second), 'type'), $second['nextCursor'] ?? null]);
        $back = (array) ($this->propsOf($this->get($query.'&before='.self::text($second['previousCursor'] ?? null))->assertOk())['entries'] ?? []);
        $this->assertSame(['SIGN_IN_FAILED', 'AUTHORIZATION_REFUSED'], array_column($this->rowsOfPage($back), 'type'));
    }

    /**
     * The actions of the events that a query of RP-612 lists, newest first.
     *
     * @return list<mixed>
     */
    private function actions(string $query): array
    {
        return array_column($this->rowsIn($this->propsOf($this->get(self::ACTIVITY.'?'.$query)->assertOk()), 'events'), 'action');
    }

    /**
     * @param  class-string<CommandAction>  $action
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     */
    private function ownerRuns(string $action, array $payload, array $expected): void
    {
        $this->runs($this->people['Hendra']['id'], $action, $payload, $expected);
    }

    /**
     * A command that a person runs through its Action, as its route would after the gate.
     *
     * @param  class-string<CommandAction>  $action
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     */
    private function runs(int $actor, string $action, array $payload, array $expected = []): void
    {
        $command = app($action);
        $envelope = new Envelope($command->operation(), Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, $expected, $payload);
        $command->execute(new CommandContext($actor, $envelope, app(Clock::class)->now()));
    }

    private function userVersion(int $user): int
    {
        return self::number($this->observer()->table('users')->where('id', $user)->value('version'));
    }

    /**
     * @return list<string>
     */
    private function secrets(): array
    {
        return array_values(array_map(static fn (array $one): string => $one['password'], $this->people));
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<array-key, mixed>
     */
    private function propsOf(TestResponse $response): array
    {
        return (array) ($this->inertiaPage($response)['props'] ?? []);
    }

    /**
     * The rows of a page's list prop.
     *
     * @param  array<array-key, mixed>  $props
     * @return list<array<array-key, mixed>>
     */
    private function rowsIn(array $props, string $list): array
    {
        return $this->rowsOfPage((array) ($props[$list] ?? []));
    }

    /**
     * @param  array<array-key, mixed>  $page
     * @return list<array<array-key, mixed>>
     */
    private function rowsOfPage(array $page): array
    {
        $rows = [];

        foreach ((array) ($page['rows'] ?? []) as $row) {
            $rows[] = (array) $row;
        }

        return $rows;
    }
}
