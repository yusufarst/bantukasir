<?php

namespace Tests\Feature\Reporting;

use App\Support\Time\BusinessDate;
use App\Support\Time\Clock;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * RP-501, UXP-501 "Rekap harian" (AC-041-01, AC-041-04; ROUTE_CONTRACTS RP-501; INTERACTION_CONTRACTS section 3.4;
 * SECURITY_MODEL CAP-22, SEC-CH-01; UI_STATES UXS-07, UXS-22; EXECUTION_CONTEXT section 10, a route and a read;
 * suites AUTH01, AUTH02, SEC01; TASK-041 section 7, L4 access): only the Owner reaches the summary, with only its
 * allowlisted props and never stored; every other role and the combined account refused; today by default, an
 * earlier date read, and a date after today, a date that does not exist or any other parameter refused
 * VALIDATION_FAILED with nothing read; the summary read again by name; a read that fails is a null summary, never
 * zero, and its cause reaches the page nowhere.
 */
final class DailyPageTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string PAGE = '/reports/daily';

    private const string COMPONENT = 'reporting/Daily';

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
    }

    /** Today in Asia/Jakarta by the application's clock, which the sign-in session follows too. */
    private static function today(): BusinessDate
    {
        return BusinessDate::ofInstant(app(Clock::class)->now());
    }

    public function test_only_the_owner_opens_the_summary_with_only_its_allowlisted_props(): void
    {
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));
        // Today: a session sold in and closed, and one still open, so that both lists carry rows.
        $product = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'sellingPrice' => '10000']);
        TokoContoh::stock($product, '5', '25000');
        $closed = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'), '10000');
        TokoContoh::sell('Rina', $closed, [['productId' => $product, 'quantity' => '1']]);
        TokoContoh::closeSession($closed);
        $open = TokoContoh::openSession('Dewi', TokoContoh::register('LACI-2', 'Laci 2'));
        $this->as('Hendra');

        $page = $this->get(self::PAGE)->assertOk();
        $this->assertSame(self::COMPONENT, $this->inertiaPage($page)['component'] ?? null);
        $this->assertPageChannel($page, 'owner', $secrets);
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));

        // Today in Asia/Jakarta by default; the summary of the snapshot.
        $today = self::today()->toString();
        $props = $this->propsOf($page);
        $this->assertSame([$today, $today], [$props['date'] ?? null, $props['today'] ?? null]);
        $summary = (array) ($props['summary'] ?? []);
        $this->assertSame($today, $summary['date'] ?? null);
        $this->assertSame([$closed], array_column((array) ($summary['sessions'] ?? []), 'id'));
        $this->assertSame([$open], array_column((array) ($summary['active'] ?? []), 'id'));
        $this->assertSame('10000', ((array) ($summary['figures'] ?? []))['grossSales'] ?? null);

        // "Coba lagi" reads the summary alone, with only its allowlisted fields.
        $version = $this->inertiaPage($page)['version'] ?? null;
        $this->assertIsString($version);
        $this->assertDeferredChannel($this->reload($version), 'owner', $secrets);

        // An earlier date is read.
        $yesterday = self::today()->addDays(-1)->toString();
        $earlier = $this->propsOf($this->get(self::PAGE.'?date='.$yesterday)->assertOk());
        $this->assertSame($yesterday, $earlier['date'] ?? null);
        $this->assertSame($yesterday, ((array) ($earlier['summary'] ?? []))['date'] ?? null);

        // Every other role and the combined account: refused (UXS-07).
        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $refused = $this->get(self::PAGE)->assertStatus(403)->viewData('page');
            $this->assertIsArray($refused);
            $this->assertSame('shell/Error', $refused['component'] ?? null, $person);
            $this->getJson(self::PAGE.'?date='.$today)->assertStatus(403);
        }

        $this->browse(null);
        $this->get(self::PAGE)->assertRedirect('/login');
    }

    public function test_a_date_after_today_or_that_does_not_exist_and_any_other_parameter_are_refused_before_anything_is_read(): void
    {
        $this->as('Hendra');
        $today = self::today();

        $reads = 0;
        DB::listen(static function (QueryExecuted $query) use (&$reads): void {
            if (str_contains($query->sql, 'from sales s') || str_contains($query->sql, 'cash_session_closes')) {
                $reads++;
            }
        });

        foreach ([
            // After today in Asia/Jakarta (INTERACTION_CONTRACTS section 3.4).
            'date='.$today->addDays(1)->toString(),
            'date='.$today->addDays(400)->toString(),
            // A date that does not exist or is not written YYYY-MM-DD; an empty one; a list.
            'date=2026-02-30',
            'date=21-11-2026',
            'date=',
            'date[]=2026-11-01',
            // Any other parameter (RT-08).
            'period=today',
            'date='.$today->toString().'&x=1',
        ] as $query) {
            $this->getJson(self::PAGE.'?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame(0, $reads, 'A refused date reads no figure and no session.');
    }

    public function test_the_summary_is_read_again_by_name_and_a_failed_read_is_never_zero(): void
    {
        $this->as('Hendra');
        $version = $this->inertiaPage($this->get(self::PAGE)->assertOk())['version'] ?? null;
        $this->assertIsString($version);

        // "Coba lagi": the summary alone, by name; with no session today, both lists are empty.
        $reload = $this->reload($version);
        $this->assertSame(['sessions' => [], 'active' => []], array_intersect_key((array) ($this->propsOf($reload)['summary'] ?? []), ['sessions' => true, 'active' => true]));
        $this->assertArrayNotHasKey('date', $this->propsOf($reload));

        // A read that fails inside the snapshot is a null summary (UXS-22), on the page and on its reload, and its
        // message reaches neither; the date and today still stand for the date control.
        DB::listen(static function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'cash_session_closes')) {
                throw new RuntimeException('summary failed at /secret/path');
            }
        });

        $failed = $this->get(self::PAGE)->assertOk();
        $this->assertArrayHasKey('summary', $this->propsOf($failed));
        $this->assertNull($this->propsOf($failed)['summary']);
        $this->assertPageChannel($failed, 'owner', ['/secret/path'], 'failed');
        $this->assertSame(0, DB::connection()->transactionLevel(), 'The failed read leaves no transaction open.');

        $failedReload = $this->reload($version);
        $this->assertArrayHasKey('summary', $this->propsOf($failedReload));
        $this->assertNull($this->propsOf($failedReload)['summary']);
        $this->assertStringNotContainsString('/secret/path', (string) $failedReload->getContent());
    }

    /**
     * The page's own read of its summary, by name, as "Coba lagi" makes it.
     *
     * @return TestResponse<Response>
     */
    private function reload(string $version): TestResponse
    {
        $reload = $this->get(self::PAGE, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => self::COMPONENT,
            'X-Inertia-Partial-Data' => 'summary',
        ])->assertOk();
        $this->assertSame(self::COMPONENT, $this->inertiaPage($reload)['component'] ?? null);

        return $reload;
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
