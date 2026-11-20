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
 * RP-502, UXP-502 "Laporan keuangan" (AC-040-01, AC-040-02, AC-040-05; ROUTE_CONTRACTS RP-502; INTERACTION_CONTRACTS
 * section 3.4; TECHNICAL_RULES section 11.4; SECURITY_MODEL CAP-20, SEC-CH-01; UI_STATES UXS-07, UXS-22;
 * EXECUTION_CONTEXT section 10, a route and a read; suites AUTH01, AUTH02, SEC01; threat THR-02; TASK-040 section 7,
 * L4 period validation and access): only the Owner reaches the report, with only its allowlisted props and never
 * stored; every other role and the combined account refused; the periods of RP-502 and nothing else; a custom range
 * that starts after its end, ends after today or is longer than 366 days refused VALIDATION_FAILED with nothing read;
 * the report read again by name; a read that fails is a null report, never zero, and its cause reaches the page
 * nowhere.
 */
final class FinancePageTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string PAGE = '/reports/finance';

    private const string COMPONENT = 'reporting/Finance';

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

    public function test_only_the_owner_opens_the_report_with_only_its_allowlisted_props(): void
    {
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));
        $this->as('Hendra');

        $page = $this->get(self::PAGE)->assertOk();
        $this->assertSame(self::COMPONENT, $this->inertiaPage($page)['component'] ?? null);
        $this->assertPageChannel($page, 'owner', $secrets);
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));

        // Bulan Ini by default; today in Asia/Jakarta for the date fields; the report of the snapshot.
        $today = self::today();
        $props = $this->propsOf($page);
        $this->assertSame(['kind' => 'this_month', 'from' => null, 'to' => null], $props['period'] ?? null);
        $this->assertSame($today->toString(), $props['today'] ?? null);
        $report = (array) ($props['report'] ?? []);
        $this->assertMatchesRegularExpression('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}Z$/D', (string) ($report['asOf'] ?? ''));
        $this->assertSame(['kind' => 'this_month', 'from' => $today->firstOfMonth()->toString(), 'to' => $today->toString()], $report['period'] ?? null);

        // Every other role and the combined account: refused (UXS-07).
        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $refused = $this->get(self::PAGE)->assertStatus(403)->viewData('page');
            $this->assertIsArray($refused);
            $this->assertSame('shell/Error', $refused['component'] ?? null, $person);
            $this->getJson(self::PAGE.'?period=today')->assertStatus(403);
        }

        $this->browse(null);
        $this->get(self::PAGE)->assertRedirect('/login');
    }

    public function test_the_periods_of_rp_502_are_read_and_a_custom_range_is_refused_before_anything_is_read(): void
    {
        $this->as('Hendra');
        $today = self::today();
        $day = $today->toString();
        $first = $today->firstOfMonth();
        // 366 days, both dates included, ending today (TECHNICAL_RULES section 11.4), and one day more.
        $longest = $today->addDays(-365)->toString();
        $tooLong = $today->addDays(-366)->toString();

        foreach ([
            'period=today' => ['today', $day, $day],
            'period=7d' => ['7d', $today->addDays(-6)->toString(), $day],
            'period=this_month' => ['this_month', $first->toString(), $day],
            'period=last_month' => ['last_month', $first->firstOfPreviousMonth()->toString(), $first->addDays(-1)->toString()],
            "period=custom&from={$day}&to={$day}" => ['custom', $day, $day],
            "period=custom&from={$longest}&to={$day}" => ['custom', $longest, $day],
        ] as $query => [$kind, $from, $to]) {
            $report = (array) ($this->propsOf($this->get(self::PAGE.'?'.$query)->assertOk())['report'] ?? []);
            $this->assertSame(['kind' => $kind, 'from' => $from, 'to' => $to], $report['period'] ?? null, $query);
        }

        $reads = 0;
        DB::listen(static function (QueryExecuted $query) use (&$reads): void {
            if (str_contains($query->sql, 'from sales s')) {
                $reads++;
            }
        });

        foreach ([
            // The start after the end, the end after today in Asia/Jakarta, 367 days (INTERACTION_CONTRACTS section 3.4).
            'period=custom&from='.$day.'&to='.$today->addDays(-1)->toString(),
            'period=custom&from='.$first->toString().'&to='.$today->addDays(1)->toString(),
            "period=custom&from={$tooLong}&to={$day}",
            // A custom period needs both dates, and only a custom period has dates.
            'period=custom&from=2026-11-01',
            'period=custom',
            'period=today&from=2026-11-01&to=2026-11-02',
            'from=2026-11-01&to=2026-11-02',
            // Dates that do not exist or are not written YYYY-MM-DD; a period that RP-502 does not have; any other parameter.
            'period=custom&from=2026-02-30&to=2026-03-01',
            'period=custom&from=1-11-2026&to=2026-11-02',
            'period=year',
            'period=today&x=1',
        ] as $query) {
            $this->getJson(self::PAGE.'?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame(0, $reads, 'A refused period reads no figure.');
    }

    public function test_the_report_is_read_again_by_name_and_a_failed_read_is_never_zero(): void
    {
        $this->as('Hendra');
        $version = $this->inertiaPage($this->get(self::PAGE)->assertOk())['version'] ?? null;
        $this->assertIsString($version);

        // "Coba lagi": the report alone, by name.
        $this->assertDeferredChannel($this->reload($version), 'owner');

        // A read that fails inside the snapshot is a null report (UXS-22), on the page and on its reload, and its
        // message reaches neither; the period and today still stand for the period choice.
        DB::listen(static function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'join payments p')) {
                throw new RuntimeException('report failed at /secret/path');
            }
        });

        $failed = $this->get(self::PAGE.'?period=today')->assertOk();
        $this->assertArrayHasKey('report', $this->propsOf($failed));
        $this->assertNull($this->propsOf($failed)['report']);
        $this->assertSame(['kind' => 'today', 'from' => null, 'to' => null], $this->propsOf($failed)['period'] ?? null);
        $this->assertPageChannel($failed, 'owner', ['/secret/path'], 'failed');
        $this->assertSame(0, DB::connection()->transactionLevel(), 'The failed read leaves no transaction open.');

        $failedReload = $this->reload($version);
        $this->assertArrayHasKey('report', $this->propsOf($failedReload));
        $this->assertNull($this->propsOf($failedReload)['report']);
        $this->assertStringNotContainsString('/secret/path', (string) $failedReload->getContent());
    }

    /**
     * The page's own read of its report, by name, as "Coba lagi" makes it.
     *
     * @return TestResponse<Response>
     */
    private function reload(string $version): TestResponse
    {
        $reload = $this->get(self::PAGE, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => self::COMPONENT,
            'X-Inertia-Partial-Data' => 'report',
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
