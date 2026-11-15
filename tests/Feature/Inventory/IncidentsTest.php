<?php

namespace Tests\Feature\Inventory;

use App\Actions\Commands\CommandContext;
use App\Actions\Inventory\ReopenPostings;
use App\Database\CommandPoints;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\Operations;
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
 * RP-414, RP-415, the open incidents of RD-03 and RC-16 `posting_block.reopen` (AC-036-03; ROUTE_CONTRACTS
 * RP-414, RP-415, RD-03 and RC-16; INTERACTION_CONTRACTS RC-16, sections 1.5 and 3.2; NAVIGATION_CONTRACTS
 * UXP-414, UXP-415; BR-INV-45, BR-INV-46; G-11; CAP-18; EXECUTION_CONTEXT section 10, a route, a read and a
 * command; suites INV01, AUTH01, AUTH02, AUTH03, CMD01; TASK-036 section 7, L4 RC-16 and the answers of
 * RP-414 and RP-415): the Owner's pages with only their allowlisted props, every other kind of account
 * refused; the list newest first with its filter and pages; one incident with what did not match; the
 * count of open incidents for the Owner alone; the reopening, with recent authentication, once, after
 * which the product posts again; its refusals, its failure after every write step and its repeat.
 */
final class IncidentsTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string LIST = '/stock/incidents';

    private int $pen = 0;

    private int $book = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $category = TokoContoh::category('Alat Tulis');
        $this->pen = TokoContoh::product('PEN-01', ['categoryId' => $category, 'name' => 'Pulpen Hitam']);
        $this->book = TokoContoh::product('BUKU-01', ['categoryId' => $category, 'name' => 'Buku Tulis']);
        TokoContoh::stock($this->pen, '5', '5000');
        TokoContoh::stock($this->book, '5', '5000');
    }

    public function test_only_the_owner_opens_the_pages_with_only_their_allowlisted_props_and_reads_the_count(): void
    {
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));
        $this->as('Hendra');
        $this->assertPageChannel($this->get(self::LIST)->assertOk(), 'owner', $secrets, 'empty');
        $this->assertSame(0, $this->getJson('/data/shell')->assertOk()->json('openIncidents'));

        $block = TokoContoh::postingBlock($this->pen, 'VALUATION_MISMATCH');
        $this->assertPageChannel($this->get(self::LIST)->assertOk(), 'owner', $secrets);
        $this->assertPageChannel($this->get(self::LIST.'/'.$block)->assertOk(), 'owner', $secrets);
        $this->assertSame(1, $this->getJson('/data/shell')->assertOk()->json('openIncidents'));

        // Every other role and the combined account: the pages refused, and no count (CAP-18).
        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);

            foreach ([self::LIST, self::LIST.'/'.$block] as $path) {
                $page = $this->get($path)->assertStatus(403)->viewData('page');
                $this->assertIsArray($page);
                $this->assertSame('shell/Error', $page['component'] ?? null, $person.' '.$path);
            }

            $this->assertNull($this->getJson('/data/shell')->assertOk()->json('openIncidents'), $person);
        }

        $this->browse(null);
        $this->get(self::LIST)->assertRedirect('/login');
    }

    public function test_the_list_and_the_incident_give_what_was_found_filtered_and_paged(): void
    {
        $this->as('Hendra');
        $first = TokoContoh::postingBlock($this->pen, 'BALANCE_MISMATCH');
        TokoContoh::reopenPostings($first);
        $second = TokoContoh::postingBlock($this->book, 'ISSUE_PROJECTION_MISMATCH');

        // Newest first; the state of each (UXI-301, UXI-302).
        $props = $this->propsOf($this->get(self::LIST)->assertOk());
        $rows = (array) (((array) ($props['incidents'] ?? []))['rows'] ?? []);
        $this->assertSame([[$second, 'BLOCKED', 'Buku Tulis'], [$first, 'REOPENED', 'Pulpen Hitam']], array_map(static fn (mixed $row): array => [((array) $row)['id'] ?? null, ((array) $row)['status'] ?? null, ((array) (((array) $row)['product'] ?? []))['name'] ?? null], $rows));
        $this->assertSame(['status' => null], $props['filters'] ?? null);

        $this->assertSame([$second], $this->idsOf(self::LIST.'?status=BLOCKED'));
        $this->assertSame([$first], $this->idsOf(self::LIST.'?status=REOPENED'));

        // One per page: the cursor leads to the next.
        $page = (array) ($this->propsOf($this->get(self::LIST.'?perPage=1')->assertOk())['incidents'] ?? []);
        $this->assertSame([$second], array_map(static fn (mixed $row): mixed => ((array) $row)['id'] ?? null, (array) ($page['rows'] ?? [])));
        $cursor = $page['nextCursor'] ?? null;
        $this->assertIsString($cursor);
        $this->assertSame([$first], $this->idsOf(self::LIST.'?perPage=1&after='.$cursor));

        // RP-415: what did not match and the blocked product; once reopened, when.
        $incident = (array) ($this->propsOf($this->get(self::LIST.'/'.$second)->assertOk())['incident'] ?? []);
        $this->assertSame(['ISSUE_PROJECTION_MISMATCH', 'BLOCKED', null, ['id' => $this->book, 'name' => 'Buku Tulis', 'sku' => 'BUKU-01']], [$incident['detailCode'] ?? null, $incident['status'] ?? null, $incident['reopenedAt'] ?? null, $incident['product'] ?? null]);
        $reopened = (array) ($this->propsOf($this->get(self::LIST.'/'.$first)->assertOk())['incident'] ?? []);
        $this->assertSame('REOPENED', $reopened['status'] ?? null);
        $this->assertIsString($reopened['reopenedAt'] ?? null);

        // Object scope and the allowlists: a guessed or malformed key is NOT_FOUND; any other parameter refused.
        $this->get(self::LIST.'/999999')->assertNotFound();
        $this->get(self::LIST.'/abc')->assertNotFound();

        foreach (['status=OPEN', 'before=x', 'after=%25%25', 'perPage=0', 'q=PEN'] as $query) {
            $this->getJson(self::LIST.'?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->getJson(self::LIST.'/'.$second.'?x=1')->assertStatus(422);
    }

    public function test_the_owner_reopens_a_block_once_with_recent_authentication_and_the_product_posts_again(): void
    {
        // AC-036-03; RC-16: without a recent password confirmation 423 and nothing run, with no receipt.
        $block = TokoContoh::postingBlock($this->pen);
        $this->as('Hendra');
        $reopen = $this->reopening($block, 'Fakta sudah diperiksa');
        $this->send($reopen)->assertStatus(423)->assertJsonPath('code', 'RECENT_AUTH_REQUIRED');
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', ReopenPostings::OPERATION)->count());

        $this->owner();
        $response = $this->send($reopen)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed', 'owner');
        $this->assertSame(['posting_block', $block, self::LIST.'/'.$block], [$response->json('result.type'), $response->json('result.id'), $response->json('result.location')]);
        $commandId = $response->json('commandId');
        $this->assertIsInt($commandId);
        $hendra = $this->people['Hendra']['id'];
        $this->assertSame([$hendra, 'Fakta sudah diperiksa', $commandId], array_values((array) $this->watcher()->table('inventory_posting_block_reopenings')->where('block_id', $block)->first(['actor_user_id', 'reason', 'command_id'])));
        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['POSTING_REOPEN', 'USER', $hendra, 'posting_block', $block, $this->pen], [$event['action'], $event['actor_kind'], $event['actor_user_id'], $event['subject_type'], $event['subject_id'], $event['product_id']]);

        // A repeat answers the original; a status check recovers it; a second reopening is refused.
        $this->send($reopen)->assertStatus(200)->assertJsonPath('repeated', true);
        $this->check($reopen)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.location', self::LIST.'/'.$block);
        $this->send($this->reopening($block))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->send($this->reopening(999999))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->assertSame(1, $this->watcher()->table('inventory_posting_block_reopenings')->count());

        // The product posts again.
        TokoContoh::stock($this->pen, '1');
        $this->assertSame('6.000', $this->watcher()->table('stock_balances')->where('product_id', $this->pen)->value('on_hand'));

        // The fields: a key that is none, a reason beyond its limit.
        foreach ([['blockId' => '1'], ['blockId' => 0], ['blockId' => $block, 'reason' => str_repeat('a', 1001)]] as $payload) {
            $this->send($this->envelope($payload, ['operation' => ReopenPostings::OPERATION]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }
    }

    public function test_the_owner_alone_reopens_and_the_guard_in_the_transaction_holds_too(): void
    {
        // AUTH03, SEC-AZ-04: CAP-18 is the Owner's alone, at the gate, with no receipt, and in the transaction.
        $block = TokoContoh::postingBlock($this->pen);

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->postJson('/user/confirm-password', ['password' => $this->people[$person]['password']])->assertStatus(201);
            $this->send($this->reopening($block))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
            $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('actor_user_id', $this->people[$person]['id'])->count(), $person);
        }

        foreach (['Agus', 'Dewi'] as $person) {
            $envelope = new Envelope(ReopenPostings::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, [], ['blockId' => $block, 'reason' => null]);

            try {
                app(ReopenPostings::class)->execute(new CommandContext($this->people[$person]['id'], $envelope, app(Clock::class)->now()));
                $this->fail($person.' passed the guard of RC-16.');
            } catch (ActorNotAllowed) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, $this->watcher()->table('inventory_posting_block_reopenings')->count());

        // The product stays blocked: its postings are refused (G-11).
        try {
            TokoContoh::stock($this->pen, '1');
            $this->fail('A posting of a blocked product was accepted.');
        } catch (CommandRefused $refusal) {
            $this->assertSame(ErrorCode::PostingBlocked, $refusal->error);
        }
    }

    public function test_a_failure_after_every_write_step_leaves_nothing_and_the_receipt_pending(): void
    {
        // TS-21. RC-16 writes 3 steps: the reopening, the audit event, the result.
        $block = TokoContoh::postingBlock($this->pen);
        $this->owner();
        $tables = ['inventory_posting_block_reopenings', 'command_results', 'audit_events'];
        $counts = fn (): array => array_map(fn (string $table): int => $this->watcher()->table($table)->count(), $tables);
        $before = $counts();

        for ($step = 1; $step <= 3; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $this->send($this->reopening($block))->assertStatus(500);
            $this->assertSame($before, $counts(), 'after write step '.$step);
            $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', ReopenPostings::OPERATION)->orderByDesc('id')->value('outcome'), (string) $step);
        }

        $points = new FailurePoints(4);
        $this->app->instance(CommandPoints::class, $points);
        $this->send($this->reopening($block))->assertStatus(201);
        $this->assertSame(['after_lock:2.1', 'after_lock:6', 'after_write:1', 'after_write:2', 'after_write:3'], array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))));
    }

    /** The Owner, with the recent authentication of RC-16 (INTERACTION_CONTRACTS section 1.5). */
    private function owner(): void
    {
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
    }

    /**
     * An envelope of RC-16 for this block, under a new intent and key.
     *
     * @return array<string, mixed>
     */
    private function reopening(int $block, ?string $reason = null): array
    {
        return $this->envelope(['blockId' => $block, 'reason' => $reason], ['operation' => ReopenPostings::OPERATION]);
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function send(array $envelope): TestResponse
    {
        $operation = $envelope['operation'] ?? null;

        return $this->postJson('/commands/'.(is_string($operation) ? $operation : ''), $envelope);
    }

    /**
     * The keys of the incidents of a page of RP-414.
     *
     * @return list<mixed>
     */
    private function idsOf(string $path): array
    {
        $incidents = (array) ($this->propsOf($this->get($path)->assertOk())['incidents'] ?? []);

        return array_values(array_map(static fn (mixed $row): mixed => ((array) $row)['id'] ?? null, (array) ($incidents['rows'] ?? [])));
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
