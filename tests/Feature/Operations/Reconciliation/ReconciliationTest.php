<?php

namespace Tests\Feature\Operations\Reconciliation;

use App\Actions\Operations\BlockPostings;
use App\Database\CommandPoints;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Operations\Heartbeat;
use App\Domain\Operations\Reconciliation\BlockOutcome;
use FilesystemIterator;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;
use Tests\Harness\Race\InjectedFailure;
use Throwable;

/**
 * SC-01 `reconciliation:run` and its `posting_block.block` (AC-036-01, AC-036-06, AC-036-07; ROUTE_CONTRACTS
 * SC-01; INTERACTION_CONTRACTS section 7; SCHEMA guard G-03, G-11 and sections 5.7 and 5.12; BR-INV-45,
 * BR-INV-46; ADR-008 section 5; SECURITY_MODEL section 14; EXECUTION_CONTEXT section 10, a scheduled
 * command and a command; TASK-036 section 7, the L2 reconciliation family; suites INV01, OPPROC01, FIN03):
 * a shop whose facts and projections agree reconciles clean; each mismatch induced on the test database —
 * a balance, a valuation, an issue projection, a return projection — blocks its product, whose postings are
 * then refused POSTING_BLOCKED while other products post; a valid UNKNOWN cost is no mismatch; a running
 * total or an active mapping that differs is reported, never blocked; one row per run, and the heartbeat
 * pinged only after a completed run of every product; a repeated run blocks once; the operator's run and
 * its typed options; the block's failure after every write step leaves nothing.
 */
final class ReconciliationTest extends DatabaseTestCase
{
    private const string PING_ADDRESS = 'https://heartbeat.test/ping/reconciliation';

    private int $category = 0;

    /** @var list<string> the addresses pinged */
    private array $pings = [];

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->category = TokoContoh::category();
        config(['heartbeat.checks.reconciliation' => self::PING_ADDRESS]);
        $this->app->instance(Heartbeat::class, new Heartbeat(new Client(['handler' => function (RequestInterface $request) {
            $this->pings[] = (string) $request->getUri();

            return Create::promiseFor(new Response(200));
        }])));
    }

    public function test_a_shop_whose_facts_and_projections_agree_reconciles_clean_and_pings_after_the_run(): void
    {
        // AC-036-07; ADR-008 section 5: receipts, an UNKNOWN cost, a manual issue, a Sale with a refund and a
        // saleable return, and a barcode — every projection equals its facts.
        $this->shopWithHistory();

        $this->assertSame(0, Artisan::call('reconciliation:run'), Artisan::output());
        $this->assertStringContainsString('RECONCILIATION_CLEAN', Artisan::output());

        $run = (array) $this->watcher()->table('reconciliation_runs')->sole();
        $this->assertSame(['CLEAN', 5, 0, 0, 'ALL', 'SCHEDULE'], [$run['outcome'], $run['products_checked'], $run['blocks_written'], $run['other_differences'], $run['scope'], $run['run_trigger']]);
        $this->assertSame([self::PING_ADDRESS], $this->pings);
        $this->assertSame(0, $this->watcher()->table('inventory_posting_blocks')->count());

        // SECURITY_MODEL section 14: the run's security log entry names the scheduled command.
        $entry = (array) $this->watcher()->table('security_log_entries')->orderByDesc('id')->first();
        $this->assertSame(['OPERATOR_PROCEDURE_RUN', 'SCHEDULED_COMMAND', 'reconciliation', 'SUCCEEDED'], [$entry['event_type'], $entry['actor_kind'], $entry['actor_process'], $entry['outcome_code']]);
    }

    public function test_each_induced_mismatch_blocks_its_product_whose_postings_are_refused_while_others_post(): void
    {
        // AC-036-01; G-03, G-11. The test database's projections are changed directly by the schema's
        // owner, as no role of the application can: on staging the operator does the same (UAT-20).
        [$balance, $valuation, $issue, $return, $clean] = $this->shopWithHistory();
        $this->migration()->table('stock_balances')->where('product_id', $balance)->update(['on_hand' => $this->migration()->raw('on_hand + 1')]);
        $this->migration()->table('inventory_valuations')->where('product_id', $valuation)->update(['value' => $this->migration()->raw('value + 1')]);
        $this->migration()->table('issue_cost_allocation_projections')->whereIn('allocation_id', $this->migration()->table('issue_cost_allocations')->where('product_id', $issue)->where('issue_type', 'MANUAL_ISSUE')->select('id'))->update(['current_amount' => '1.000000']);
        $this->migration()->table('return_cost_allocation_projections')->whereIn('allocation_id', $this->migration()->table('return_cost_allocations')->where('product_id', $return)->select('id'))->update(['current_amount' => '9.000000']);
        $before = $this->facts();

        $this->assertSame(1, Artisan::call('reconciliation:run'), Artisan::output());
        $this->assertStringContainsString('RECONCILIATION_MISMATCH', Artisan::output());

        $run = (array) $this->watcher()->table('reconciliation_runs')->sole();
        $this->assertSame(['MISMATCH', 5, 4, 0], [$run['outcome'], $run['products_checked'], $run['blocks_written'], $run['other_differences']]);
        $this->assertSame([self::PING_ADDRESS], $this->pings, 'A completed run pings, with or without a mismatch.');

        $blocks = $this->watcher()->table('inventory_posting_blocks')->orderBy('product_id')->get(['id', 'product_id', 'detail_code', 'actor_kind', 'actor_process', 'actor_user_id', 'command_id'])->map(static fn (object $row): array => (array) $row)->all();
        $this->assertSame(
            [[$balance, 'BALANCE_MISMATCH'], [$valuation, 'VALUATION_MISMATCH'], [$issue, 'ISSUE_PROJECTION_MISMATCH'], [$return, 'RETURN_PROJECTION_MISMATCH']],
            array_map(static fn (array $block): array => [$block['product_id'], $block['detail_code']], $blocks),
        );

        foreach ($blocks as $block) {
            $this->assertSame(['SCHEDULED_COMMAND', 'reconciliation', null], [$block['actor_kind'], $block['actor_process'], $block['actor_user_id']]);
            $identity = (array) $this->watcher()->table('command_identities')->where('id', $block['command_id'])->sole();
            $this->assertSame(['posting_block.block', 'SCHEDULED_COMMAND', 'reconciliation'], [$identity['operation'], $identity['actor_kind'], $identity['actor_process']]);
            $event = (array) $this->watcher()->table('audit_events')->where('command_id', $block['command_id'])->sole();
            $this->assertSame(['RECONCILIATION_BLOCK', 'SCHEDULED_COMMAND', 'reconciliation', 'posting_block', $block['id'], $block['product_id']], [$event['action'], $event['actor_kind'], $event['actor_process'], $event['subject_type'], $event['subject_id'], $event['product_id']]);
            $this->assertSame(['posting_block', $block['id']], array_values((array) $this->watcher()->table('command_results')->where('command_id', $block['command_id'])->first(['result_type', 'result_ref'])));
        }

        // Never a fact changed: the block, its identity, result and audit event are added, nothing else.
        $this->assertSame($before['movements'], $this->facts()['movements']);

        // The blocked product's postings are refused POSTING_BLOCKED; the clean one posts.
        $refusal = null;

        try {
            TokoContoh::stock($balance, '1');
        } catch (Throwable $thrown) {
            $refusal = $thrown;
        }

        $this->assertInstanceOf(CommandRefused::class, $refusal, 'A posting of a blocked product was accepted.');
        $this->assertSame(ErrorCode::PostingBlocked, $refusal->error);

        TokoContoh::stock($clean, '1');

        // A repeated run blocks once: the products stay blocked, and no second block is written.
        $this->assertSame(1, Artisan::call('reconciliation:run'));
        $this->assertSame(4, $this->watcher()->table('inventory_posting_blocks')->count());
        $second = (array) $this->watcher()->table('reconciliation_runs')->orderByDesc('id')->first();
        $this->assertSame(['MISMATCH', 0], [$second['outcome'], $second['blocks_written']]);
    }

    public function test_a_running_total_or_an_active_mapping_that_differs_is_an_incident_reported_never_blocked(): void
    {
        // G-03, BR-INV-46: the Sale's running total and a token's active row, changed on the test database.
        $this->shopWithHistory();
        $sale = $this->int($this->watcher()->table('refunds')->value('sale_id'));
        $line = $this->int($this->watcher()->table('sale_line_correction_totals')->orderBy('sale_line_id')->value('sale_line_id'));
        $token = $this->int($this->watcher()->table('barcode_active_mappings')->value('token_id'));
        $this->migration()->table('sale_line_correction_totals')->where('sale_line_id', $line)->update(['returned_quantity' => '0.000']);
        $this->migration()->table('barcode_active_mappings')->where('token_id', $token)->delete();

        $this->assertSame(1, Artisan::call('reconciliation:run'));
        $output = Artisan::output();
        $this->assertStringContainsString('RECONCILIATION_DIFFERENCES sales='.$sale.' tokens='.$token, $output);

        $run = (array) $this->watcher()->table('reconciliation_runs')->sole();
        $this->assertSame(['MISMATCH', 0, 2], [$run['outcome'], $run['blocks_written'], $run['other_differences']]);
        $this->assertSame(0, $this->watcher()->table('inventory_posting_blocks')->count());
    }

    public function test_the_operators_run_for_one_product_and_the_typed_options(): void
    {
        // OPERATIONS_RUNBOOK section 3 and RB-04 step 6; OPR-02; THR-10.
        [$balance] = $this->shopWithHistory();

        $this->assertSame(0, Artisan::call('reconciliation:run', ['--operator' => true, '--environment' => 'test', '--product' => (string) $balance]), Artisan::output());
        $run = (array) $this->watcher()->table('reconciliation_runs')->sole();
        $this->assertSame(['CLEAN', 1, 'PRODUCT', 'OPERATOR'], [$run['outcome'], $run['products_checked'], $run['scope'], $run['run_trigger']]);
        $this->assertSame([], $this->pings, 'A run for one product is no daily run: it pings nothing.');

        // Refused before anything runs, with no row: the environment missing or another; a product without
        // --operator; a key that is none; a product that does not exist.
        foreach ([
            ['--operator' => true, '--product' => (string) $balance],
            ['--operator' => true, '--environment' => 'production'],
            ['--product' => (string) $balance],
            ['--environment' => 'test'],
            ['--operator' => true, '--environment' => 'test', '--product' => '1;2'],
            ['--operator' => true, '--environment' => 'test', '--product' => '999999'],
        ] as $options) {
            $this->assertSame(1, Artisan::call('reconciliation:run', $options), (string) json_encode($options));
            $this->assertStringContainsString('RECONCILIATION_REFUSED', Artisan::output());
        }

        $this->assertSame(1, $this->watcher()->table('reconciliation_runs')->count());
    }

    public function test_a_run_that_does_not_complete_is_recorded_failed_and_never_pings(): void
    {
        // AC-036-07: the budget passed at the first product.
        $this->shopWithHistory();
        config(['reconciliation.run_budget' => 0]);

        $this->assertSame(1, Artisan::call('reconciliation:run'));
        $this->assertStringContainsString('RECONCILIATION_FAILED', Artisan::output());
        $this->assertSame('FAILED', $this->watcher()->table('reconciliation_runs')->value('outcome'));
        $this->assertSame([], $this->pings);
        $this->assertSame('REFUSED', $this->watcher()->table('security_log_entries')->orderByDesc('id')->value('outcome_code'));
    }

    public function test_the_block_writes_once_per_run_and_product_and_a_failure_after_each_write_step_leaves_nothing(): void
    {
        // TS-21; SCHEMA section 5.3: a deterministic intent per run and product.
        [$balance] = $this->shopWithHistory();
        $this->migration()->table('stock_balances')->where('product_id', $balance)->update(['on_hand' => $this->migration()->raw('on_hand + 1')]);
        $run = (string) Str::uuid();
        $tables = ['inventory_posting_blocks', 'command_identities', 'command_results', 'audit_events'];
        $counts = fn (): array => array_map(fn (string $table): int => $this->watcher()->table($table)->count(), $tables);
        $before = $counts();

        for ($step = 1; $step <= 3; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));

            try {
                app(BlockPostings::class)($run, $balance);
                $this->fail('The injected failure did not happen at write step '.$step);
            } catch (InjectedFailure) {
                $this->assertSame($before, $counts(), 'after write step '.$step);
            }
        }

        $points = new FailurePoints;
        $this->app->instance(CommandPoints::class, $points);
        $this->assertSame(BlockOutcome::Written, app(BlockPostings::class)($run, $balance));
        $this->assertSame(['after_identity', 'after_lock:6', 'after_write:1', 'after_write:2', 'after_write:3', 'before_commit'], $points->passed);

        // The same run and product again, or a later run: the product is blocked once.
        $this->assertSame(BlockOutcome::AlreadyBlocked, app(BlockPostings::class)($run, $balance));
        $this->assertSame(BlockOutcome::AlreadyBlocked, app(BlockPostings::class)((string) Str::uuid(), $balance));
        $this->assertSame(1, $this->watcher()->table('inventory_posting_blocks')->count());

        // A product whose facts and projections agree under its guard gets no block.
        $this->assertSame(BlockOutcome::Agrees, app(BlockPostings::class)($run, TokoContoh::product('RK-99', ['categoryId' => $this->category])));
    }

    public function test_only_the_inventory_module_writes_the_blocks_and_the_operations_module_its_runs(): void
    {
        // STK-12: one writer per table.
        $this->assertSame(['Domain/Inventory/PostingBlocks.php', 'Queries/Inventory/Incidents.php'], self::filesNaming('inventory_posting_blocks'));
        $this->assertSame(['Domain/Operations/Reconciliation/ReconciliationRuns.php'], self::filesNaming('reconciliation_runs'));
    }

    /**
     * Five products with their histories: received; received and issued by hand; sold, refunded and
     * returned; received at an UNKNOWN cost; with a barcode. The first four are those whose projections
     * the mismatch test changes, in that order, and the last stays clean.
     *
     * @return list<int>
     */
    private function shopWithHistory(): array
    {
        $balance = TokoContoh::product('RK-01', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam']);
        $valuation = TokoContoh::product('RK-02', ['categoryId' => $this->category, 'name' => 'Pulpen Biru']);
        $issue = TokoContoh::product('RK-03', ['categoryId' => $this->category, 'name' => 'Buku Tulis']);
        $return = TokoContoh::product('RK-04', ['categoryId' => $this->category, 'name' => 'Penghapus']);
        $clean = TokoContoh::product('RK-05', ['categoryId' => $this->category, 'name' => 'Penggaris']);

        TokoContoh::stock($balance, '10', '10000');
        TokoContoh::stock($valuation, '7', '7000');
        TokoContoh::stock($issue, '9', '10000');
        TokoContoh::issue([['productId' => $issue, 'quantity' => '2']]);
        TokoContoh::stock($return, '6', '9000');
        $sale = TokoContoh::sell('Hendra', TokoContoh::openSession('Hendra', TokoContoh::register()), [['productId' => $return, 'quantity' => '3']]);
        TokoContoh::correct($sale, [['saleLineId' => TokoContoh::saleLinesOf($sale)[0] ?? 0, 'refundAmount' => '7500', 'returnedSaleable' => '1']], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-01']);
        // A valid UNKNOWN cost is no mismatch (BR-INV-45).
        TokoContoh::receive([['productId' => $clean, 'quantity' => '4', 'cost' => ['status' => 'UNKNOWN']]]);
        TokoContoh::barcode($clean, '8991234567891');

        return [$balance, $valuation, $issue, $return, $clean];
    }

    /**
     * @return array{movements: int}
     */
    private function facts(): array
    {
        return ['movements' => $this->watcher()->table('stock_movements')->count()];
    }

    private function int(mixed $value): int
    {
        $this->assertIsInt($value);

        return $value;
    }

    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    /**
     * The files of app/ that name this table in a string, as the query builder takes it.
     *
     * @return list<string>
     */
    private static function filesNaming(string $table): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match('/[\'"]'.preg_quote($table, '/').'(?: as [a-z]+)?[\'"]/', (string) file_get_contents($file->getPathname())) === 1) {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen(app_path()) + 1));
            }
        }

        sort($files);

        return $files;
    }
}
