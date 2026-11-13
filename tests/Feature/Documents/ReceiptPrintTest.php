<?php

namespace Tests\Feature\Documents;

use App\Actions\Access\AnonymizeAccount;
use App\Actions\Access\DeactivateAccount;
use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Configuration\ChangeBusinessProfile;
use App\Database\CommandPoints;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Operations;
use App\Models\User;
use App\Support\Time\Clock;
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
 * RC-44 and RP-103P through their routes (AC-031-01, AC-031-02, AC-031-04 to AC-031-06; INTERACTION_CONTRACTS,
 * "RC-44 to RC-47"; DOCUMENT_CONTRACTS DC-01; BR-DOC-01 to BR-DOC-06, BR-AUD-06; REQ-SALE-11; RACE-16, RACE-25;
 * EXECUTION_CONTEXT section 10, a command, a route and a document; suites SESSION08, IDENTITY01, AUTH01, AUTH02
 * and CMD01; TASK-031 section 7, L2 and L4): the first print request of a Sale is the original and every
 * later one, by anyone, a copy; each is audited and creates no fact; the print view renders the snapshot in
 * the size asked for, by its template, with the copy marker of a copy, also after a profile change and an
 * anonymization; another cashier's Sale, a guessed one and Operations are refused.
 */
final class ReceiptPrintTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string PATH = '/commands/document.print_receipt';

    /** The tables of the business facts that a print never touches (BR-DOC-04). */
    private const array FACTS = ['sales', 'sale_lines', 'payments', 'receipt_snapshots', 'stock_movements', 'issue_cost_allocations', 'cash_sessions', 'cash_session_closes', 'business_profile_revisions'];

    /** The tables that a refused print request leaves as they were. */
    private const array RECORDS = ['command_identities', 'command_results', 'audit_events'];

    private int $category = 0;

    private int $sale = 0;

    private int $session = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category();
        $product = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'sellingPrice' => '7500', 'categoryId' => $this->category]);
        TokoContoh::stock($product, '20');
        $this->session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $this->sale = TokoContoh::sell('Rina', $this->session, [['productId' => $product, 'quantity' => '2']], ['method' => 'CASH', 'tendered' => '20000']);
    }

    public function test_the_first_print_is_the_original_and_every_later_one_a_copy_audited_creating_no_fact(): void
    {
        $this->as('Rina');
        $facts = $this->counts2(self::FACTS);
        $version = $this->sessionVersion();
        $envelope = $this->commandEnvelope(['saleId' => $this->sale, 'size' => '58']);

        $response = $this->postJson(self::PATH, $envelope)->assertStatus(201);
        $this->assertJsonChannel($response, 'commands.committed.print', 'cashier');
        $first = self::number($response->json('commandId'));
        $this->assertSame(['receipt_print', $first, false, '/sales/'.$this->sale.'/receipt/print/'.$first.'?size=58'], [$response->json('result.type'), $response->json('result.id'), $response->json('result.copy'), $response->json('result.location')]);

        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $first)->sole();
        $this->assertSame(['BUSINESS', 'PRINT', $this->people['Rina']['id'], 'sale', $this->sale, $this->session, $this->saleNumber()], [$event['category'], $event['action'], $event['actor_user_id'], $event['subject_type'], $event['subject_id'], $event['cash_session_id'], $event['reference']]);
        $this->assertSame(['copy' => false, 'size' => '58'], self::dataOf($event['change_data']));

        // A repeat with the same identity is the same print request, still the original.
        $records = $this->counts2(self::RECORDS);
        $this->postJson(self::PATH, $envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('commandId', $first)->assertJsonPath('result.copy', false);
        $this->assertSame($records, $this->counts2(self::RECORDS));

        // Every later print request is a copy, by the cashier and by the Owner (BR-DOC-04).
        $again = $this->postJson(self::PATH, $this->commandEnvelope(['saleId' => $this->sale, 'size' => '80']))->assertStatus(201)->assertJsonPath('result.copy', true);
        $this->assertStringEndsWith('?size=80', self::text($again->json('result.location')));
        $this->as('Hendra');
        $owner = $this->postJson(self::PATH, $this->commandEnvelope(['saleId' => $this->sale, 'size' => 'A4']))->assertStatus(201)->assertJsonPath('result.copy', true);
        $this->assertJsonChannel($owner, 'commands.committed.print', 'owner');
        $this->assertStringEndsWith('?size=a4', self::text($owner->json('result.location')));

        // No Sale, Payment, movement or session change (AC-031-02), and three audit events.
        $this->assertSame($facts, $this->counts2(self::FACTS));
        $this->assertSame($version, $this->sessionVersion());
        $this->assertSame(3, $this->watcher()->table('audit_events')->where('action', 'PRINT')->where('subject_id', $this->sale)->count());

        // A status check recovers the print request (RC-50).
        $this->as('Rina');
        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.copy', false);
    }

    public function test_another_cashiers_sale_and_a_guessed_one_are_not_found_and_operations_is_refused(): void
    {
        // AC-031-05: the combined account holds CAP-21 on its own Sales only.
        $records = $this->counts2(self::RECORDS);

        foreach (['Dewi' => $this->sale, 'Rina' => 999999] as $person => $sale) {
            $this->as($person);
            $this->postJson(self::PATH, $this->commandEnvelope(['saleId' => $sale, 'size' => '58']))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        $this->as('Agus');
        $this->postJson(self::PATH, $this->commandEnvelope(['saleId' => $this->sale, 'size' => '58']))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');

        // The payload: a size of the three as a text, the Sale a JSON whole number, nothing else.
        $this->as('Rina');

        foreach ([
            ['saleId' => $this->sale, 'size' => '57'],
            ['saleId' => $this->sale, 'size' => 'a4'],
            ['saleId' => $this->sale, 'size' => 58],
            ['saleId' => (string) $this->sale, 'size' => '58'],
            ['saleId' => $this->sale],
            ['saleId' => $this->sale, 'size' => '58', 'copy' => false],
        ] as $payload) {
            $this->postJson(self::PATH, $this->commandEnvelope($payload))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($records, $this->counts2(self::RECORDS));
    }

    public function test_the_print_view_renders_the_snapshot_in_each_size_with_the_copy_marker_of_a_copy_and_writes_nothing(): void
    {
        // AC-031-01, L2: 58 mm, 80 mm and A4 from one snapshot, by its template.
        $this->as('Rina');
        $receipt = $this->propsOf($this->get('/sales/'.$this->sale.'/receipt')->assertOk());
        $original = self::text($this->postJson(self::PATH, $this->commandEnvelope(['saleId' => $this->sale, 'size' => '58']))->assertStatus(201)->json('result.location'));
        $copy = self::text($this->postJson(self::PATH, $this->commandEnvelope(['saleId' => $this->sale, 'size' => 'A4']))->assertStatus(201)->json('result.location'));
        $records = $this->counts2([...self::FACTS, ...self::RECORDS]);

        foreach ([[$original, '58', false], [str_replace('?size=a4', '?size=80', $copy), '80', true], [$copy, 'a4', true]] as [$location, $size, $isCopy]) {
            $page = $this->get($location)->assertOk();
            $this->assertSame('no-store, private', $page->headers->get('Cache-Control'));
            $this->assertSame('documents/ReceiptPrint', $this->inertiaPage($page)['component'] ?? null);
            $this->assertPageChannel($page, 'cashier', $this->secrets());
            $props = $this->propsOf($page);
            $this->assertSame([$receipt['receipt'] ?? null, $receipt['issuer'] ?? null, 'receipt.1', $size, $isCopy], [$props['receipt'] ?? null, $props['issuer'] ?? null, $props['template'] ?? null, $props['size'] ?? null, $props['copy'] ?? null], $location);
        }

        // A read writes nothing (TR-HTTP-02).
        $this->assertSame($records, $this->counts2([...self::FACTS, ...self::RECORDS]));

        // The Owner reads his own print requests of the Sale, never the cashier's; the size is required.
        $this->as('Hendra');
        $this->getJson($original)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $own = self::text($this->postJson(self::PATH, $this->commandEnvelope(['saleId' => $this->sale, 'size' => '80']))->assertStatus(201)->json('result.location'));
        $this->assertTrue($this->propsOf($this->get($own)->assertOk())['copy'] ?? null);
        $this->assertPageChannel($this->get($own)->assertOk(), 'owner', $this->secrets());

        $this->as('Rina');
        $path = (string) strtok($original, '?');
        $this->getJson($path)->assertStatus(422);
        $this->getJson($path.'?size=a5')->assertStatus(422);
        $this->getJson($original.'&copy=0')->assertStatus(422);
    }

    public function test_a_guessed_print_request_another_sale_or_another_users_is_not_found(): void
    {
        $this->as('Rina');
        $print = self::number($this->postJson(self::PATH, $this->commandEnvelope(['saleId' => $this->sale, 'size' => '58']))->assertStatus(201)->json('commandId'));
        $other = TokoContoh::sell('Rina', $this->session, $this->otherLines());

        foreach ([
            '/sales/'.$this->sale.'/receipt/print/999999?size=58',
            '/sales/'.$this->sale.'/receipt/print/abc?size=58',
            '/sales/'.$other.'/receipt/print/'.$print.'?size=58',
            '/sales/999999/receipt/print/'.$print.'?size=58',
        ] as $path) {
            $this->getJson($path)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        // Another cashier and Operations, also with the right keys (AC-031-05).
        $this->as('Dewi');
        $this->getJson('/sales/'.$this->sale.'/receipt/print/'.$print.'?size=58')->assertStatus(404);
        $this->as('Agus');
        $this->getJson('/sales/'.$this->sale.'/receipt/print/'.$print.'?size=58')->assertStatus(403);
    }

    public function test_after_a_profile_change_and_an_anonymization_a_reprint_shows_what_the_receipt_froze(): void
    {
        // AC-031-04; RACE-25 (the snapshot keeps its revision) and RACE-16 (the cashier's frozen name).
        $rina = $this->people['Rina']['id'];
        TokoContoh::closeSession($this->session);
        $this->as('Hendra');
        $before = $this->printProps($this->sale);
        $this->assertSame(['Toko Contoh', 'Rina'], [((array) ($before['issuer'] ?? []))['name'] ?? null, ((array) ($before['receipt'] ?? []))['cashier'] ?? null]);

        $this->ownerRuns(ChangeBusinessProfile::class, ['shopName' => 'Toko Baru', 'address' => 'Jalan Baru 1', 'contact' => null, 'email' => null, 'documentHeader' => null, 'documentFooter' => null, 'documentIdentity' => null, 'accentToken' => 'green', 'logoAction' => 'keep'], ['revisionId' => TokoContoh::shop()]);
        $this->ownerRuns(DeactivateAccount::class, ['userId' => $rina], ['userVersion' => $this->userVersion($rina)]);
        $this->ownerRuns(AnonymizeAccount::class, ['userId' => $rina, 'reason' => 'Permintaan karyawan'], ['userVersion' => $this->userVersion($rina)]);
        $this->assertNotSame('Rina', $this->watcher()->table('users')->where('id', $rina)->value('display_name'));

        $after = $this->printProps($this->sale);
        $this->assertSame([$before['receipt'] ?? null, $before['issuer'] ?? null], [$after['receipt'] ?? null, $after['issuer'] ?? null]);
        $this->assertTrue($after['copy'] ?? null);
    }

    public function test_a_failure_after_every_write_step_leaves_no_record_and_the_sale_reprintable(): void
    {
        // TS-21: the audit event and the result; REQ-SALE-11 (AC-031-06): the Sale stays as it was, and the
        // next print request is still the original.
        $this->as('Rina');
        $facts = $this->counts2(self::FACTS);

        foreach ([1, 2] as $step) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $records = $this->counts2(self::RECORDS);

            $this->postJson(self::PATH, $this->commandEnvelope(['saleId' => $this->sale, 'size' => '58']))->assertStatus(500);

            $this->assertSame($records, $this->counts2(self::RECORDS), 'after write step '.$step);
            $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', 'document.print_receipt')->orderByDesc('id')->value('outcome'));
        }

        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->postJson(self::PATH, $this->commandEnvelope(['saleId' => $this->sale, 'size' => '58']))->assertStatus(201)->assertJsonPath('result.copy', false);
        $this->assertSame($facts, $this->counts2(self::FACTS));
    }

    /**
     * The props of a new print request's view of a Sale, for the viewer.
     *
     * @return array<array-key, mixed>
     */
    private function printProps(int $sale): array
    {
        $location = self::text($this->postJson(self::PATH, $this->commandEnvelope(['saleId' => $sale, 'size' => 'A4']))->assertStatus(201)->json('result.location'));

        return $this->propsOf($this->get($location)->assertOk());
    }

    /**
     * One line of a second product, with its own stock.
     *
     * @return list<array{productId: int, quantity: string}>
     */
    private function otherLines(): array
    {
        $product = TokoContoh::product('KRT-'.Str::upper(Str::random(6)), ['sellingPrice' => '3000', 'categoryId' => $this->category]);
        TokoContoh::stock($product, '5');

        return [['productId' => $product, 'quantity' => '1']];
    }

    /**
     * A command of the Owner through its Action, as the fixtures run them (EXECUTION_CONTEXT EXC-45).
     *
     * @param  class-string<CommandAction>  $action
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     */
    private function ownerRuns(string $action, array $payload, array $expected): void
    {
        $owner = User::query()->where('role_super_admin', true)->where('status', 'ACTIVE')->firstOrFail();
        $command = app($action);
        $envelope = new Envelope($command->operation(), Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, $expected, $payload);
        $command->execute(new CommandContext($owner->id, $envelope, app(Clock::class)->now()));
    }

    private function userVersion(int $user): int
    {
        return self::number($this->watcher()->table('users')->where('id', $user)->value('version'));
    }

    private function sessionVersion(): int
    {
        return self::number($this->watcher()->table('cash_sessions')->where('id', $this->session)->value('activity_version'));
    }

    private function saleNumber(): string
    {
        return self::text($this->watcher()->table('sales')->where('id', $this->sale)->value('sale_number'));
    }

    /**
     * An envelope of RC-44.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function commandEnvelope(array $payload): array
    {
        return [
            'operation' => 'document.print_receipt',
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
     * @return array<array-key, mixed>
     */
    private static function dataOf(mixed $json): array
    {
        $data = json_decode(is_string($json) ? $json : '', true);
        self::assertIsArray($data);
        ksort($data);

        return $data;
    }

    /**
     * Every person's password, which no page may carry.
     *
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
