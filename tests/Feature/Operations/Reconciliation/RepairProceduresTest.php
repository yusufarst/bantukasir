<?php

namespace Tests\Feature\Operations\Reconciliation;

use App\Database\CommandPoints;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;
use Tests\Harness\Race\InjectedFailure;
use Throwable;

/**
 * OP-07 `projection:rebuild` and OP-06 `costing:recover` (AC-036-04 to AC-036-06; ROUTE_CONTRACTS OP-06,
 * OP-07; SECURITY_MODEL SEC-OP-06, SEC-OP-07 and section 14; OPERATIONS_RUNBOOK OPR-02, RB-04 and RB-12;
 * SCHEMA section 5.8 and guard G-03; ADR-008 sections 4.3 and 5; EXECUTION_CONTEXT section 10, an operator
 * procedure; TASK-036 section 7, the L2 procedure tests; suites OPPROC01, FIN03): the rebuild of a product's
 * balance, valuation and allocation projections, of a Sale's running totals and of a token's active mapping
 * from facts that it never changes, with PROJECTION_REBUILT, after which the reconciliation is clean; its
 * refusal with no change on facts that fail validation; the recovery that publishes the Owner's correction
 * with the larger bound and clears the marks with RECOVERY_PROCEDURE, once per mark, or refuses with no
 * change; the common rules of both — the typed environment, the typed options, the note, the audit and the
 * security log; their failure after every write step.
 */
final class RepairProceduresTest extends DatabaseTestCase
{
    private const string NOTE = 'Permintaan tertulis Pemilik';

    private int $category = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->category = TokoContoh::category();
    }

    public function test_the_rebuild_of_a_product_restores_its_projections_from_facts_it_never_changes(): void
    {
        // AC-036-04; RB-04 steps 5 and 6.
        $product = TokoContoh::product('RP-01', ['categoryId' => $this->category]);
        TokoContoh::stock($product, '9', '10000');
        TokoContoh::issue([['productId' => $product, 'quantity' => '2']]);
        $expected = $this->projectionsOf($product);
        $facts = $this->factsOf($product);
        $this->corrupt($product);
        $this->assertNotSame($expected, $this->projectionsOf($product));

        $this->assertSame(0, $this->rebuild(['--product' => (string) $product]), Artisan::output());
        $this->assertStringContainsString('were rebuilt from its facts', Artisan::output());
        $this->assertSame(self::withoutPublications($expected), self::withoutPublications($this->projectionsOf($product)));
        $this->assertSame($facts, $this->factsOf($product), 'A rebuild never changes a fact.');

        $event = (array) $this->watcher()->table('audit_events')->where('action', 'PROJECTION_REBUILT')->sole();
        $this->assertSame(['OPERATOR_PROCEDURE', 'projection_rebuild', null, self::NOTE, 'product', $product, $product], [$event['actor_kind'], $event['actor_process'], $event['actor_user_id'], $event['operator_note'], $event['subject_type'], $event['subject_id'], $event['product_id']]);
        $this->assertStringContainsString('valueAfter', $this->readText($event['owner_only_data']));
        $this->assertStringNotContainsString('valueAfter', $this->readText($event['change_data']), 'Cost values are in the Owner-only data alone.');
        $this->assertSecurityEntry('projection_rebuild', 'SUCCEEDED');

        // The reconciliation of the product is then clean (RB-04 step 6); a second rebuild changes nothing.
        $this->assertSame(0, Artisan::call('reconciliation:run', ['--operator' => true, '--environment' => 'test', '--product' => (string) $product]), Artisan::output());
        $this->assertSame(0, $this->rebuild(['--product' => (string) $product]));
        $this->assertStringContainsString('nothing was changed', Artisan::output());
        $this->assertSame(1, $this->watcher()->table('audit_events')->where('action', 'PROJECTION_REBUILT')->count());
    }

    public function test_the_rebuild_of_a_sales_running_totals_and_of_a_tokens_active_mapping(): void
    {
        // AC-036-04; SEC-OP-07: the Sale's totals from its refund and return lines; the token's active row
        // from its mapping without a retirement.
        $product = TokoContoh::product('RP-02', ['categoryId' => $this->category]);
        TokoContoh::stock($product, '6', '9000');
        $mapping = TokoContoh::barcode($product, '8991234567891');
        $sale = TokoContoh::sell('Hendra', TokoContoh::openSession('Hendra', TokoContoh::register()), [['productId' => $product, 'quantity' => '3']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        TokoContoh::correct($sale, [['saleLineId' => $line, 'refundAmount' => '7500', 'returnedSaleable' => '1']], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-01']);
        $totals = fn (): array => [
            $this->watcher()->table('sale_correction_totals')->where('sale_id', $sale)->value('refunded_amount'),
            array_values((array) $this->watcher()->table('sale_line_correction_totals')->where('sale_line_id', $line)->first(['refunded_amount', 'returned_quantity'])),
        ];
        $before = $totals();
        $this->migration()->table('sale_correction_totals')->where('sale_id', $sale)->update(['refunded_amount' => 0]);
        $this->migration()->table('sale_line_correction_totals')->where('sale_line_id', $line)->update(['returned_quantity' => '0.000']);
        $token = $this->watcher()->table('barcode_mappings')->where('id', $mapping)->value('token_id');
        $this->assertIsInt($token);
        $this->migration()->table('barcode_active_mappings')->where('token_id', $token)->delete();
        $this->assertSame(1, Artisan::call('reconciliation:run'));

        $this->assertSame(0, $this->rebuild(['--sale' => (string) $sale]), Artisan::output());
        $this->assertSame($before, $totals());
        $this->assertSame(0, $this->rebuild(['--token' => (string) $token]), Artisan::output());
        $this->assertSame([$mapping, $product], array_values((array) $this->watcher()->table('barcode_active_mappings')->where('token_id', $token)->first(['mapping_id', 'product_id'])));
        $this->assertSame(['sale', 'token'], $this->watcher()->table('audit_events')->where('action', 'PROJECTION_REBUILT')->orderBy('id')->pluck('subject_type')->all());

        $this->assertSame(0, Artisan::call('reconciliation:run'), Artisan::output());
    }

    public function test_the_rebuild_refuses_with_no_change_when_the_facts_fail_validation(): void
    {
        // SEC-OP-07, RB-04 step 5: a refund that is not the sum of its lines; a history that cannot be
        // replayed as it is stored.
        $product = TokoContoh::product('RP-03', ['categoryId' => $this->category]);
        TokoContoh::stock($product, '9', '10000');
        TokoContoh::issue([['productId' => $product, 'quantity' => '2']]);
        $sale = TokoContoh::sell('Hendra', TokoContoh::openSession('Hendra', TokoContoh::register()), [['productId' => $product, 'quantity' => '3']]);
        TokoContoh::correct($sale, [['saleLineId' => TokoContoh::saleLinesOf($sale)[0] ?? 0, 'refundAmount' => '7500']], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-01']);
        $this->migration()->table('refunds')->where('sale_id', $sale)->update(['amount' => 7000]);
        $this->migration()->table('issue_cost_allocation_projections')->whereIn('allocation_id', $this->migration()->table('issue_cost_allocations')->where('product_id', $product)->where('issue_type', 'MANUAL_ISSUE')->select('id'))->update(['current_status' => 'CANCELLED', 'current_amount' => null]);
        $before = [$this->snapshot('sale_correction_totals', 'sale_id'), $this->projectionsOf($product)];

        $this->assertSame(1, $this->rebuild(['--sale' => (string) $sale]));
        $this->assertStringContainsString('not the sum of its lines', Artisan::output());
        $this->assertSame(1, $this->rebuild(['--product' => (string) $product]));
        $this->assertStringContainsString('do not form a valid history', Artisan::output());

        $this->assertSame($before, [$this->snapshot('sale_correction_totals', 'sale_id'), $this->projectionsOf($product)]);
        $this->assertSame(0, $this->watcher()->table('audit_events')->where('action', 'PROJECTION_REBUILT')->count());
        $this->assertSecurityEntry('projection_rebuild', 'REFUSED');
    }

    public function test_both_procedures_follow_the_common_rules_of_the_operator_procedures(): void
    {
        // AC-036-06; SECURITY_MODEL section 14; OPR-02; THR-10: the environment typed and equal to the
        // database's marker before anything is read; typed options only; the note required.
        $product = TokoContoh::product('RP-04', ['categoryId' => $this->category]);
        TokoContoh::stock($product, '1', '1000');
        $cases = [
            ['projection:rebuild', ['--product' => (string) $product, '--note' => self::NOTE]],
            ['projection:rebuild', ['--environment' => 'production', '--product' => (string) $product, '--note' => self::NOTE]],
            ['projection:rebuild', ['--environment' => 'test', '--product' => (string) $product]],
            ['projection:rebuild', ['--environment' => 'test', '--product' => (string) $product, '--sale' => '1', '--note' => self::NOTE]],
            ['projection:rebuild', ['--environment' => 'test', '--product' => '1 or 1=1', '--note' => self::NOTE]],
            ['projection:rebuild', ['--environment' => 'test', '--note' => self::NOTE]],
            ['costing:recover', ['--environment' => 'test', '--mark' => '1', '--evidence' => '1', '--amount' => '-5', '--reason' => 'Nota', '--note' => self::NOTE]],
            ['costing:recover', ['--environment' => 'test', '--mark' => 'x', '--evidence' => '1', '--amount' => '5', '--reason' => 'Nota', '--note' => self::NOTE]],
            ['costing:recover', ['--environment' => 'test', '--mark' => '1', '--evidence' => '1', '--amount' => '5', '--note' => self::NOTE]],
        ];

        foreach ($cases as [$command, $options]) {
            $this->assertSame(1, Artisan::call($command, $options), $command.' '.json_encode($options));
        }

        $this->assertSame(0, $this->watcher()->table('audit_events')->whereIn('action', ['PROJECTION_REBUILT', 'COST_CORRECTION'])->count());
    }

    public function test_the_recovery_publishes_the_owners_correction_with_the_larger_bound_once_per_mark(): void
    {
        // AC-036-05; SEC-OP-06, RB-12: a correction refused for the movement cap marks its source.
        [$product, $evidence, $mark] = $this->markedSource();
        $corrections = $this->watcher()->table('cost_corrections')->count();

        $this->assertSame(0, $this->recover($mark, $evidence, '20000'), Artisan::output());
        $correction = (array) $this->watcher()->table('cost_corrections')->where('evidence_id', $evidence)->sole();
        $hendra = TokoContoh::person('Hendra')['id'];
        $this->assertSame([1, '20000.000000', $hendra, 'Nota pemasok asli'], [$correction['correction_no'], $correction['new_amount'], $correction['actor_user_id'], $correction['reason']]);
        $identity = (array) $this->watcher()->table('command_identities')->where('id', $correction['command_id'])->sole();
        $this->assertSame(['cost_correction.confirm', 'OPERATOR_PROCEDURE', 'cost_recovery', null], [$identity['operation'], $identity['actor_kind'], $identity['actor_process'], $identity['actor_user_id']]);
        $clearance = (array) $this->watcher()->table('cost_incomplete_mark_clearances')->where('mark_id', $mark)->sole();
        $this->assertSame(['RECOVERY_PROCEDURE', 'OPERATOR_PROCEDURE', 'cost_recovery', $correction['command_id']], [$clearance['clearing_kind'], $clearance['actor_kind'], $clearance['actor_process'], $clearance['command_id']]);
        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $correction['command_id'])->sole();
        $this->assertSame(['COST_CORRECTION', 'OPERATOR_PROCEDURE', 'cost_recovery', self::NOTE, $product], [$event['action'], $event['actor_kind'], $event['actor_process'], $event['operator_note'], $event['product_id']]);
        $this->assertStringContainsString('20000', $this->readText($event['owner_only_data']));
        $this->assertSecurityEntry('cost_recovery', 'SUCCEEDED');

        // The replay was published: the product reconciles clean.
        $this->assertSame(0, Artisan::call('reconciliation:run', ['--operator' => true, '--environment' => 'test', '--product' => (string) $product]), Artisan::output());

        // A repeated run with the same mark returns the first result and writes nothing.
        $this->assertSame(0, $this->recover($mark, $evidence, '20000'));
        $this->assertStringContainsString('recovered by an earlier run', Artisan::output());
        $this->assertSame($corrections + 1, $this->watcher()->table('cost_corrections')->count());
        $this->assertSame(1, $this->watcher()->table('cost_incomplete_mark_clearances')->where('mark_id', $mark)->count());
    }

    public function test_the_recovery_refuses_with_no_change_and_never_forces_an_unsafe_history(): void
    {
        // AC-036-05; RB-12 step 2: another source than the mark's; a bound still too small; a mark of an
        // unsafe history; a product blocked by an incident.
        [$product, $evidence, $mark] = $this->markedSource();
        $before = [$this->snapshot('cost_corrections'), $this->snapshot('cost_incomplete_mark_clearances', 'mark_id'), $this->projectionsOf($product)];

        $this->assertSame(1, $this->recover($mark, $evidence + 1000, '20000'));
        $this->assertStringContainsString('another cost source', Artisan::output());

        config(['reconciliation.recovery_factor' => 1]);
        $this->assertSame(1, $this->recover($mark, $evidence, '20000'));
        $this->assertStringContainsString('larger bound was refused (MOVEMENT_CAP)', Artisan::output());
        config(['reconciliation.recovery_factor' => 10]);

        $unsafe = $this->migration()->table('cost_incomplete_marks')->insertGetId([
            ...(array) $this->migration()->table('cost_incomplete_marks')->where('id', $mark)->first(['evidence_id', 'product_id', 'evidence_movement_id', 'rejected_operation', 'corrections_seen', 'recorded_at', 'actor_user_id', 'actor_display_name']),
            'rejected_source_intent_id' => (string) Str::uuid(),
            'rejection_code' => 'UNSAFE_HISTORY',
        ]);
        $this->assertSame(1, $this->recover($unsafe, $evidence, '20000'));
        $this->assertStringContainsString('unsafe history', Artisan::output());

        TokoContoh::postingBlock($product);
        $this->assertSame(1, $this->recover($mark, $evidence, '20000'));
        $this->assertStringContainsString('blocked by a reconciliation incident', Artisan::output());

        $this->assertSame($before, [$this->snapshot('cost_corrections'), $this->snapshot('cost_incomplete_mark_clearances', 'mark_id'), $this->projectionsOf($product)]);
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('actor_process', 'cost_recovery')->count());
    }

    public function test_a_failure_after_every_write_step_of_either_procedure_leaves_nothing(): void
    {
        // TS-21. OP-07 of a product whose balance and valuation differ writes 3 steps — the balance, the
        // valuation and projections, the audit event; OP-06 6 — the identity, the correction, the
        // valuation and projections, the clearances, the audit event, the result.
        $product = TokoContoh::product('RP-05', ['categoryId' => $this->category]);
        TokoContoh::stock($product, '9', '10000');
        $this->corrupt($product);
        $corrupted = $this->projectionsOf($product);
        [$marked, $evidence, $mark] = $this->markedSource();
        $recovered = [$this->snapshot('cost_corrections'), $this->snapshot('command_identities'), $this->projectionsOf($marked)];

        foreach ([[3, fn (): int => $this->rebuild(['--product' => (string) $product]), fn (): array => $this->projectionsOf($product), $corrupted], [6, fn (): int => $this->recover($mark, $evidence, '20000'), fn (): array => [$this->snapshot('cost_corrections'), $this->snapshot('command_identities'), $this->projectionsOf($marked)], $recovered]] as [$steps, $run, $state, $expected]) {
            for ($step = 1; $step <= $steps; $step++) {
                $this->app->instance(CommandPoints::class, new FailurePoints($step));

                try {
                    $run();
                    $this->fail('The injected failure did not happen at write step '.$step);
                } catch (InjectedFailure) {
                    $this->assertSame($expected, $state(), 'after write step '.$step);
                }
            }

            $this->app->instance(CommandPoints::class, new FailurePoints);
            $this->assertSame(0, $run(), Artisan::output());
        }
    }

    /**
     * A product of four logical movements from its receipt, whose correction the movement cap of two
     * refused: the product, the receipt's evidence and the mark.
     *
     * @return array{int, int, int}
     */
    private function markedSource(): array
    {
        $product = TokoContoh::product('RP-'.Str::random(6), ['categoryId' => $this->category]);
        $document = TokoContoh::stock($product, '10', '10000');

        TokoContoh::issue([['productId' => $product, 'quantity' => '1']]);
        TokoContoh::issue([['productId' => $product, 'quantity' => '1']]);
        TokoContoh::issue([['productId' => $product, 'quantity' => '1']]);

        $evidence = TokoContoh::evidenceOf(TokoContoh::movementOf($document));
        config(['costing.replay.movement_cap' => 2]);

        $refusal = null;

        try {
            TokoContoh::correctCost($evidence, '20000');
        } catch (Throwable $thrown) {
            $refusal = $thrown;
        }

        $this->assertInstanceOf(CommandRefused::class, $refusal, 'The correction passed the movement cap.');
        $this->assertSame(ErrorCode::ReplayRejected, $refusal->error);

        $mark = $this->watcher()->table('cost_incomplete_marks')->where('evidence_id', $evidence)->value('id');
        $this->assertIsInt($mark);

        return [$product, $evidence, $mark];
    }

    /**
     * Changes the product's balance, valuation and issue projections on the test database, directly, as
     * only the schema's owner can.
     */
    private function corrupt(int $product): void
    {
        $this->migration()->table('stock_balances')->where('product_id', $product)->update(['on_hand' => $this->migration()->raw('on_hand + 3')]);
        $this->migration()->table('inventory_valuations')->where('product_id', $product)->update(['value' => '1.000000']);
        $this->migration()->table('issue_cost_allocation_projections')->whereIn('allocation_id', $this->migration()->table('issue_cost_allocations')->where('product_id', $product)->select('id'))->update(['current_status' => 'UNKNOWN', 'current_amount' => null]);
    }

    /**
     * @param  array<string, string>  $options
     */
    private function rebuild(array $options): int
    {
        return Artisan::call('projection:rebuild', ['--environment' => 'test', '--note' => self::NOTE, ...$options]);
    }

    private function recover(int $mark, int $evidence, string $amount): int
    {
        return Artisan::call('costing:recover', ['--environment' => 'test', '--mark' => (string) $mark, '--evidence' => (string) $evidence, '--amount' => $amount, '--reason' => 'Nota pemasok asli', '--note' => self::NOTE]);
    }

    /**
     * The product's balance, valuation and issue and return projections.
     *
     * @return array<string, mixed>
     */
    private function projectionsOf(int $product): array
    {
        return [
            'balance' => array_values((array) $this->watcher()->table('stock_balances')->where('product_id', $product)->first(['on_hand', 'last_sequence'])),
            'valuation' => array_values((array) $this->watcher()->table('inventory_valuations')->where('product_id', $product)->first(['value_status', 'value', 'last_sequence', 'publication_no'])),
            'issues' => $this->watcher()->table('issue_cost_allocation_projections as p')->join('issue_cost_allocations as a', 'a.id', '=', 'p.allocation_id')->where('a.product_id', $product)->orderBy('p.allocation_id')->get(['p.allocation_id', 'p.current_status', 'p.current_amount', 'p.publication_no'])->map(static fn (object $row): array => array_values((array) $row))->all(),
        ];
    }

    /**
     * The projections without the publication numbers, which a rebuild moves on.
     *
     * @param  array<string, mixed>  $projections
     * @return array<string, mixed>
     */
    private static function withoutPublications(array $projections): array
    {
        $valuation = (array) ($projections['valuation'] ?? []);
        array_pop($valuation);
        $issues = array_map(static function (mixed $row): array {
            $values = (array) $row;
            array_pop($values);

            return $values;
        }, (array) ($projections['issues'] ?? []));

        return ['balance' => $projections['balance'] ?? null, 'valuation' => $valuation, 'issues' => $issues];
    }

    /**
     * The product's facts: its movements, cost evidence and issue allocations, as committed.
     *
     * @return array<int, mixed>
     */
    private function factsOf(int $product): array
    {
        return [
            $this->watcher()->table('stock_movements')->where('product_id', $product)->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
            $this->watcher()->table('acquisition_cost_evidence')->where('product_id', $product)->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
            $this->watcher()->table('issue_cost_allocations')->where('product_id', $product)->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
        ];
    }

    private function assertSecurityEntry(string $process, string $outcome): void
    {
        $entry = (array) $this->watcher()->table('security_log_entries')->where('actor_process', $process)->orderByDesc('id')->first();
        $this->assertSame(['OPERATOR_PROCEDURE_RUN', 'OPERATOR_PROCEDURE', $outcome], [$entry['event_type'] ?? null, $entry['actor_kind'] ?? null, $entry['outcome_code'] ?? null]);
    }

    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
