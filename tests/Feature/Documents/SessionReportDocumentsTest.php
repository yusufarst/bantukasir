<?php

namespace Tests\Feature\Documents;

use App\Database\CommandPoints;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-45, RC-46 and RP-206P through their routes (AC-031-02, AC-031-03, AC-031-05; INTERACTION_CONTRACTS,
 * "RC-44 to RC-47"; DOCUMENT_CONTRACTS DC-02, DC-03; BR-CASH-19 to BR-CASH-22, BR-AUD-06; SEC-CH-07;
 * EXECUTION_CONTEXT section 10, a command, a route and a document; suites SESSION08, SESSION10, AUTH01,
 * AUTH02 and CMD01; TASK-031 section 7, L2 and L4): a CLOSED session's report printed from its snapshot and
 * exported as the CSV of DC-03, each request audited and creating no fact or session change; a repeated or
 * retried export the same file; an OPEN session, another user's, a guessed one and Operations refused.
 */
final class SessionReportDocumentsTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string PRINT = '/commands/document.print_session_report';

    private const string EXPORT = '/commands/document.export_session_report';

    /** The tables of the business facts that a print or an export never touches (BR-CASH-22). */
    private const array FACTS = ['sales', 'payments', 'stock_movements', 'cash_sessions', 'cash_session_closes', 'cash_session_reports'];

    private const array RECORDS = ['command_identities', 'command_results', 'audit_events'];

    private int $session = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $product = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'sellingPrice' => '50000']);
        TokoContoh::stock($product, '20');
        $this->travelTo('2026-11-12T01:00:00.000000Z');
        $this->session = TokoContoh::openSession('Rina', TokoContoh::register('=LACI', '+Laci "utama"'), '20000');
        TokoContoh::sell('Rina', $this->session, [['productId' => $product, 'quantity' => '2']]);
        TokoContoh::sell('Rina', $this->session, [['productId' => $product, 'quantity' => '1']], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-1']);
        $this->travelTo('2026-11-12T10:30:00.000000Z');
        // Rp118.500 counted against Rp120.000 expected: a variance of −Rp1.500.
        TokoContoh::closeSession($this->session, '118500');
    }

    public function test_the_csv_follows_dc_03_exactly_and_a_repeat_or_a_retry_is_the_same_file(): void
    {
        // AC-031-03, L1 and L4: the byte-order mark, semicolons, CRLF, plain integers, a negative variance
        // numeric with its word, times of Asia/Jakarta with the time-zone line, every text quoted and the
        // formula prefixes of the register's code and label neutralized.
        $this->as('Rina');
        $facts = $this->counts2(self::FACTS);
        $envelope = $this->commandEnvelope('document.export_session_report', ['sessionId' => $this->session]);
        $response = $this->postJson(self::EXPORT, $envelope)->assertStatus(201);
        $number = $this->sessionNumber();

        $this->assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=laporan-sesi-'.$number.'.csv', $response->headers->get('Content-Disposition'));
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $expected = "\u{FEFF}".implode("\r\n", [
            '"Laporan sesi kas";"'.$number.'"',
            '"Laci kas";"\'=LACI · +Laci ""utama"""',
            '"Penanggung jawab";"Rina"',
            '"Dibuka";"2026-11-12 08:00"',
            '"Dihitung sampai";"2026-11-12 17:30"',
            '"Ditutup";"2026-11-12 17:30"',
            '"Modal awal (Rp)";20000',
            '"Jumlah penjualan";2',
            '"Nilai penjualan (Rp)";150000',
            '"Pembayaran tunai (Rp)";100000',
            '"Pembayaran transfer bank (Rp)";50000',
            '"Pengembalian dari laci kas (Rp)";0',
            '"Uang seharusnya di laci (Rp)";120000',
            '"Uang fisik di laci (Rp)";118500',
            '"Selisih (Rp)";-1500;"kurang"',
            '"Ditutup oleh";"Rina"',
            '"Zona waktu: WIB (Asia/Jakarta)"',
        ])."\r\n";
        $this->assertSame($expected, $response->getContent());

        $event = (array) $this->watcher()->table('audit_events')->where('command_id', self::number($this->watcher()->table('command_identities')->where('source_intent_id', $envelope['sourceIntentId'])->value('id')))->sole();
        $this->assertSame(['BUSINESS', 'EXPORT', $this->people['Rina']['id'], 'cash_session_report', $this->session, $this->session, $number], [$event['category'], $event['action'], $event['actor_user_id'], $event['subject_type'], $event['subject_id'], $event['cash_session_id'], $event['reference']]);

        // A repeat of the same identity is the same file, and no new record; a retry after a failed
        // download with a new request is the same file too (BR-CASH-22).
        $records = $this->counts2(self::RECORDS);
        $this->assertSame($expected, $this->postJson(self::EXPORT, $envelope)->assertStatus(200)->getContent());
        $this->assertSame($records, $this->counts2(self::RECORDS));
        $this->as('Hendra');
        $this->assertSame($expected, $this->postJson(self::EXPORT, $this->commandEnvelope('document.export_session_report', ['sessionId' => $this->session]))->assertStatus(201)->getContent());

        // No fact and no session change (AC-031-02).
        $this->assertSame($facts, $this->counts2(self::FACTS));
        $this->assertSame('CLOSED', $this->watcher()->table('cash_sessions')->where('id', $this->session)->value('status'));
    }

    public function test_the_print_request_leads_to_the_report_rendered_from_its_snapshot_and_writes_nothing(): void
    {
        foreach (['Rina' => 'cashier', 'Hendra' => 'owner'] as $person => $actor) {
            $this->as($person);
            $response = $this->postJson(self::PRINT, $this->commandEnvelope('document.print_session_report', ['sessionId' => $this->session]))->assertStatus(201);
            $this->assertJsonChannel($response, 'commands.committed', $actor);
            $commandId = self::number($response->json('commandId'));
            $location = '/cash-sessions/'.$this->session.'/report/print/'.$commandId;
            $this->assertSame(['session_report_print', $commandId, $location], [$response->json('result.type'), $response->json('result.id'), $response->json('result.location')]);
            $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
            $this->assertSame(['PRINT', 'cash_session_report', $this->session, $this->session], [$event['action'], $event['subject_type'], $event['subject_id'], $event['cash_session_id']]);

            $before = $this->counts2([...self::FACTS, ...self::RECORDS]);
            $page = $this->get($location)->assertOk();
            $this->assertSame('documents/SessionReportPrint', $this->inertiaPage($page)['component'] ?? null);
            $this->assertPageChannel($page, $actor, $this->secrets());
            $props = $this->propsOf($page);
            $report = (array) ($props['report'] ?? []);
            $this->assertSame(
                [$this->sessionNumber(), '=LACI', '+Laci "utama"', '20000', 2, '150000', '100000', '50000', '0', '120000', '118500', '-1500', 'Rina', 'session_report.1', '2026-11-12T10:30:00.000000Z'],
                [$report['number'] ?? null, $report['registerCode'] ?? null, $report['registerLabel'] ?? null, $report['openingCash'] ?? null, $report['saleCount'] ?? null, $report['saleValue'] ?? null, $report['cashPayments'] ?? null, $report['transferPayments'] ?? null, $report['drawerRefunds'] ?? null, $report['expectedCash'] ?? null, $report['physicalCash'] ?? null, $report['variance'] ?? null, $report['closedBy'] ?? null, $report['template'] ?? null, $report['closedAt'] ?? null],
            );
            $this->assertSame(['name' => 'Toko Contoh', 'identity' => null], $props['issuer'] ?? null);
            $this->assertSame($before, $this->counts2([...self::FACTS, ...self::RECORDS]));
        }

        // Another session's key or a guessed print request is NOT_FOUND, and the view takes only its path.
        $this->as('Rina');
        $print = self::number($this->postJson(self::PRINT, $this->commandEnvelope('document.print_session_report', ['sessionId' => $this->session]))->assertStatus(201)->json('commandId'));
        $this->getJson('/cash-sessions/'.$this->session.'/report/print/999999')->assertStatus(404);
        $this->getJson('/cash-sessions/999999/report/print/'.$print)->assertStatus(404);
        $this->getJson('/cash-sessions/'.$this->session.'/report/print/'.$print.'?size=a4')->assertStatus(422);
    }

    public function test_an_open_session_another_users_and_a_guessed_one_are_not_found_and_operations_is_refused(): void
    {
        // AC-031-05; BR-CASH-21: an OPEN session has no report.
        $open = TokoContoh::openSession('Dewi', TokoContoh::register('LACI-2', 'Laci 2'));
        $records = $this->counts2(self::RECORDS);

        foreach ([[self::PRINT, 'document.print_session_report'], [self::EXPORT, 'document.export_session_report']] as [$path, $operation]) {
            foreach (['Dewi' => $this->session, 'Rina' => $open, 'Hendra' => 999999] as $person => $session) {
                $this->as($person);
                $this->postJson($path, $this->commandEnvelope($operation, ['sessionId' => $session]))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
            }

            $this->as('Dewi');
            $this->postJson($path, $this->commandEnvelope($operation, ['sessionId' => $open]))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

            $this->as('Agus');
            $this->postJson($path, $this->commandEnvelope($operation, ['sessionId' => $this->session]))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');

            $this->as('Rina');

            foreach ([['sessionId' => (string) $this->session], [], ['sessionId' => $this->session, 'format' => 'xlsx']] as $payload) {
                $this->postJson($path, $this->commandEnvelope($operation, $payload))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            }
        }

        $this->assertSame($records, $this->counts2(self::RECORDS));

        // Another cashier's print view, also with a committed print request's key.
        $this->as('Rina');
        $print = self::number($this->postJson(self::PRINT, $this->commandEnvelope('document.print_session_report', ['sessionId' => $this->session]))->assertStatus(201)->json('commandId'));
        $this->as('Dewi');
        $this->getJson('/cash-sessions/'.$this->session.'/report/print/'.$print)->assertStatus(404);
        $this->as('Agus');
        $this->getJson('/cash-sessions/'.$this->session.'/report/print/'.$print)->assertStatus(403);
    }

    public function test_a_failure_after_every_write_step_leaves_no_record_and_a_retry_gives_the_same_file(): void
    {
        // TS-21: the audit event and the result; a failed export retries the same snapshot (AC-031-03).
        $this->as('Rina');
        $file = $this->postJson(self::EXPORT, $this->commandEnvelope('document.export_session_report', ['sessionId' => $this->session]))->assertStatus(201)->getContent();

        foreach ([[self::PRINT, 'document.print_session_report'], [self::EXPORT, 'document.export_session_report']] as [$path, $operation]) {
            foreach ([1, 2] as $step) {
                $this->app->instance(CommandPoints::class, new FailurePoints($step));
                $records = $this->counts2(self::RECORDS);
                $envelope = $this->commandEnvelope($operation, ['sessionId' => $this->session]);

                $this->postJson($path, $envelope)->assertStatus(500);

                $this->assertSame($records, $this->counts2(self::RECORDS), $operation.' after write step '.$step);
                $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', $operation)->orderByDesc('id')->value('outcome'));

                $this->app->instance(CommandPoints::class, new FailurePoints);
                $retry = $this->postJson($path, $envelope)->assertStatus(201);

                if ($path === self::EXPORT) {
                    $this->assertSame($file, $retry->getContent());
                }
            }
        }

        // A status check recovers an export's result: its session (RC-50).
        $envelope = $this->commandEnvelope('document.export_session_report', ['sessionId' => $this->session]);
        $this->postJson(self::EXPORT, $envelope)->assertStatus(201);
        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.id', $this->session)->assertJsonPath('result.location', '/cash-sessions/'.$this->session);
    }

    private function sessionNumber(): string
    {
        return self::text($this->watcher()->table('cash_sessions')->where('id', $this->session)->value('session_number'));
    }

    /**
     * An envelope of RC-45 or RC-46.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function commandEnvelope(string $operation, array $payload): array
    {
        return [
            'operation' => $operation,
            'version' => 1,
            'sourceIntentId' => (string) Str::uuid(),
            'idempotencyKey' => (string) Str::uuid(),
            'recoveryEpoch' => $this->epoch,
            'expected' => (object) [],
            'payload' => $payload,
        ];
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    private function counts2(array $tables): array
    {
        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = $this->watcher()->table($table)->count();
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    private function secrets(): array
    {
        return array_values(array_map(static fn (array $person): string => $person['password'], $this->people));
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<array-key, mixed>
     */
    private function propsOf(TestResponse $response): array
    {
        return (array) ($this->inertiaPage($response)['props'] ?? []);
    }

    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
