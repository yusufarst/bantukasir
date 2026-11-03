<?php

namespace Tests\Feature\Inventory;

use App\Actions\Commands\CommandContext;
use App\Actions\Inventory\ConfirmAdjustment;
use App\Database\CommandPoints;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Operations;
use App\Support\Time\Clock;
use FilesystemIterator;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-09 `adjustment.confirm` through its route (AC-024-01 to AC-024-03; INTERACTION_CONTRACTS RC-09
 * and section 1.5; ROUTE_CONTRACTS section 6; TECHNICAL_RULES sections 7.1, 7.2, 11.1 to 11.3;
 * BR-INV-06 to BR-INV-11, BR-INV-31 to BR-INV-33; BR-COST-02 to BR-COST-05, BR-COST-10 to
 * BR-COST-16; ADR-008 sections 2, 4.1 and 4.2; WF-22; G-02, G-10, G-11, G-13, G-14; CAP-16;
 * EXECUTION_CONTEXT section 10, a command; suites INV03, AUTH03, CMD01, AUTH01, AUTH02; TASK-024, L2
 * and L4): the Owner alone, with recent authentication; each line's signed movement, an increase
 * with its cost evidence and a decrease with its allocation, the fact and its equal projection;
 * the balance and the valuation; the audit event, its costs the Owner's; the result that leads to
 * RP-402 filtered to the adjusted products; insufficient stock for the decreases of a product added
 * together; every refusal, which writes nothing; the fields and the envelope; the failure after
 * every write step; the roles and the combined account; one writer of each table.
 */
final class ConfirmAdjustmentTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string COMMAND = '/commands/adjustment.confirm';

    /** The tables that a refused adjustment leaves as they were. */
    private const array FACTS = ['stock_movements', 'stock_balances', 'adjustments', 'adjustment_lines', 'acquisition_cost_evidence', 'issue_cost_allocations', 'issue_cost_allocation_projections', 'inventory_valuations', 'command_identities', 'command_results', 'audit_events'];

    private int $category = 0;

    /** Counted whole: `pcs`, precision 0. */
    private int $pen = 0;

    /** Weighed: `kg`, precision 3. */
    private int $rice = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category();
        $this->pen = TokoContoh::product('PEN-01', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam']);
        $this->rice = TokoContoh::product('BERAS-01', ['categoryId' => $this->category, 'name' => 'Beras', 'baseUnit' => 'kg', 'quantityPrecision' => 3]);
    }

    public function test_an_adjustment_posts_each_signed_line_with_its_evidence_or_allocation_balance_valuation_audit_and_result(): void
    {
        // AC-024-01, AC-024-02: BR-INV-31, BR-INV-32; ADR-008 sections 2 and 4.2; G-13, G-14. The
        // Owner counts 13 pens where 10 stood, and 2.5 kg of rice where 4 kg stood, with a pen line
        // that takes 1 off again: each line posts its own movement in the order of the lines.
        TokoContoh::stock($this->pen, '10', '100000');
        TokoContoh::stock($this->rice, '4', '30000');
        $this->owner();
        $hendra = $this->people['Hendra']['id'];
        $envelope = $this->envelopeOf([self::increase($this->pen, '3', ['status' => 'KNOWN', 'amount' => '36000']), self::decrease($this->rice, '1.5'), self::decrease($this->pen, '1')], ['reason' => 'Hitung fisik akhir bulan', 'countReference' => 'LH-11/03', 'reference' => 'BA-2026-11-03']);

        $response = $this->confirm($envelope)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed', 'owner');
        $adjustment = self::number($response->json('result.id'));
        $commandId = self::number($response->json('commandId'));
        $products = implode(',', [min($this->pen, $this->rice), max($this->pen, $this->rice)]);
        $this->assertSame(['adjustment', '/stock/history?product='.$products], [$response->json('result.type'), $response->json('result.location')]);

        // The adjustment, with its reason, count, reference and the actor's frozen name.
        $row = (array) $this->watcher()->table('adjustments')->where('id', $adjustment)->sole();
        $this->assertSame(['Hitung fisik akhir bulan', 'LH-11/03', 'BA-2026-11-03', 3, $hendra, 'Hendra', $commandId], [$row['reason'], $row['count_reference'], $row['reference'], $row['line_count'], $row['actor_user_id'], $row['actor_display_name'], $row['command_id']]);

        // One ADJUSTMENT per line, of its line's signed quantity, in its product's sequence.
        $movements = $this->watcher()->table('stock_movements', 'm')->join('adjustment_lines as l', 'l.id', '=', 'm.adjustment_line_id')
            ->where('m.command_id', $commandId)->orderBy('m.id')
            ->get(['l.adjustment_id', 'l.line_no', 'l.quantity as line_quantity_of_line', 'm.product_id', 'm.product_sequence', 'm.movement_type', 'm.quantity', 'm.line_quantity', 'm.cost_evidence_presence', 'm.issue_allocation_presence', 'm.id'])
            ->map(static fn (object $movement): array => (array) $movement)->all();
        $ids = array_map(static fn (array $movement): int => self::number($movement['id']), $movements);
        $this->assertSame([
            [$adjustment, 1, '3.000', $this->pen, 2, 'ADJUSTMENT', '3.000', '3.000', $ids[0], null],
            [$adjustment, 2, '-1.500', $this->rice, 2, 'ADJUSTMENT', '-1.500', '-1.500', null, $ids[1]],
            [$adjustment, 3, '-1.000', $this->pen, 3, 'ADJUSTMENT', '-1.000', '-1.000', null, $ids[2]],
        ], array_map(static fn (array $movement): array => array_values(array_diff_key($movement, ['id' => true])), $movements));

        // The increase's cost evidence, KNOWN, with the adjustment's reference (BR-INV-32).
        $evidence = (array) $this->watcher()->table('acquisition_cost_evidence')->where('movement_id', $ids[0])->sole();
        $this->assertSame([$this->pen, 'ADJUSTMENT', '3.000', 'KNOWN', '36000.000000', null, 'BA-2026-11-03', $hendra, $commandId], [$evidence['product_id'], $evidence['movement_type'], $evidence['quantity'], $evidence['cost_status'], $evidence['total_amount'], $evidence['zero_cost_note'], $evidence['reference'], $evidence['actor_user_id'], $evidence['command_id']]);

        // Each decrease's allocation at the average before it, as a manual issue's (BR-INV-32):
        // 1.5 of 4 kg worth Rp30.000 is Rp11.250; one of 13 pens worth Rp136.000 is Rp10.461,538462.
        $allocations = $this->watcher()->table('issue_cost_allocations')->whereIn('movement_id', [$ids[1], $ids[2]])->orderBy('id')
            ->get(['movement_id', 'issue_type', 'movement_quantity', 'issued_quantity', 'at_commit_status', 'at_commit_amount', 'based_on_publication_no'])->map(static fn (object $item): array => array_values((array) $item))->all();
        $this->assertSame([
            [$ids[1], 'ADJUSTMENT', '-1.500', '1.500', 'KNOWN', '11250.000000', 1],
            [$ids[2], 'ADJUSTMENT', '-1.000', '1.000', 'KNOWN', '10461.538462', 2],
        ], $allocations);
        $this->assertProjectionsEqualTheirFacts();

        // The balances and valuations, one publication per movement.
        $this->assertSame([['12.000', 3], ['2.500', 2]], [$this->balanceOf($this->pen), $this->balanceOf($this->rice)]);
        $this->assertSame(['KNOWN|125538.461538|3|3', 'KNOWN|18750.000000|2|2'], [$this->valuationOf($this->pen), $this->valuationOf($this->rice)]);
        $this->assertBalancesAreTheSumOfTheMovements();

        // One audit event; its costs are the Owner's part (BR-AUD-02). Two products, so no product filter.
        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['BUSINESS', 'ADJUSTMENT_CONFIRM', $hendra, 'adjustment', $adjustment, null, 'BA-2026-11-03'], [$event['category'], $event['action'], $event['actor_user_id'], $event['subject_type'], $event['subject_id'], $event['product_id'], $event['reference']]);
        $this->assertSame(['countReference' => 'LH-11/03', 'lineCount' => 3, 'lines' => [
            ['lineNo' => 1, 'movementId' => $ids[0], 'productId' => $this->pen, 'productSequence' => 2, 'quantity' => '3'],
            ['lineNo' => 2, 'movementId' => $ids[1], 'productId' => $this->rice, 'productSequence' => 2, 'quantity' => '-1.5'],
            ['lineNo' => 3, 'movementId' => $ids[2], 'productId' => $this->pen, 'productSequence' => 3, 'quantity' => '-1'],
        ], 'reason' => 'Hitung fisik akhir bulan'], self::dataOf($event['change_data']));

        foreach (['amount', 'KNOWN', 'allocation', 'evidence'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, self::text($event['change_data']), $word);
        }

        $owner = self::dataOf($event['owner_only_data']);
        $this->assertSame(['36000', '11250', '10461.538462'], array_map(static fn (mixed $line): mixed => is_array($line) ? ($line['amount'] ?? null) : null, (array) ($owner['lines'] ?? [])));

        // The result holds references only; no version moved; no Sale table exists.
        $stored = (array) $this->watcher()->table('command_results')->where('command_id', $commandId)->sole();
        $this->assertSame(['adjustment', $adjustment], [$stored['result_type'], $stored['result_ref']]);
        $this->assertSame([1, 1], [$this->versionOf($this->pen), $this->versionOf($this->rice)]);
        $this->assertSame([], $this->watcher()->table('pg_tables')->where('schemaname', 'public')->whereRaw("tablename ~ '^(sale|payment|receipt|refund)'")->pluck('tablename')->all());

        // A repeat answers the original and writes nothing; a status check recovers it.
        $facts = $this->factsAndRows();
        $this->confirm($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $adjustment)->assertJsonPath('result.location', '/stock/history?product='.$products);
        $this->assertSame($facts, $this->factsAndRows());
        $resolved = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.location', '/stock/history?product='.$products);
        $this->assertJsonChannel($resolved, 'commands.resolve', 'owner');
    }

    public function test_an_increase_carries_known_unknown_or_an_explicit_zero_and_a_decrease_follows_adr_008(): void
    {
        // AC-024-02: BR-COST-02 to BR-COST-04, BR-COST-11, BR-COST-13, BR-COST-15.
        TokoContoh::stock($this->pen, '3', '100');
        $this->owner();

        // An UNKNOWN increase makes the valuation UNKNOWN, and a decrease then allocates UNKNOWN, never zero.
        $this->confirm($this->envelopeOf([self::increase($this->pen, '2', ['status' => 'UNKNOWN'])]))->assertStatus(201);
        $this->assertSame('UNKNOWN||2|2', $this->valuationOf($this->pen));
        $unknown = self::number($this->confirm($this->envelopeOf([self::decrease($this->pen, '1')]))->assertStatus(201)->json('commandId'));
        $this->assertSame([['UNKNOWN', null]], $this->allocationsOf($unknown));

        // The decrease that takes stock to zero: UNKNOWN still, and a new pool, a known zero.
        $zero = self::number($this->confirm($this->envelopeOf([self::decrease($this->pen, '4')]))->assertStatus(201)->json('commandId'));
        $this->assertSame([['UNKNOWN', null]], $this->allocationsOf($zero));
        $this->assertSame(['0.000', 4], $this->balanceOf($this->pen));
        $this->assertSame('KNOWN|0.000000|4|4', $this->valuationOf($this->pen));

        // The Owner's explicit zero, with its evidence or reason (BR-COST-04).
        $free = self::number($this->confirm($this->envelopeOf([self::increase($this->pen, '5', ['status' => 'KNOWN', 'amount' => '0', 'zeroEvidence' => 'Sampel gratis pemasok'])]))->assertStatus(201)->json('commandId'));
        $this->assertSame(['KNOWN', '0.000000', 'Sampel gratis pemasok'], array_values((array) $this->watcher()->table('acquisition_cost_evidence as e')->join('stock_movements as m', 'm.id', '=', 'e.movement_id')->where('m.command_id', $free)->sole(['e.cost_status', 'e.total_amount', 'e.zero_cost_note'])));
        $this->assertSame('KNOWN|0.000000|5|5', $this->valuationOf($this->pen));

        // Three thirds of Rp100 by decreases: the last takes what is left (BR-COST-11).
        TokoContoh::stock($this->rice, '3', '100');
        $this->owner();
        $thirds = self::number($this->confirm($this->envelopeOf([self::decrease($this->rice, '1'), self::decrease($this->rice, '1'), self::decrease($this->rice, '1')]))->assertStatus(201)->json('commandId'));
        $this->assertSame([['KNOWN', '33.333333'], ['KNOWN', '33.333334'], ['KNOWN', '33.333333']], $this->allocationsOf($thirds));
        $this->assertSame('KNOWN|0.000000|4|4', $this->valuationOf($this->rice));
        $this->assertProjectionsEqualTheirFacts();
        $this->assertBalancesAreTheSumOfTheMovements();
    }

    public function test_no_adjustment_makes_stock_negative_the_decreases_of_a_product_added_together(): void
    {
        // AC-024-02: BR-INV-33, BR-INV-07, G-02. Five pens: two decreases of three are six, refused
        // even with an increase of the pen in the same adjustment, each decrease named with the stock
        // available; the increase is not.
        TokoContoh::stock($this->pen, '5', '5000');
        $this->owner();
        $before = $this->factsAndRows();

        $short = $this->confirm($this->envelopeOf([self::decrease($this->pen, '3'), self::increase($this->pen, '4', ['status' => 'UNKNOWN']), self::decrease($this->pen, '3'), self::decrease($this->rice, '0.5')]))
            ->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_STOCK')->assertJsonPath('outcome', 'REJECTED');
        $this->assertSame([
            '0' => ['productId' => $this->pen, 'available' => '5'],
            '2' => ['productId' => $this->pen, 'available' => '5'],
            '3' => ['productId' => $this->rice, 'available' => '0'],
        ], (array) $short->json('lines'));
        $this->assertSame($before, $this->factsAndRows());

        // The channel's allowlist reads a list of lines, as one refused line gives.
        $one = $this->confirm($this->envelopeOf([self::decrease($this->rice, '1')]))->assertStatus(422)->assertJsonPath('lines', [['productId' => $this->rice, 'available' => '0']]);
        $this->assertJsonChannel($one, 'commands.refused.stock', 'owner');

        // Exactly the stock, added together, posts and leaves zero.
        $this->confirm($this->envelopeOf([self::decrease($this->pen, '2'), self::decrease($this->pen, '3')]))->assertStatus(201);
        $this->assertSame(['0.000', 3], $this->balanceOf($this->pen));
        $this->confirm($this->envelopeOf([self::decrease($this->pen, '1')]))->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_STOCK')->assertJsonPath('lines', [['productId' => $this->pen, 'available' => '0']]);
    }

    public function test_each_refusal_under_the_locks_names_its_lines_and_writes_nothing(): void
    {
        // INTERACTION_CONTRACTS RC-09, "Under locks"; TR-VAL-08; BR-INV-06, BR-INV-11, G-10, G-11.
        $sugar = TokoContoh::product('GULA-01', ['categoryId' => $this->category, 'name' => 'Gula', 'baseUnit' => 'kg', 'quantityPrecision' => 2]);
        TokoContoh::stock($sugar, '9', '9000');
        TokoContoh::postingBlock($sugar);
        $sweet = TokoContoh::product('MANIS-01', ['categoryId' => $this->category, 'name' => 'Pemanis', 'baseUnit' => 'kg', 'quantityPrecision' => 2]);
        TokoContoh::stock($sweet, '9', '9000');
        TokoContoh::stock($this->pen, '9', '9000');
        TokoContoh::stock($this->rice, '9', '9000');
        $this->as('Hendra');
        $this->command('product.deactivate', ['productId' => $this->rice], ['productVersion' => 1])->assertStatus(201);
        $this->owner();
        $before = $this->factsAndRows();

        $this->confirm($this->envelopeOf([self::decrease($this->rice, '1'), self::decrease(999999, '1')], [], [['productId' => $this->rice, 'version' => 2], ['productId' => 999999, 'version' => 1]]))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->confirm($this->envelopeOf([self::decrease($this->pen, '1'), self::increase($this->rice, '1', ['status' => 'UNKNOWN'])]))->assertStatus(422)->assertJsonPath('code', 'PRODUCT_INACTIVE')->assertJsonPath('lines', [1 => ['productId' => $this->rice]]);
        $this->confirm($this->envelopeOf([self::decrease($this->pen, '1'), self::decrease($sugar, '1')]))->assertStatus(422)->assertJsonPath('code', 'POSTING_BLOCKED')->assertJsonPath('lines', [1 => ['productId' => $sugar]]);
        $this->confirm($this->envelopeOf([self::decrease($this->pen, '1'), self::decrease($sweet, '1')], [], [['productId' => $sweet, 'version' => 1], ['productId' => $this->pen, 'version' => 2]]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION')->assertJsonPath('lines', [0 => ['productId' => $this->pen]]);

        // The precision before the stock.
        $precision = $this->confirm($this->envelopeOf([self::decrease($this->pen, '1.5'), self::increase($sweet, '100.234', ['status' => 'UNKNOWN'])]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame([
            'payload.lines.0.quantity' => [['key' => 'ux.validation.quantity_whole', 'params' => []]],
            'payload.lines.1.quantity' => [['key' => 'ux.validation.quantity_decimals', 'params' => ['desimal' => '2']]],
        ], $precision->json('fields'));
        $this->assertSame($before, $this->factsAndRows());

        // The onboarding freeze, before every product (G-10).
        $this->runtime()->table('onboarding_state')->where('id', 1)->update(['state' => 'ACTIVE', 'version' => 2]);
        $this->confirm($this->envelopeOf([self::decrease($this->pen, '1')]))->assertStatus(422)->assertJsonPath('code', 'ONBOARDING_FREEZE');
        $this->runtime()->table('onboarding_state')->where('id', 1)->update(['state' => 'NOT_STARTED', 'version' => 3]);
        $this->assertSame($before, $this->factsAndRows());
    }

    public function test_the_fields_are_validated_with_their_keys_before_any_lock(): void
    {
        // AC-024-01: BR-INV-31, BR-INV-32; TR-VAL-01 to TR-VAL-07; TECHNICAL_RULES sections 11.2
        // and 11.3; COPY_DECK section 16.1.
        TokoContoh::stock($this->pen, '300', '30000');
        $decrease = self::decrease($this->pen, '1');
        $increase = self::increase($this->pen, '1', ['status' => 'KNOWN', 'amount' => '100']);
        $base = ['reason' => 'Hitung', 'countReference' => 'HIT', 'reference' => 'REF', 'lines' => [$decrease]];
        $this->owner();
        $before = $this->factsAndRows();

        foreach ([
            [['reason' => null], 'payload.reason', 'ux.validation.required', []],
            [['reason' => ''], 'payload.reason', 'ux.validation.required', []],
            [['reason' => str_repeat('r', 1001)], 'payload.reason', 'ux.validation.text_max', ['maks' => 1000]],
            [['countReference' => null], 'payload.countReference', 'ux.validation.required', []],
            [['countReference' => str_repeat('c', 101)], 'payload.countReference', 'ux.validation.text_max', ['maks' => 100]],
            [['reference' => null], 'payload.reference', 'ux.validation.required', []],
            [['reference' => str_repeat('f', 101)], 'payload.reference', 'ux.validation.text_max', ['maks' => 100]],
            [['lines' => []], 'payload.lines', 'ux.validation.lines_min', []],
            [['lines' => [[...$decrease, 'direction' => 'UP']]], 'payload.lines.0.direction', 'ux.validation.choose_one', []],
            [['lines' => [[...$decrease, 'direction' => null]]], 'payload.lines.0.direction', 'ux.validation.choose_one', []],
            [['lines' => [[...$decrease, 'quantity' => '0']]], 'payload.lines.0.quantity', 'ux.validation.quantity_positive', []],
            [['lines' => [[...$decrease, 'quantity' => '-1']]], 'payload.lines.0.quantity', 'ux.validation.quantity_positive', []],
            [['lines' => [[...$decrease, 'quantity' => '100000000']]], 'payload.lines.0.quantity', 'ux.validation.quantity_max', []],
            [['lines' => [self::increase($this->pen, '1', null)]], 'payload.lines.0.cost', 'ux.validation.required', []],
            [['lines' => [self::increase($this->pen, '1', ['status' => 'KNOWN'])]], 'payload.lines.0.cost.amount', 'ux.validation.required', []],
            [['lines' => [self::increase($this->pen, '1', ['status' => 'KNOWN', 'amount' => '0'])]], 'payload.lines.0.cost.zeroEvidence', 'ux.validation.zero_evidence', []],
            [['lines' => [self::increase($this->pen, '1', ['status' => 'KNOWN', 'amount' => '1,5'])]], 'payload.lines.0.cost.amount', 'ux.validation.money', []],
            [['lines' => array_fill(0, 201, $decrease)], 'payload.lines', 'ux.validation.lines_max', ['maks' => ConfirmAdjustment::MAX_LINES]],
        ] as $index => [$payload, $path, $key, $params]) {
            $refused = $this->confirmPayload([...$base, ...$payload])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $fields = (array) $refused->json('fields');
            $this->assertSame(['key' => $key, 'params' => $params], ((array) ($fields[$path] ?? []))[0] ?? null, sprintf('case %d at %s', $index, $path));
        }

        // What no page sends: a cost on a decrease, UNKNOWN with an amount, a control character, another field.
        foreach ([
            ['lines' => [[...$decrease, 'cost' => ['status' => 'UNKNOWN']]]],
            ['lines' => [[...$decrease, 'cost' => null]]],
            ['lines' => [self::increase($this->pen, '1', ['status' => 'UNKNOWN', 'amount' => '0'])]],
            ['lines' => [self::increase($this->pen, '1', ['status' => 'KNOWN', 'amount' => '5', 'zeroEvidence' => 'x'])]],
            ['lines' => [[...$decrease, 'quantity' => -1]]],
            ['lines' => [[...$decrease, 'productId' => (string) $this->pen]]],
            ['reason' => "Hitung\nulang"],
            ['supplier' => 'Toko Lain'],
            ['lines' => [0 => $decrease, 2 => $decrease]],
        ] as $payload) {
            $this->confirmPayload([...$base, ...$payload])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // The versions reviewed name each product of the lines once, as whole numbers.
        foreach ([[], [['productId' => $this->rice, 'version' => 1]], [['productId' => $this->pen, 'version' => 1], ['productId' => $this->pen, 'version' => 1]], [['productId' => $this->pen, 'version' => '1']]] as $expected) {
            $this->confirm($this->envelopeOf([$decrease], [], $expected))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($before, $this->factsAndRows());

        // The longest texts and 200 lines are kept whole.
        $commandId = self::number($this->confirmPayload(['reason' => str_repeat('r', 1000), 'countReference' => str_repeat('c', 100), 'reference' => str_repeat('f', 100), 'lines' => [...array_fill(0, 100, $decrease), ...array_fill(0, 100, $increase)]])->assertStatus(201)->json('commandId'));
        $this->assertSame(range(2, 201), $this->watcher()->table('stock_movements')->where('command_id', $commandId)->orderBy('id')->pluck('product_sequence')->all());
        $this->assertSame(['300.000', 201], $this->balanceOf($this->pen));
        $this->assertSame(1000, mb_strlen(self::text($this->watcher()->table('adjustments')->value('reason'))));
        $this->assertBalancesAreTheSumOfTheMovements();
    }

    public function test_recent_authentication_is_required_and_a_refused_envelope_or_a_repeat_writes_nothing(): void
    {
        // AUTH03, INTERACTION_CONTRACTS section 1.5: without a recent password confirmation the
        // Owner's adjustment answers 423 and runs nothing, with no receipt; after it, the same frozen
        // request runs. CMD01, G-12, G-25, RC-50; RACE-01 and RACE-02 in sequence.
        TokoContoh::stock($this->pen, '10', '10000');
        $this->as('Hendra');
        $envelope = $this->envelopeOf([self::decrease($this->pen, '3')]);
        $before = $this->factsAndRows();

        $this->confirm($envelope)->assertStatus(423)->assertJsonPath('code', 'RECENT_AUTH_REQUIRED');
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', ConfirmAdjustment::OPERATION)->count());
        $this->assertSame($before, $this->factsAndRows());

        $this->owner();

        foreach ([['version' => 2], ['tambahan' => 'x'], ['operation' => 'manual_issue.confirm']] as $changes) {
            $this->confirm([...$envelope, 'idempotencyKey' => (string) Str::uuid(), ...$changes])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->confirm([...$envelope, 'sourceIntentId' => (string) Str::uuid(), 'idempotencyKey' => (string) Str::uuid(), 'recoveryEpoch' => str_repeat('ab', 32)])->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->assertSame($before, $this->factsAndRows());

        $adjustment = self::number($this->confirm($envelope)->assertStatus(201)->json('result.id'));
        $facts = $this->factsAndRows();
        $this->confirm([...$envelope, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $adjustment);
        $this->confirm([...$envelope, 'payload' => ['reason' => 'Lain', 'countReference' => 'HIT', 'reference' => 'REF', 'lines' => [self::decrease($this->pen, '4')]]])->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        $this->assertSame($facts, $this->factsAndRows());
        $this->assertSame(['7.000', 2], $this->balanceOf($this->pen));
    }

    public function test_a_failure_after_every_write_step_leaves_no_fact_and_the_receipt_pending(): void
    {
        // AC-024-03, TS-21: two products and three lines write 16 steps — each product's balance,
        // each product's valuation, the adjustment, each line's row, movement and its evidence or
        // allocation with its projection, the audit event, the result.
        TokoContoh::stock($this->pen, '20', '20000');
        TokoContoh::stock($this->rice, '5', '5000');
        $lines = [self::increase($this->pen, '2', ['status' => 'KNOWN', 'amount' => '2000']), self::decrease($this->rice, '1.5'), self::decrease($this->pen, '3')];
        $this->owner();

        for ($step = 1; $step <= 16; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $before = $this->factsAndRows();

            $this->confirm($this->envelopeOf($lines))->assertStatus(500);

            $this->assertSame($before, $this->factsAndRows(), sprintf('after write step %d', $step));
            $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', ConfirmAdjustment::OPERATION)->orderByDesc('id')->value('outcome'), (string) $step);
        }

        $points = new FailurePoints(17);
        $this->app->instance(CommandPoints::class, $points);
        $this->confirm($this->envelopeOf($lines))->assertStatus(201);
        $this->assertSame(['after_lock:2.1', 'after_lock:2.3', 'after_lock:6', 'after_lock:7', ...array_map(static fn (int $step): string => 'after_write:'.$step, range(1, 16))], array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))));
        $this->app->instance(CommandPoints::class, new FailurePoints);

        $this->assertSame([['19.000', 3], ['3.500', 2]], [$this->balanceOf($this->pen), $this->balanceOf($this->rice)]);
        $this->assertProjectionsEqualTheirFacts();
        $this->assertBalancesAreTheSumOfTheMovements();
    }

    public function test_only_the_owner_passes_the_gate_and_the_guard_in_the_transaction_holds_too(): void
    {
        // AC-024-01: CAP-16, the Owner's alone; OPERATIONS_ADMIN, the cashier and the combined
        // account are refused at the gate, with no receipt; SEC-AZ-04 in the Action.
        TokoContoh::stock($this->pen, '10', '10000');
        $before = $this->factsAndRows();

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->postJson('/user/confirm-password', ['password' => $this->people[$person]['password']])->assertStatus(201);
            $this->confirm($this->envelopeOf([self::decrease($this->pen, '1')]))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
            $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', ConfirmAdjustment::OPERATION)->where('actor_user_id', $this->people[$person]['id'])->count(), $person);
        }

        foreach (['Agus', 'Dewi'] as $person) {
            $envelope = new Envelope(ConfirmAdjustment::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, ['products' => [['productId' => $this->pen, 'version' => 1]]], ['reason' => 'Hitung', 'countReference' => 'HIT', 'reference' => 'REF', 'lines' => [self::decrease($this->pen, '1')]]);

            try {
                app(ConfirmAdjustment::class)->execute(new CommandContext($this->people[$person]['id'], $envelope, app(Clock::class)->now()));
                $this->fail($person.' passed the guard.');
            } catch (ActorNotAllowed) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame($before, $this->factsAndRows());

        $this->browse(null);
        $this->confirm($this->envelopeOf([self::decrease($this->pen, '1')]))->assertStatus(401);
        $this->assertSame($before, $this->factsAndRows());
    }

    public function test_each_table_of_the_adjustment_has_its_one_writer(): void
    {
        // STK-12, ARC-20, ARC-21: the adjustment's tables are named by the Inventory module's
        // writer alone, and its operation is the one of its family's that begins so.
        $this->assertSame(['Domain/Inventory/Adjustments.php'], self::filesNaming('adjustments'));
        $this->assertSame(['Domain/Inventory/Adjustments.php'], self::filesNaming('adjustment_lines'));
        $this->assertSame([ConfirmAdjustment::OPERATION], array_values(array_filter(array_keys(Operations::FAMILIES), static fn (string $operation): bool => str_starts_with($operation, 'adjustment'))));
    }

    /**
     * An increase of RC-09, with its cost, or none when null.
     *
     * @param  array<string, string>|null  $cost
     * @return array<string, mixed>
     */
    private static function increase(int $product, string $quantity, ?array $cost): array
    {
        return ['productId' => $product, 'direction' => 'INCREASE', 'quantity' => $quantity, ...($cost === null ? [] : ['cost' => $cost])];
    }

    /**
     * A decrease of RC-09.
     *
     * @return array<string, mixed>
     */
    private static function decrease(int $product, string $quantity): array
    {
        return ['productId' => $product, 'direction' => 'DECREASE', 'quantity' => $quantity];
    }

    /** The Owner, with the recent authentication of RC-09 (INTERACTION_CONTRACTS section 1.5). */
    private function owner(): void
    {
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
    }

    /**
     * An envelope of RC-09 for these lines, with its texts and the version of each of their
     * products as it stands, once, unless $expected names others.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>|null  $expected
     * @return array<string, mixed>
     */
    private function envelopeOf(array $lines, array $payload = [], ?array $expected = null): array
    {
        if ($expected === null) {
            $versions = [];

            foreach ($lines as $line) {
                $product = self::number($line['productId'] ?? null);
                $versions[$product] ??= ['productId' => $product, 'version' => $this->versionOf($product)];
            }

            $expected = array_values($versions);
        }

        return $this->envelope(['reason' => 'Hitung fisik', 'countReference' => 'HIT-01', 'reference' => 'REF-01', 'lines' => $lines, ...$payload], ['operation' => ConfirmAdjustment::OPERATION, 'expected' => ['products' => $expected]]);
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function confirm(array $envelope): TestResponse
    {
        return $this->postJson(self::COMMAND, $envelope);
    }

    /**
     * RC-09 with this payload, with the versions of the products that its lines name, or of the
     * pen when they name none that exists.
     *
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function confirmPayload(array $payload): TestResponse
    {
        $lines = $payload['lines'] ?? null;
        $products = [];

        foreach (is_array($lines) ? $lines : [] as $line) {
            $product = is_array($line) ? ($line['productId'] ?? null) : null;

            if (is_int($product) && $product > 0 && $product < 999999) {
                $products[$product] ??= ['productId' => $product, 'version' => $this->versionOf($product)];
            }
        }

        $expected = $products === [] ? [['productId' => $this->pen, 'version' => 1]] : array_values($products);

        return $this->confirm($this->envelope($payload, ['operation' => ConfirmAdjustment::OPERATION, 'expected' => ['products' => $expected]]));
    }

    /**
     * Another command with its envelope.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return TestResponse<Response>
     */
    private function command(string $operation, array $payload, array $expected): TestResponse
    {
        return $this->postJson('/commands/'.$operation, $this->envelope($payload, ['operation' => $operation, 'expected' => $expected]));
    }

    private function versionOf(int $product): int
    {
        return self::number($this->watcher()->table('products')->where('id', $product)->value('version'));
    }

    /**
     * The allocations of a command's movements, in their order: status and amount.
     *
     * @return list<list<mixed>>
     */
    private function allocationsOf(int $commandId): array
    {
        return array_values($this->watcher()->table('issue_cost_allocations as a')->join('stock_movements as m', 'm.id', '=', 'a.movement_id')
            ->where('m.command_id', $commandId)->orderBy('a.id')->get(['a.at_commit_status', 'a.at_commit_amount'])
            ->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    /**
     * G-14: every projection holds its fact's status and amount, and every fact has one.
     */
    private function assertProjectionsEqualTheirFacts(): void
    {
        $rows = $this->watcher()->table('issue_cost_allocations as a')->leftJoin('issue_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->orderBy('a.id')->get(['a.issue_type', 'a.at_commit_status', 'a.at_commit_amount', 'p.issue_type as projected_type', 'p.current_status', 'p.current_amount']);

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $values = (array) $row;
            $this->assertSame([$values['issue_type'], $values['at_commit_status'], $values['at_commit_amount']], [$values['projected_type'], $values['current_status'], $values['current_amount']]);
        }

        $this->assertSame($this->watcher()->table('issue_cost_allocations')->count(), $this->watcher()->table('issue_cost_allocation_projections')->count());
    }

    /**
     * A product's balance: on hand and the last sequence.
     *
     * @return array{mixed, mixed}|null
     */
    private function balanceOf(int $product): ?array
    {
        $row = $this->watcher()->table('stock_balances')->where('product_id', $product)->first(['on_hand', 'last_sequence']);

        return $row === null ? null : [((array) $row)['on_hand'], ((array) $row)['last_sequence']];
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
     * G-03: each balance is the sum of its product's movements, and its last sequence the number
     * of them.
     */
    private function assertBalancesAreTheSumOfTheMovements(): void
    {
        $sums = [];

        foreach ($this->watcher()->table('stock_movements')->selectRaw('product_id, sum(quantity) as total, max(product_sequence) as last, count(*) as movements')->groupBy('product_id')->orderBy('product_id')->get() as $row) {
            $values = (array) $row;
            $sums[self::number($values['product_id'])] = [self::text($values['total']), self::number($values['last']), self::number($values['movements'])];
        }

        $balances = [];

        foreach ($this->watcher()->table('stock_balances')->orderBy('product_id')->get(['product_id', 'on_hand', 'last_sequence']) as $row) {
            $values = (array) $row;
            $balances[self::number($values['product_id'])] = [self::text($values['on_hand']), self::number($values['last_sequence']), self::number($values['last_sequence'])];
        }

        $this->assertSame($sums, $balances);
    }

    /**
     * Data of jsonb with its keys sorted at every level, since jsonb keeps no order of its own.
     *
     * @return array<array-key, mixed>
     */
    private static function dataOf(mixed $json): array
    {
        $data = json_decode(is_string($json) ? $json : '', true);
        self::assertIsArray($data);

        return self::sortedKeys($data);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function sortedKeys(array $data): array
    {
        ksort($data);

        return array_map(static fn (mixed $value): mixed => is_array($value) ? self::sortedKeys($value) : $value, $data);
    }

    /**
     * The files of app/ that name this table in a string, as the query builder takes it; a
     * mention in a comment is no access.
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

    /** One independent session for every read of the test, so that the test stays within the server's connections. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    /**
     * Every row of a table, in key order.
     *
     * @return list<array<string, mixed>>
     */
    private function tableRows(string $table, string $key): array
    {
        return array_values($this->watcher()->table($table)->orderBy($key)->get()->map(static fn (object $row): array => (array) $row)->all());
    }

    /**
     * The facts, with the rows that an adjustment changes: the balances, the valuations and the
     * products, whose versions it never moves.
     *
     * @return array<string, mixed>
     */
    private function factsAndRows(): array
    {
        $counts = [];

        foreach (self::FACTS as $table) {
            $counts[$table] = $this->watcher()->table($table)->count();
        }

        return ['facts' => $counts, 'balances' => $this->tableRows('stock_balances', 'product_id'), 'valuations' => $this->tableRows('inventory_valuations', 'product_id'), 'products' => $this->tableRows('products', 'id')];
    }
}
