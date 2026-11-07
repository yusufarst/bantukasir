<?php

namespace Tests\Feature\Inventory;

use App\Actions\Commands\CommandContext;
use App\Actions\Inventory\ConfirmReversal;
use App\Database\CommandPoints;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Operations;
use App\Domain\Inventory\ReversalSource;
use App\Support\Numbers\Quantity;
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
 * RC-10 `reversal.confirm` through its route, and the replay it runs (AC-026-01 to AC-026-04;
 * INTERACTION_CONTRACTS RC-10 and section 1.5; TECHNICAL_RULES sections 7.2 and 9, TR-TIME-01; ADR-008
 * sections 4.1 and 4.3; SCHEMA sections 5.7 and 5.8; BR-INV-34 to BR-INV-37; BR-COST-27, BR-COST-32,
 * BR-COST-33; G-07, G-11, G-21; CAP-17; EXECUTION_CONTEXT section 10, a command; suites INV03, FIN03,
 * CMD01, AUTH03; TASK-026 section 7, L2 and L4): the fixture of COSTING section 8, with a RECEIPT for
 * its OPENING until TASK-035; a manual issue reversed, its allocation CANCELLED; the two examples of
 * ADR-008 section 4.1, a zero point created and one removed; the invalid history and the stock now,
 * refused with nothing written and no mark; a source reversed once, its type eligible, onboarding and
 * a block; the source's marks cleared in the reversal's transaction, and a later refused correction's
 * mark cleared at once; the failure after every write step; the roles, recent authentication and the
 * fields.
 */
final class ConfirmReversalTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string COMMAND = '/commands/reversal.confirm';

    /** The tables that a refused or failed reversal leaves as they were, beside the rows compared. */
    private const array FACTS = ['reversals', 'stock_movements', 'cost_incomplete_marks', 'cost_incomplete_mark_clearances', 'issue_cost_allocations', 'command_identities', 'command_results', 'audit_events'];

    private int $category = 0;

    private int $pen = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category();
        $this->pen = TokoContoh::product('PEN-01', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam']);
    }

    public function test_the_fixture_of_costing_section_8_gives_quantity_5_value_50_and_issue_cost_50(): void
    {
        // AC-026-01, AC-026-02; COSTING section 8 with a RECEIPT standing in for its OPENING: 10 at 100,
        // 10 at 200, 5 out (75): 15 worth 225. Reversing the second receipt gives 5 worth 50, and the
        // issue's projection 50 while its fact stays 75 (D-060; ADR-008).
        TokoContoh::stock($this->pen, '10', '100');
        $source = TokoContoh::movementOf(TokoContoh::stock($this->pen, '10', '200'));
        TokoContoh::issue([['productId' => $this->pen, 'quantity' => '5']]);
        $this->assertSame('KNOWN|225.000000|3|3', $this->valuationOf($this->pen));
        $this->owner();
        $hendra = $this->people['Hendra']['id'];
        $envelope = $this->envelopeOf($source);

        $response = $this->reverse($envelope)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed', 'owner');
        $reversal = self::number($response->json('result.id'));
        $commandId = self::number($response->json('commandId'));
        $movement = self::number($this->watcher()->table('stock_movements')->where('reversal_id', $reversal)->value('id'));
        $this->assertSame(['reversal', '/stock/movements/'.$movement], [$response->json('result.type'), $response->json('result.location')]);

        // The reversal and its REVERSAL, the exact inverse, after the issue; the source stays.
        $row = (array) $this->watcher()->table('reversals')->where('id', $reversal)->sole();
        $this->assertSame([$source, $this->pen, 'RECEIPT', '10.000', '-10.000', 'Salah catat', $hendra, 'Hendra', $commandId], [$row['original_movement_id'], $row['product_id'], $row['original_type'], $row['original_quantity'], $row['inverse_quantity'], $row['reason'], $row['actor_user_id'], $row['actor_display_name'], $row['command_id']]);
        $this->assertSame(['REVERSAL', '-10.000', 4, null], array_values((array) $this->watcher()->table('stock_movements')->where('id', $movement)->first(['movement_type', 'quantity', 'product_sequence', 'line_quantity'])));
        $this->assertSame(4, $this->watcher()->table('stock_movements')->where('product_id', $this->pen)->count());
        $this->assertSame(['5.000', 4], $this->balanceOf($this->pen));

        // The valuation and the projection follow the logical ledger; the fact stays (BR-COST-32).
        $this->assertSame('KNOWN|50.000000|4|4', $this->valuationOf($this->pen));
        $this->assertSame([['MANUAL_ISSUE', 'KNOWN', '75.000000', 'KNOWN', '50.000000', 4]], $this->allocationsOf($this->pen));

        // One audit event, with the reason in its own column (BR-AUD-02).
        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['BUSINESS', 'REVERSAL_CONFIRM', $hendra, 'reversal', $reversal, $this->pen, 'Salah catat'], [$event['category'], $event['action'], $event['actor_user_id'], $event['subject_type'], $event['subject_id'], $event['product_id'], $event['reason']]);
        $this->assertSame(['inverseQuantity' => '-10', 'movementId' => $movement, 'originalMovementId' => $source, 'originalQuantity' => '10', 'originalType' => 'RECEIPT', 'productSequence' => 4], self::dataOf($event['change_data']));
        $this->assertSame(['marksCleared' => 0, 'projectionsRewritten' => 1, 'publicationNo' => 4], self::dataOf($event['owner_only_data']));

        // The result holds references only; a repeat answers it and writes nothing; a status check recovers it.
        $stored = (array) $this->watcher()->table('command_results')->where('command_id', $commandId)->sole();
        $this->assertSame(['reversal', $reversal], [$stored['result_type'], $stored['result_ref']]);
        $facts = $this->factsAndRows();
        $this->reverse($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $reversal);
        $this->reverse([...$envelope, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $reversal);
        $this->assertSame($facts, $this->factsAndRows());
        $resolved = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.location', '/stock/movements/'.$movement);
        $this->assertJsonChannel($resolved, 'commands.resolve', 'owner');

        // AC-026-01, BR-INV-35: a second reversal of the source, another intent, is refused with nothing written.
        $this->reverse($this->envelopeOf($source))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->assertSame($facts, $this->factsAndRows());
    }

    public function test_the_invalid_history_and_the_stock_now_are_refused_with_no_movement_and_no_mark(): void
    {
        // AC-026-02; BR-INV-37; COSTING section 8, the invalid history: 10 in, 10 out, 20 in, 20 on
        // hand; reversing the first receipt would leave the issue without stock at its point: refused
        // REPLAY_REJECTED before the commit, with nothing written and no mark (TR-TIME-01).
        $first = TokoContoh::movementOf(TokoContoh::stock($this->pen, '10', '100'));
        TokoContoh::issue([['productId' => $this->pen, 'quantity' => '10']]);
        TokoContoh::stock($this->pen, '20', '400');
        $this->owner();
        $before = $this->factsAndRows();

        $refused = $this->reverse($this->envelopeOf($first))->assertStatus(422)->assertJsonPath('code', 'REPLAY_REJECTED')->assertJsonPath('messageKey', 'ux.state.replay_rejected.reversal');
        $this->assertJsonChannel($refused, 'commands.refused', 'owner');
        $this->assertSame($before, $this->factsAndRows());

        // BR-INV-37, stock now: 5 of 20 out since; reversing the 20 would leave −5.
        $second = TokoContoh::movementOf(TokoContoh::stock($this->pen, '5', '50'));
        TokoContoh::issue([['productId' => $this->pen, 'quantity' => '25']]);
        $before = $this->factsAndRows();
        $this->reverse($this->envelopeOf($second))->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_STOCK')->assertJsonPath('messageKey', 'ux.state.insufficient_stock.reversal');
        $this->assertSame($before, $this->factsAndRows());

        // The movement cap refuses REPLAY_REJECTED too, with no mark (TR-TIME-01).
        $third = TokoContoh::movementOf(TokoContoh::stock($this->pen, '3', '30'));
        TokoContoh::stock($this->pen, '1', '10');
        config(['costing.replay.movement_cap' => 0]);
        $before = $this->factsAndRows();
        $this->reverse($this->envelopeOf($third))->assertStatus(422)->assertJsonPath('code', 'REPLAY_REJECTED');
        $this->assertSame($before, $this->factsAndRows());
        $this->assertSame(0, $this->watcher()->table('cost_incomplete_marks')->count());
    }

    public function test_a_manual_issue_reversed_is_cancelled_and_reduces_no_value(): void
    {
        // BR-COST-32, ADR-008 section 2: 10 at 100, 4 out (40), 10 at 300: 16 worth 360. Reversing the
        // issue gives 20 worth 400; its projection CANCELLED with no amount, its fact 40.
        TokoContoh::stock($this->pen, '10', '100');
        $issue = TokoContoh::movementOf(TokoContoh::issue([['productId' => $this->pen, 'quantity' => '4']]), 'MANUAL_ISSUE');
        TokoContoh::stock($this->pen, '10', '300');
        $this->assertSame('KNOWN|360.000000|3|3', $this->valuationOf($this->pen));

        TokoContoh::reverse($issue, 'Barang ditemukan');

        $this->assertSame('KNOWN|400.000000|4|4', $this->valuationOf($this->pen));
        $this->assertSame(['20.000', 4], $this->balanceOf($this->pen));
        $this->assertSame([['MANUAL_ISSUE', 'KNOWN', '40.000000', 'CANCELLED', null, 4]], $this->allocationsOf($this->pen));
        $this->assertSame(['MANUAL_ISSUE', '-4.000', '4.000'], array_values((array) $this->watcher()->table('reversals')->first(['original_type', 'original_quantity', 'inverse_quantity'])));

        // A later posting appends to the logical ledger: 5 of 20 worth 400 take 100.
        TokoContoh::issue([['productId' => $this->pen, 'quantity' => '5']]);
        $this->assertSame(['CANCELLED', 'KNOWN'], array_column($this->allocationsOf($this->pen), 3));
        $this->assertSame('KNOWN|300.000000|5|5', $this->valuationOf($this->pen));
    }

    public function test_the_examples_of_adr_008_section_4_1_a_zero_point_created_and_one_removed(): void
    {
        // ADR-008 section 4.1, with manual issues for its Sales until TASK-028. Reversing an increase
        // creates a zero point: A 10 UNKNOWN, B 5 at 50.000, 10 out (UNKNOWN), C 10 at 100.000, 5 out
        // (UNKNOWN); reversing B makes the 10 take Q to zero, and the 5 lie in C's known pool: 50.000.
        $created = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis']);
        TokoContoh::receive([['productId' => $created, 'quantity' => '10', 'cost' => ['status' => 'UNKNOWN']]]);
        $b = TokoContoh::movementOf(TokoContoh::stock($created, '5', '50000'));
        TokoContoh::issue([['productId' => $created, 'quantity' => '10']]);
        TokoContoh::stock($created, '10', '100000');
        TokoContoh::issue([['productId' => $created, 'quantity' => '5']]);
        $this->assertSame(['UNKNOWN', 'UNKNOWN'], array_column($this->allocationsOf($created), 3));

        TokoContoh::reverse($b);

        // Only the projection that changed is rewritten, with the reversal's publication (ADR-008 section 4.3).
        $this->assertSame([['MANUAL_ISSUE', 'UNKNOWN', null, 'UNKNOWN', null, 3], ['MANUAL_ISSUE', 'UNKNOWN', null, 'KNOWN', '50000.000000', 6]], $this->allocationsOf($created));
        $this->assertSame('KNOWN|50000.000000|6|6', $this->valuationOf($created));

        // Reversing a manual issue removes a zero point and merges two pools: A 10 UNKNOWN, 10 out (the
        // pool resets), B 10 at 100.000, 4 out (40.000); reversing the first issue makes the 4 UNKNOWN.
        $merged = TokoContoh::product('BUKU-02', ['categoryId' => $this->category, 'name' => 'Buku Gambar']);
        TokoContoh::receive([['productId' => $merged, 'quantity' => '10', 'cost' => ['status' => 'UNKNOWN']]]);
        $out = TokoContoh::movementOf(TokoContoh::issue([['productId' => $merged, 'quantity' => '10']]), 'MANUAL_ISSUE');
        TokoContoh::stock($merged, '10', '100000');
        TokoContoh::issue([['productId' => $merged, 'quantity' => '4']]);
        $this->assertSame(['UNKNOWN', 'KNOWN'], array_column($this->allocationsOf($merged), 3));

        TokoContoh::reverse($out);

        $this->assertSame([['MANUAL_ISSUE', 'UNKNOWN', null, 'CANCELLED', null, 5], ['MANUAL_ISSUE', 'KNOWN', '40000.000000', 'UNKNOWN', null, 5]], $this->allocationsOf($merged));
        $this->assertSame('UNKNOWN||5|5', $this->valuationOf($merged));
    }

    public function test_only_an_opening_receipt_or_manual_issue_is_a_source_and_onboarding_and_a_block_refuse(): void
    {
        // AC-026-02, AC-026-03; BR-INV-34, BR-INV-36, BR-INV-24, G-11. An ADJUSTMENT, a REVERSAL and a
        // movement that does not exist are no reversal's source: NOT_FOUND. A SALE_ISSUE and a
        // RETURN_RECEIPT, which TASK-028 and TASK-033 write, are not eligible either.
        $receipt = TokoContoh::movementOf(TokoContoh::stock($this->pen, '10', '100'));
        TokoContoh::adjust([['productId' => $this->pen, 'direction' => 'DECREASE', 'quantity' => '1']]);
        $adjustment = self::number($this->watcher()->table('stock_movements')->where('product_id', $this->pen)->where('movement_type', 'ADJUSTMENT')->value('id'));
        $issue = TokoContoh::movementOf(TokoContoh::issue([['productId' => $this->pen, 'quantity' => '1']]), 'MANUAL_ISSUE');
        $this->owner();
        $before = $this->factsAndRows();

        foreach ([$adjustment, 999999] as $movement) {
            $this->reverse($this->envelopeOf($movement))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        $this->assertSame($before, $this->factsAndRows());

        foreach (['SALE_ISSUE', 'RETURN_RECEIPT', 'ADJUSTMENT', 'REVERSAL'] as $type) {
            $this->assertFalse((new ReversalSource(1, $this->pen, $type, Quantity::of('1', 0), 1))->eligible(), $type);
        }

        foreach (['OPENING', 'RECEIPT', 'MANUAL_ISSUE'] as $type) {
            $this->assertTrue((new ReversalSource(1, $this->pen, $type, Quantity::of('1', 0), 1))->eligible(), $type);
        }

        // While onboarding is ACTIVE, a RECEIPT and a MANUAL_ISSUE are refused (BR-INV-24).
        $this->runtime()->table('onboarding_state')->where('id', 1)->update(['state' => 'ACTIVE', 'version' => 2]);
        $this->reverse($this->envelopeOf($receipt))->assertStatus(422)->assertJsonPath('code', 'ONBOARDING_FREEZE');
        $this->reverse($this->envelopeOf($issue))->assertStatus(422)->assertJsonPath('code', 'ONBOARDING_FREEZE');
        $this->runtime()->table('onboarding_state')->where('id', 1)->update(['state' => 'NOT_STARTED', 'version' => 3]);

        // A blocked product refuses the reversal (G-11).
        $block = TokoContoh::postingBlock($this->pen);
        $blocked = $this->factsAndRows();
        $this->reverse($this->envelopeOf($receipt))->assertStatus(422)->assertJsonPath('code', 'POSTING_BLOCKED');
        $this->assertSame($blocked, $this->factsAndRows());
        $this->assertSame(0, $this->watcher()->table('reversals')->count());

        // Reopened, the product takes the reversal of its manual issue: 8 on hand become 9.
        TokoContoh::reopenPostings($block);
        $this->reverse($this->envelopeOf($issue))->assertStatus(201);
        $this->assertSame(['9.000', 4], $this->balanceOf($this->pen));
    }

    public function test_the_marks_of_the_source_are_cleared_in_the_reversal_and_a_later_refused_correction_is_cleared_at_once(): void
    {
        // AC-026-04; SCHEMA section 5.8; G-21; BR-COST-27: a correction of the second receipt refused at
        // the movement cap leaves a mark; reversing that receipt clears it in its own transaction.
        TokoContoh::stock($this->pen, '10', '100');
        $source = TokoContoh::movementOf(TokoContoh::stock($this->pen, '10', '200'));
        TokoContoh::issue([['productId' => $this->pen, 'quantity' => '5']]);
        $evidence = TokoContoh::evidenceOf($source);
        $this->owner();
        $hendra = $this->people['Hendra']['id'];
        config(['costing.replay.movement_cap' => 0]);
        $correction = $this->envelope(['evidenceId' => $evidence, 'newCost' => ['amount' => '250'], 'reason' => 'Nota pemasok'], ['operation' => 'cost_correction.confirm', 'expected' => ['correctionNo' => 0]]);
        $this->postJson('/commands/cost_correction.confirm', $correction)->assertStatus(422)->assertJsonPath('code', 'REPLAY_REJECTED');
        $mark = self::number($this->watcher()->table('cost_incomplete_marks')->value('id'));
        config(['costing.replay.movement_cap' => 10000]);

        $response = $this->reverse($this->envelopeOf($source))->assertStatus(201);
        $reversal = self::number($response->json('result.id'));
        $commandId = self::number($response->json('commandId'));
        $this->assertSame([[$mark, $evidence, $source, 'SOURCE_REVERSED', null, $reversal, 'USER', $hendra, null, 'Hendra', $commandId]], $this->clearances());
        $this->assertSame(1, self::dataOf($this->watcher()->table('audit_events')->where('command_id', $commandId)->value('owner_only_data'))['marksCleared'] ?? null);

        // A correction of the reversed source refused afterwards: its mark is written with its clearance
        // by the reversal, in the mark's own transaction, with no command (SCHEMA section 5.8).
        config(['costing.replay.movement_cap' => 0]);
        $this->postJson('/commands/cost_correction.confirm', $this->envelope(['evidenceId' => $evidence, 'newCost' => ['amount' => '260'], 'reason' => 'Nota kedua'], ['operation' => 'cost_correction.confirm', 'expected' => ['correctionNo' => 0]]))
            ->assertStatus(422)->assertJsonPath('code', 'REPLAY_REJECTED');
        $later = self::number($this->watcher()->table('cost_incomplete_marks')->where('id', '<>', $mark)->value('id'));
        $this->assertSame([$later, $evidence, $source, 'SOURCE_REVERSED', null, $reversal, 'USER', $hendra, null, 'Hendra', null], $this->clearances()[1] ?? null);
        $this->assertTrue((bool) (self::dataOf($this->watcher()->table('audit_events')->where('action', 'COST_INCOMPLETE_MARK')->where('subject_id', $later)->value('change_data'))['cleared'] ?? false));
    }

    public function test_a_failure_after_every_write_step_leaves_no_fact_projection_or_valuation_changed(): void
    {
        // AC-026-04, TS-21: eight steps — the reversal, its REVERSAL, the balance, the valuation, the
        // projections, the clearances, the audit event, the result.
        TokoContoh::stock($this->pen, '10', '100');
        $source = TokoContoh::movementOf(TokoContoh::stock($this->pen, '10', '200'));
        TokoContoh::issue([['productId' => $this->pen, 'quantity' => '5']]);
        $this->owner();

        for ($step = 1; $step <= 8; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $before = $this->factsAndRows();

            $this->reverse($this->envelopeOf($source))->assertStatus(500);

            $this->assertSame($before, $this->factsAndRows(), sprintf('after write step %d', $step));
            $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', ConfirmReversal::OPERATION)->orderByDesc('id')->value('outcome'), (string) $step);
        }

        $points = new FailurePoints(9);
        $this->app->instance(CommandPoints::class, $points);
        $this->reverse($this->envelopeOf($source))->assertStatus(201);
        $this->assertSame(['after_lock:2.1', 'after_lock:2.3', 'after_lock:6', 'after_lock:7', ...array_map(static fn (int $step): string => 'after_write:'.$step, range(1, 8))], array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))));
        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->assertSame('KNOWN|50.000000|4|4', $this->valuationOf($this->pen));
    }

    public function test_only_the_owner_with_recent_authentication_reverses_a_movement(): void
    {
        // CAP-17; THR-19: staff and the combined account are refused at the gate, with no receipt, and
        // by the guard in the transaction (SEC-AZ-04); AUTH03: without a recent password confirmation
        // the Owner's reversal answers 423 and runs nothing.
        $source = TokoContoh::movementOf(TokoContoh::stock($this->pen, '10', '100'));
        $before = $this->factsAndRows();

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->postJson('/user/confirm-password', ['password' => $this->people[$person]['password']])->assertStatus(201);
            $this->reverse($this->envelopeOf($source))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
            $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', ConfirmReversal::OPERATION)->where('actor_user_id', $this->people[$person]['id'])->count(), $person);
        }

        foreach (['Agus', 'Dewi'] as $person) {
            $envelope = new Envelope(ConfirmReversal::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, [], ['movementId' => $source, 'reason' => 'Coba']);

            try {
                app(ConfirmReversal::class)->execute(new CommandContext($this->people[$person]['id'], $envelope, app(Clock::class)->now()));
                $this->fail($person.' passed the guard.');
            } catch (ActorNotAllowed) {
                $this->addToAssertionCount(1);
            }
        }

        $this->as('Hendra');
        $this->reverse($this->envelopeOf($source))->assertStatus(423)->assertJsonPath('code', 'RECENT_AUTH_REQUIRED');
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', ConfirmReversal::OPERATION)->count());
        $this->assertSame($before, $this->factsAndRows());

        $this->browse(null);
        $this->reverse($this->envelopeOf($source))->assertStatus(401);
        $this->assertSame($before, $this->factsAndRows());
    }

    public function test_the_fields_are_validated_with_their_keys_and_a_quantity_is_never_taken(): void
    {
        // TR-VAL-01 to TR-VAL-07; TECHNICAL_RULES section 11.3; BR-INV-34: the Owner never enters a
        // quantity, and RC-10 has no expected value.
        $source = TokoContoh::movementOf(TokoContoh::stock($this->pen, '10', '100'));
        $this->owner();
        $base = ['movementId' => $source, 'reason' => 'Salah catat'];
        $before = $this->factsAndRows();

        foreach ([
            [['reason' => null], 'payload.reason', 'ux.validation.required', []],
            [['reason' => str_repeat('r', 1001)], 'payload.reason', 'ux.validation.text_max', ['maks' => 1000]],
            [['movementId' => 0], 'payload.movementId', 'ux.validation.choose_one', []],
        ] as $index => [$payload, $path, $key, $params]) {
            $refused = $this->reverse($this->envelope([...$base, ...$payload], ['operation' => ConfirmReversal::OPERATION, 'expected' => []]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $fields = (array) $refused->json('fields');
            $this->assertSame(['key' => $key, 'params' => $params], ((array) ($fields[$path] ?? []))[0] ?? null, sprintf('case %d at %s', $index, $path));
        }

        foreach ([
            ['quantity' => '5'],
            ['movementId' => (string) $source],
            ['reason' => "Salah\ncatat"],
            ['productId' => $this->pen],
        ] as $payload) {
            $this->reverse($this->envelope([...$base, ...$payload], ['operation' => ConfirmReversal::OPERATION, 'expected' => []]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->reverse($this->envelope($base, ['operation' => ConfirmReversal::OPERATION, 'expected' => ['version' => 1]]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame($before, $this->factsAndRows());
    }

    /** The Owner, with the recent authentication of RC-10 (INTERACTION_CONTRACTS section 1.5). */
    private function owner(): void
    {
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
    }

    /**
     * An envelope of RC-10 for this movement.
     *
     * @return array<string, mixed>
     */
    private function envelopeOf(int $movement): array
    {
        return $this->envelope(['movementId' => $movement, 'reason' => 'Salah catat'], ['operation' => ConfirmReversal::OPERATION, 'expected' => []]);
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function reverse(array $envelope): TestResponse
    {
        return $this->postJson(self::COMMAND, $envelope);
    }

    /**
     * The clearances in the order of their marks.
     *
     * @return list<list<mixed>>
     */
    private function clearances(): array
    {
        return array_values($this->watcher()->table('cost_incomplete_mark_clearances')->orderBy('mark_id')
            ->get(['mark_id', 'evidence_id', 'evidence_movement_id', 'clearing_kind', 'clearing_correction_id', 'clearing_reversal_id', 'actor_kind', 'actor_user_id', 'actor_process', 'actor_display_name', 'command_id'])
            ->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    /**
     * A product's balance: on hand and the last sequence.
     *
     * @return list<mixed>
     */
    private function balanceOf(int $product): array
    {
        return array_values((array) $this->watcher()->table('stock_balances')->where('product_id', $product)->first(['on_hand', 'last_sequence']));
    }

    /**
     * The allocations of a product in their order: the type, the fact's status and amount, the
     * projection's status, amount and publication.
     *
     * @return list<list<mixed>>
     */
    private function allocationsOf(int $product): array
    {
        return array_values($this->watcher()->table('issue_cost_allocations as a')->join('issue_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->where('a.product_id', $product)->orderBy('a.product_sequence')
            ->get(['a.issue_type', 'a.at_commit_status', 'a.at_commit_amount', 'p.current_status', 'p.current_amount', 'p.publication_no'])
            ->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    /**
     * A product's valuation as "status|value|publication|last sequence".
     */
    private function valuationOf(int $product): string
    {
        $row = (array) $this->watcher()->table('inventory_valuations')->where('product_id', $product)->sole();

        return implode('|', [self::text($row['value_status']), self::text($row['value']), (string) self::number($row['publication_no']), (string) self::number($row['last_sequence'])]);
    }

    /**
     * Data of jsonb with its keys sorted, since jsonb keeps no order of its own.
     *
     * @return array<array-key, mixed>
     */
    private static function dataOf(mixed $json): array
    {
        $data = json_decode(is_string($json) ? $json : '', true);
        self::assertIsArray($data);
        ksort($data);

        return $data;
    }

    /** One independent session for every read of the test, so that the test stays within the server's connections. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    /**
     * The facts, with the rows that a reversal changes: the balances, the valuations and the projections.
     *
     * @return array<string, mixed>
     */
    private function factsAndRows(): array
    {
        $counts = [];

        foreach (self::FACTS as $table) {
            $counts[$table] = $this->watcher()->table($table)->count();
        }

        return [
            'facts' => $counts,
            'balances' => array_values($this->watcher()->table('stock_balances')->orderBy('product_id')->get()->map(static fn (object $row): array => (array) $row)->all()),
            'valuations' => array_values($this->watcher()->table('inventory_valuations')->orderBy('product_id')->get()->map(static fn (object $row): array => (array) $row)->all()),
            'projections' => array_values($this->watcher()->table('issue_cost_allocation_projections')->orderBy('allocation_id')->get()->map(static fn (object $row): array => (array) $row)->all()),
        ];
    }
}
