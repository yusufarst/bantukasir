<?php

namespace Tests\Feature\Inventory;

use App\Actions\Commands\CommandContext;
use App\Actions\Inventory\ConfirmManualIssue;
use App\Database\CommandPoints;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Operations;
use App\Support\Time\Clock;
use Carbon\CarbonImmutable;
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
 * RC-08 `manual_issue.confirm` through its route (AC-023-01 to AC-023-05; INTERACTION_CONTRACTS
 * RC-08; ROUTE_CONTRACTS section 6; TECHNICAL_RULES sections 7.1, 7.2, 11.1 to 11.3; BR-INV-06
 * to BR-INV-11, BR-INV-18 to BR-INV-22; BR-COST-10 to BR-COST-16; ADR-008 sections 2, 4.1, 4.2
 * and 5; D-060; G-02, G-10, G-11, G-13, G-14; CAP-13; EXECUTION_CONTEXT section 10, a command;
 * suites INV02, FIN01, CMD01, AUTH01, AUTH02; TASK-023, L2 and L4): the commit with each line's
 * movement and its allocation, the fact and its equal projection; the balance that is the sum of
 * the movements; the valuation; the audit event, its allocations the Owner's; the result; the
 * cases of ADR-008 that concern issues; insufficient stock for the lines of a product added
 * together; every refusal, which writes nothing; the fields and the envelope; the failure after
 * every write step; the roles; no Sale, no cost evidence and no other writer.
 */
final class ConfirmManualIssueTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string COMMAND = '/commands/manual_issue.confirm';

    /** The tables that a refused issue leaves as they were. */
    private const array FACTS = ['stock_movements', 'stock_balances', 'manual_issues', 'manual_issue_lines', 'issue_cost_allocations', 'issue_cost_allocation_projections', 'inventory_valuations', 'acquisition_cost_evidence', 'receiving_documents', 'command_identities', 'command_results', 'audit_events'];

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

    public function test_an_issue_posts_each_line_with_its_allocation_balance_valuation_audit_and_result(): void
    {
        // AC-023-01, AC-023-02, AC-023-04: BR-INV-08, BR-INV-18, BR-INV-19, BR-INV-21; ADR-008
        // section 2; G-13, G-14. OPERATIONS_ADMIN records an issue with a product on two lines,
        // each of which posts its own movement and allocation in the order of the lines.
        TokoContoh::stock($this->pen, '10', '100000');
        TokoContoh::stock($this->rice, '4', '30000');
        $this->as('Agus');
        $agus = $this->people['Agus']['id'];
        $before = $this->facts();
        $envelope = $this->envelopeOf([self::line($this->pen, '2'), self::line($this->rice, '1.5'), self::line($this->pen, '3')], ['reasonCode' => 'DAMAGED', 'note' => 'Pecah saat dipindah']);

        $response = $this->confirm($envelope)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed', 'ops');
        $issue = self::number($response->json('result.id'));
        $commandId = self::number($response->json('commandId'));
        $this->assertSame(['manual_issue', '/stock/issues/'.$issue], [$response->json('result.type'), $response->json('result.location')]);

        // The issue exists once it is confirmed, with its reason, note and actor's frozen name.
        $row = (array) $this->watcher()->table('manual_issues')->where('id', $issue)->sole();
        $this->assertSame(['DAMAGED', 'Pecah saat dipindah', 3, $agus, 'Agus', $commandId], [$row['reason_code'], $row['note'], $row['line_count'], $row['actor_user_id'], $row['actor_display_name'], $row['command_id']]);

        // One MANUAL_ISSUE per line: negative, its line's positive quantity, in its product's sequence.
        $movements = $this->watcher()->table('stock_movements', 'm')->join('manual_issue_lines as l', 'l.id', '=', 'm.manual_issue_line_id')
            ->where('m.command_id', $commandId)->orderBy('m.id')
            ->get(['l.manual_issue_id', 'l.line_no', 'l.product_id as line_product', 'l.quantity as line_quantity_of_line', 'm.product_id', 'm.base_unit', 'm.quantity_precision', 'm.product_sequence', 'm.movement_type', 'm.quantity', 'm.line_quantity', 'm.issue_allocation_presence', 'm.id'])
            ->map(static fn (object $movement): array => (array) $movement)->all();
        $movementIds = array_map(static fn (array $movement): int => self::number($movement['id']), $movements);
        $this->assertSame([
            [$issue, 1, $this->pen, '2.000', $this->pen, 'pcs', 0, 2, 'MANUAL_ISSUE', '-2.000', '2.000', $movementIds[0]],
            [$issue, 2, $this->rice, '1.500', $this->rice, 'kg', 3, 2, 'MANUAL_ISSUE', '-1.500', '1.500', $movementIds[1]],
            [$issue, 3, $this->pen, '3.000', $this->pen, 'pcs', 0, 3, 'MANUAL_ISSUE', '-3.000', '3.000', $movementIds[2]],
        ], array_map(static fn (array $movement): array => array_values(array_diff_key($movement, ['id' => true])), $movements));

        // Each movement's allocation fact, at the average before it, and its projection equal to it
        // (G-14): Rp100.000 over 10, two units Rp20.000, then three of the 8 left Rp30.000; the
        // rice's Rp30.000 over 4 kg, 1.5 kg Rp11.250.
        $allocations = $this->allocations($commandId);
        $this->assertSame([
            [$movementIds[0], $this->pen, 'MANUAL_ISSUE', '-2.000', 2, '2.000', null, 'KNOWN', '20000.000000', 1],
            [$movementIds[1], $this->rice, 'MANUAL_ISSUE', '-1.500', 2, '1.500', null, 'KNOWN', '11250.000000', 1],
            [$movementIds[2], $this->pen, 'MANUAL_ISSUE', '-3.000', 3, '3.000', null, 'KNOWN', '30000.000000', 2],
        ], array_map(static fn (array $fact): array => array_slice(array_values($fact), 1, 10), $allocations));
        $this->assertProjectionsEqualTheirFacts();
        $this->assertSame([[2, 'MANUAL_ISSUE'], [2, 'MANUAL_ISSUE'], [3, 'MANUAL_ISSUE']], $this->watcher()->table('issue_cost_allocation_projections as p')->join('issue_cost_allocations as a', 'a.id', '=', 'p.allocation_id')
            ->whereIn('a.movement_id', $movementIds)->orderBy('a.id')->get(['p.publication_no', 'p.issue_type'])->map(static fn (object $item): array => array_values((array) $item))->all());

        // One instant of the server for every fact of the command.
        $at = self::instant($row['confirmed_at']);

        foreach ($this->watcher()->table('stock_movements')->where('command_id', $commandId)->pluck('recorded_at') as $time) {
            $this->assertSame($at, self::instant($time));
        }

        foreach ($allocations as $fact) {
            $this->assertSame($at, self::instant($fact['recorded_at']));
        }

        // The balance: the sum of the product's movements, and its last sequence (G-03).
        $this->assertSame(['5.000', 3], $this->balanceOf($this->pen));
        $this->assertSame(['2.500', 2], $this->balanceOf($this->rice));
        $this->assertBalancesAreTheSumOfTheMovements();

        // The valuation, one publication per movement: the value less each allocation.
        $this->assertSame('KNOWN|50000.000000|3|3', $this->valuationOf($this->pen));
        $this->assertSame('KNOWN|18750.000000|2|2', $this->valuationOf($this->rice));

        // One audit event, with no cost where staff may read (BR-AUD-02, BR-COST-07): the
        // allocations are the Owner's part of it. Two products, so no product filter.
        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['BUSINESS', 'MANUAL_ISSUE_CONFIRM', 'USER', $agus, 'Agus', 'manual_issue', $issue, null], [$event['category'], $event['action'], $event['actor_kind'], $event['actor_user_id'], $event['actor_display_name'], $event['subject_type'], $event['subject_id'], $event['product_id']]);
        $this->assertSame(['lineCount' => 3, 'lines' => [
            ['lineNo' => 1, 'movementId' => $movementIds[0], 'productId' => $this->pen, 'productSequence' => 2, 'quantity' => '2'],
            ['lineNo' => 2, 'movementId' => $movementIds[1], 'productId' => $this->rice, 'productSequence' => 2, 'quantity' => '1.5'],
            ['lineNo' => 3, 'movementId' => $movementIds[2], 'productId' => $this->pen, 'productSequence' => 3, 'quantity' => '3'],
        ], 'note' => 'Pecah saat dipindah', 'reasonCode' => 'DAMAGED'], self::dataOf($event['change_data']));

        foreach (['amount', 'cost', 'KNOWN', 'allocation'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, self::text($event['change_data']), $word);
        }

        $this->assertSame(['lines' => [
            ['allocationId' => $allocations[0]['id'], 'amount' => '20000', 'basedOnPublicationNo' => 1, 'lineNo' => 1, 'status' => 'KNOWN'],
            ['allocationId' => $allocations[1]['id'], 'amount' => '11250', 'basedOnPublicationNo' => 1, 'lineNo' => 2, 'status' => 'KNOWN'],
            ['allocationId' => $allocations[2]['id'], 'amount' => '30000', 'basedOnPublicationNo' => 2, 'lineNo' => 3, 'status' => 'KNOWN'],
        ]], self::dataOf($event['owner_only_data']));

        // The result holds references only, and the receipt is settled.
        $stored = (array) $this->watcher()->table('command_results')->where('command_id', $commandId)->sole();
        $this->assertSame(['manual_issue', $issue, []], [$stored['result_type'], $stored['result_ref'], self::dataOf($stored['result_detail'])]);
        $this->assertSame(['COMMITTED'], $this->outcomesOf($envelope));

        // BR-INV-22: no Sale, no Payment, no receipt and no cost of goods sold — no such table even
        // exists yet — and no cost evidence: an issue only takes value out (BR-INV-21).
        $after = $this->facts();
        $this->assertSame([$before['acquisition_cost_evidence'], $before['receiving_documents']], [$after['acquisition_cost_evidence'], $after['receiving_documents']]);
        $this->assertSame([], $this->watcher()->table('pg_tables')->where('schemaname', 'public')->whereRaw("tablename ~ '^(sale|payment|receipt|refund)'")->pluck('tablename')->all());

        // A movement changes no field of its product, so no version moved.
        $this->assertSame([1, 1], [$this->versionOf($this->pen), $this->versionOf($this->rice)]);

        // A repeat answers the original and writes nothing.
        $facts = $this->factsAndRows();
        $this->confirm($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('commandId', $commandId)->assertJsonPath('result.id', $issue)->assertJsonPath('result.location', '/stock/issues/'.$issue);
        $this->assertSame($facts, $this->factsAndRows());
    }

    public function test_the_issues_of_l_15_and_the_value_rounded_once_follow_adr_008(): void
    {
        // AC-023-02, AC-023-05: COSTING section 4, the L-15 example with its issues; ADR-008
        // section 5 and its test list. 20 units worth Rp240.000: three leave at Rp36.000 — the
        // cost of the example's Sale, by the same rule (BR-COST-10) — then two at Rp24.000.
        TokoContoh::stock($this->pen, '10', '100000');
        TokoContoh::stock($this->pen, '10', '140000');
        $this->as('Hendra');

        $three = self::number($this->confirm($this->envelopeOf([self::line($this->pen, '3')]))->assertStatus(201)->json('commandId'));
        $this->assertSame(['KNOWN', '36000.000000', 2], $this->allocationOf($three));
        $this->assertSame('KNOWN|204000.000000|3|3', $this->valuationOf($this->pen));

        $two = self::number($this->confirm($this->envelopeOf([self::line($this->pen, '2')], ['reasonCode' => 'INTERNAL_USE']))->assertStatus(201)->json('commandId'));
        $this->assertSame(['KNOWN', '24000.000000', 3], $this->allocationOf($two));
        $this->assertSame('KNOWN|180000.000000|4|4', $this->valuationOf($this->pen));
        $this->assertSame(['15.000', 4], $this->balanceOf($this->pen));

        // 84764889.521997 × 28655.202 / 79662.459 rounds once, half-up, to 30490585.681776, which
        // SQL's own scale of a quotient would round twice (ADR-008 section 5).
        TokoContoh::stock($this->rice, '79662.459', '84764889.521997');
        $this->as('Hendra');
        $weighed = self::number($this->confirm($this->envelopeOf([self::line($this->rice, '28655.202')]))->assertStatus(201)->json('commandId'));
        $this->assertSame(['KNOWN', '30490585.681776', 1], $this->allocationOf($weighed));
        $this->assertSame('KNOWN|54274303.840221|2|2', $this->valuationOf($this->rice));
        $this->assertProjectionsEqualTheirFacts();
    }

    public function test_the_issue_that_takes_stock_to_zero_takes_the_whole_value_and_a_new_pool_starts_known(): void
    {
        // AC-023-02: BR-COST-11, BR-COST-15; ADR-008 section 4.1. Three thirds of Rp100: the
        // last takes what is left, so no residue remains.
        TokoContoh::stock($this->pen, '3', '100');
        $this->as('Agus');

        foreach (['33.333333', '33.333334', '33.333333'] as $index => $expected) {
            $command = self::number($this->confirm($this->envelopeOf([self::line($this->pen, '1')], ['reasonCode' => 'SAMPLE']))->assertStatus(201)->json('commandId'));
            $this->assertSame(['KNOWN', $expected, $index + 1], $this->allocationOf($command), (string) $index);
        }

        $this->assertSame(['0.000', 4], $this->balanceOf($this->pen));
        $this->assertSame('KNOWN|0.000000|4|4', $this->valuationOf($this->pen));

        // The next receipt starts a known pool from zero.
        TokoContoh::stock($this->pen, '2', '5000');
        $this->assertSame('KNOWN|5000.000000|5|5', $this->valuationOf($this->pen));
    }

    public function test_an_unknown_valuation_gives_unknown_allocations_never_zero_until_stock_reaches_zero(): void
    {
        // AC-023-02: BR-COST-03, BR-COST-13, BR-INV-21; ADR-008 sections 2 and 4.1.
        TokoContoh::stock($this->pen, '4', '4000');
        TokoContoh::receive([['productId' => $this->pen, 'quantity' => '6', 'cost' => ['status' => 'UNKNOWN']]]);
        $this->as('Agus');

        $partial = self::number($this->confirm($this->envelopeOf([self::line($this->pen, '7')]))->assertStatus(201)->json('commandId'));
        $this->assertSame(['UNKNOWN', null, 2], $this->allocationOf($partial));
        $this->assertSame('UNKNOWN||3|3', $this->valuationOf($this->pen));

        // The last three units: UNKNOWN still, and the pool that follows zero is a known zero.
        $last = self::number($this->confirm($this->envelopeOf([self::line($this->pen, '3')], ['reasonCode' => 'LOST']))->assertStatus(201)->json('commandId'));
        $this->assertSame(['UNKNOWN', null, 3], $this->allocationOf($last));
        $this->assertSame('KNOWN|0.000000|4|4', $this->valuationOf($this->pen));
        $this->assertProjectionsEqualTheirFacts();

        // The UNKNOWN projections carry no amount, never zero.
        $this->assertSame(0, $this->watcher()->table('issue_cost_allocation_projections')->where('current_status', 'UNKNOWN')->whereNotNull('current_amount')->count());
        $this->assertSame(2, $this->watcher()->table('issue_cost_allocation_projections')->where('current_status', 'UNKNOWN')->count());
    }

    public function test_insufficient_stock_refuses_the_whole_issue_for_the_lines_of_each_product_added_together(): void
    {
        // AC-023-01: BR-INV-07, BR-INV-20, G-02. Stock of 5 pens and none of rice: three and
        // three pens are six, and every line of a short product is named with what is available.
        TokoContoh::stock($this->pen, '5', '5000');
        $sugar = TokoContoh::product('GULA-01', ['categoryId' => $this->category, 'name' => 'Gula', 'baseUnit' => 'kg', 'quantityPrecision' => 2]);
        TokoContoh::stock($sugar, '1.25', '25000');
        $this->as('Agus');
        $before = $this->factsAndRows();

        $short = $this->confirm($this->envelopeOf([self::line($this->pen, '3'), self::line($sugar, '1'), self::line($this->pen, '3'), self::line($this->rice, '0.5')]))
            ->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_STOCK')->assertJsonPath('messageKey', 'ux.state.insufficient_stock')->assertJsonPath('outcome', 'REJECTED');
        $this->assertSame([
            '0' => ['productId' => $this->pen, 'available' => '5'],
            '2' => ['productId' => $this->pen, 'available' => '5'],
            '3' => ['productId' => $this->rice, 'available' => '0'],
        ], (array) $short->json('lines'));

        // A warning is never an override: the same lines are refused again, by the Owner too. The
        // channel's allowlist reads a list of lines, as one refused line from the first gives.
        $this->as('Hendra');
        $owner = $this->confirm($this->envelopeOf([self::line($sugar, '1.26')]))->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_STOCK')->assertJsonPath('lines', [['productId' => $sugar, 'available' => '1.25']]);
        $this->assertJsonChannel($owner, 'commands.refused.stock', 'owner');
        $this->assertSame($before, $this->factsAndRows());

        // Exactly the stock, added together across lines, posts and leaves zero.
        $this->as('Agus');
        $this->confirm($this->envelopeOf([self::line($this->pen, '3'), self::line($sugar, '1.25'), self::line($this->pen, '2')]))->assertStatus(201);
        $this->assertSame([['0.000', 3], ['0.000', 2]], [$this->balanceOf($this->pen), $this->balanceOf($sugar)]);
        $this->assertSame(['KNOWN|0.000000|3|3', 'KNOWN|0.000000|2|2'], [$this->valuationOf($this->pen), $this->valuationOf($sugar)]);

        // Nothing is left for one more.
        $this->confirm($this->envelopeOf([self::line($this->pen, '1')]))->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_STOCK')->assertJsonPath('lines', [['productId' => $this->pen, 'available' => '0']]);
    }

    public function test_each_refusal_under_the_locks_names_its_lines_in_the_order_of_rc_08_and_writes_nothing(): void
    {
        // INTERACTION_CONTRACTS RC-08, "Under locks"; TR-VAL-08; BR-INV-06, BR-INV-11: a product
        // that does not exist, active, not blocked, the version reviewed, the precision, then the
        // stock.
        $sugar = TokoContoh::product('GULA-01', ['categoryId' => $this->category, 'name' => 'Gula', 'baseUnit' => 'kg', 'quantityPrecision' => 2]);
        TokoContoh::stock($sugar, '9', '9000');
        TokoContoh::postingBlock($sugar);
        $sweet = TokoContoh::product('MANIS-01', ['categoryId' => $this->category, 'name' => 'Pemanis', 'baseUnit' => 'kg', 'quantityPrecision' => 2]);
        TokoContoh::stock($sweet, '9', '9000');
        TokoContoh::stock($this->pen, '9', '9000');
        TokoContoh::stock($this->rice, '9', '9000');
        $this->as('Agus');
        $this->command('product.deactivate', ['productId' => $this->rice], ['productVersion' => 1])->assertStatus(201);
        $before = $this->factsAndRows();

        $missing = $this->confirm($this->envelopeOf([self::line($this->rice, '1'), self::line(999999, '1')], [], [['productId' => $this->rice, 'version' => 2], ['productId' => 999999, 'version' => 1]]))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->assertJsonChannel($missing, 'commands.refused', 'ops');

        $this->confirm($this->envelopeOf([self::line($this->pen, '1'), self::line($this->rice, '1')]))->assertStatus(422)->assertJsonPath('code', 'PRODUCT_INACTIVE')->assertJsonPath('lines', [1 => ['productId' => $this->rice]]);
        $this->confirm($this->envelopeOf([self::line($this->pen, '1'), self::line($sugar, '1')], [], [['productId' => $this->pen, 'version' => 2], ['productId' => $sugar, 'version' => 2]]))->assertStatus(422)->assertJsonPath('code', 'POSTING_BLOCKED')->assertJsonPath('lines', [1 => ['productId' => $sugar]]);
        $this->confirm($this->envelopeOf([self::line($this->pen, '1'), self::line($sweet, '1')], [], [['productId' => $sweet, 'version' => 1], ['productId' => $this->pen, 'version' => 2]]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION')->assertJsonPath('lines', [0 => ['productId' => $this->pen]]);

        // The precision before the stock: a quantity too precise for its product is refused at its
        // field, even when the stock would not suffice.
        $precision = $this->confirm($this->envelopeOf([self::line($this->pen, '1.5'), self::line($sweet, '100.234')]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame([
            'payload.lines.0.quantity' => [['key' => 'ux.validation.quantity_whole', 'params' => []]],
            'payload.lines.1.quantity' => [['key' => 'ux.validation.quantity_decimals', 'params' => ['desimal' => '2']]],
        ], $precision->json('fields'));

        $this->assertSame($before, $this->factsAndRows());

        // The onboarding freeze, before every product (G-10).
        $this->runtime()->table('onboarding_state')->where('id', 1)->update(['state' => 'ACTIVE', 'version' => 2]);
        $frozen = $this->confirm($this->envelopeOf([self::line($this->pen, '1')]))->assertStatus(422)->assertJsonPath('code', 'ONBOARDING_FREEZE')->assertJsonPath('messageKey', 'ux.state.onboarding');
        $this->assertJsonChannel($frozen, 'commands.refused', 'ops');
        $this->runtime()->table('onboarding_state')->where('id', 1)->update(['state' => 'NOT_STARTED', 'version' => 3]);
        $this->assertSame($before, $this->factsAndRows());

        // The lines of products that may post, at their precision, post.
        $this->confirm($this->envelopeOf([self::line($this->pen, '1'), self::line($sweet, '1.23')]))->assertStatus(201);
        $this->assertSame(['7.770', 2], $this->balanceOf($sweet));
    }

    public function test_the_reason_is_one_of_the_five_and_the_fields_are_validated_with_their_keys_before_any_lock(): void
    {
        // AC-023-01: BR-INV-19; TR-VAL-01 to TR-VAL-07; TECHNICAL_RULES sections 11.2 and 11.3;
        // COPY_DECK section 16.1.
        TokoContoh::stock($this->pen, '300', '30000');
        $line = self::line($this->pen, '1');
        $before = $this->factsAndRows();

        foreach ([
            [['reasonCode' => null], 'payload.reasonCode', 'ux.validation.required', []],
            [['reasonCode' => 'SALE'], 'payload.reasonCode', 'ux.validation.choose_one', []],
            [['reasonCode' => 'damaged'], 'payload.reasonCode', 'ux.validation.choose_one', []],
            [['reasonCode' => 7], 'payload.reasonCode', 'ux.validation.choose_one', []],
            [['note' => str_repeat('c', 1001)], 'payload.note', 'ux.validation.text_max', ['maks' => 1000]],
            [['lines' => []], 'payload.lines', 'ux.validation.lines_min', []],
            [['lines' => null], 'payload.lines', 'ux.validation.lines_min', []],
            [['lines' => [[...$line, 'productId' => 0]]], 'payload.lines.0.productId', 'ux.validation.choose_one', []],
            [['lines' => [[...$line, 'productId' => null]]], 'payload.lines.0.productId', 'ux.validation.required', []],
            [['lines' => [[...$line, 'quantity' => '0']]], 'payload.lines.0.quantity', 'ux.validation.quantity_positive', []],
            [['lines' => [[...$line, 'quantity' => '-1']]], 'payload.lines.0.quantity', 'ux.validation.quantity_positive', []],
            [['lines' => [[...$line, 'quantity' => 1]]], 'payload.lines.0.quantity', 'ux.validation.quantity_positive', []],
            [['lines' => [[...$line, 'quantity' => '1.2345']]], 'payload.lines.0.quantity', 'ux.validation.quantity_decimals', ['desimal' => '3']],
            [['lines' => [[...$line, 'quantity' => '100000000']]], 'payload.lines.0.quantity', 'ux.validation.quantity_max', []],
            [['lines' => [[...$line, 'quantity' => null]]], 'payload.lines.0.quantity', 'ux.validation.required', []],
            [['lines' => array_fill(0, 201, $line)], 'payload.lines', 'ux.validation.lines_max', ['maks' => ConfirmManualIssue::MAX_LINES]],
        ] as $index => [$payload, $path, $key, $params]) {
            $this->as('Agus');
            $refused = $this->confirmPayload([...['reasonCode' => 'DAMAGED', 'lines' => [$line]], ...$payload])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $fields = (array) $refused->json('fields');
            $this->assertSame(['key' => $key, 'params' => $params], ((array) ($fields[$path] ?? []))[0] ?? null, sprintf('case %d at %s', $index, $path));
        }

        // A missing reason, refused like any field; and what no page sends, with no key of a field.
        $this->as('Agus');
        $absent = $this->confirm($this->envelope(['lines' => [$line]], ['operation' => ConfirmManualIssue::OPERATION, 'expected' => ['products' => [['productId' => $this->pen, 'version' => 1]]]]))->assertStatus(422);
        $this->assertSame('ux.validation.required', self::firstKey($absent, 'payload.reasonCode'));

        foreach ([
            // A control character inside the note; the framework's trimming removes an outer one.
            ['note' => "Pecah\nlagi"],
            ['note' => 7],
            ['lines' => 'x'],
            ['lines' => ['x']],
            ['lines' => [[...$line, 'productId' => (string) $this->pen]]],
            ['lines' => [[...$line, 'cost' => ['status' => 'KNOWN', 'amount' => '1000']]]],
            ['lines' => [[...$line, 'reason' => 'DAMAGED']]],
            ['supplier' => 'Toko Lain'],
            ['saleId' => 1],
            ['lines' => [0 => $line, 2 => $line]],
        ] as $payload) {
            $this->as('Agus');
            $this->confirmPayload([...['reasonCode' => 'DAMAGED', 'lines' => [$line]], ...$payload])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // The versions reviewed name each product of the lines once, as whole numbers.
        foreach ([[], [['productId' => $this->rice, 'version' => 1]], [['productId' => $this->pen, 'version' => 1], ['productId' => $this->pen, 'version' => 1]], [['productId' => $this->pen, 'version' => '1']], [['productId' => $this->pen]]] as $expected) {
            $this->as('Agus');
            $this->confirm($this->envelopeOf([$line], [], $expected))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($before, $this->factsAndRows());
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', ConfirmManualIssue::OPERATION)->where('outcome', '<>', 'REJECTED')->count());

        // Each of the five reasons, the longest note and 200 lines are kept whole.
        foreach (['DAMAGED', 'INTERNAL_USE', 'LOST', 'SAMPLE', 'OTHER'] as $reason) {
            $this->as('Agus');
            $this->confirmPayload(['reasonCode' => $reason, 'note' => $reason === 'OTHER' ? str_repeat('c', 1000) : null, 'lines' => [$line]])->assertStatus(201);
        }

        $this->assertSame(['DAMAGED', 'INTERNAL_USE', 'LOST', 'OTHER', 'SAMPLE'], $this->watcher()->table('manual_issues')->orderBy('reason_code')->pluck('reason_code')->all());
        $this->assertSame(1000, mb_strlen(self::text($this->watcher()->table('manual_issues')->where('reason_code', 'OTHER')->value('note'))));
        $this->as('Agus');
        $commandId = self::number($this->confirmPayload(['reasonCode' => 'LOST', 'lines' => array_fill(0, ConfirmManualIssue::MAX_LINES, $line)])->assertStatus(201)->json('commandId'));
        $this->assertSame(range(7, 206), $this->watcher()->table('stock_movements')->where('command_id', $commandId)->orderBy('id')->pluck('product_sequence')->all());
        $this->assertSame(200, $this->watcher()->table('issue_cost_allocations')->join('stock_movements as m', 'm.id', '=', 'issue_cost_allocations.movement_id')->where('m.command_id', $commandId)->count());
        $this->assertSame(['95.000', 206], $this->balanceOf($this->pen));
        $this->assertSame('KNOWN|9500.000000|206|206', $this->valuationOf($this->pen));
        $this->assertBalancesAreTheSumOfTheMovements();
    }

    public function test_an_envelope_is_refused_as_every_command_refuses_one_and_a_repeat_or_a_status_check_recovers_the_result(): void
    {
        // CMD01; G-12, G-25; RC-50; ADR-007 section 3; RACE-01 and RACE-02 in sequence (their
        // races are ManualIssueRaceTest's).
        TokoContoh::stock($this->pen, '10', '10000');
        $this->as('Agus');
        $envelope = $this->envelopeOf([self::line($this->pen, '3')]);
        $before = $this->factsAndRows();

        foreach ([['version' => 2], ['tambahan' => 'x'], ['operation' => 'receiving.confirm'], ['expected' => ['products' => [['productId' => $this->pen, 'version' => 1.5]]]]] as $changes) {
            $this->confirm([...$envelope, 'idempotencyKey' => (string) Str::uuid(), ...$changes])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->confirm([...$envelope, 'sourceIntentId' => (string) Str::uuid(), 'idempotencyKey' => (string) Str::uuid(), 'recoveryEpoch' => str_repeat('ab', 32)])->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE')->assertJsonPath('outcome', 'HELD');
        $this->assertSame($before, $this->factsAndRows());

        // The command runs once; a repeat answers the original; another payload is held.
        $issue = self::number($this->confirm($envelope)->assertStatus(201)->json('result.id'));
        $facts = $this->factsAndRows();
        $other = ['reasonCode' => 'DAMAGED', 'lines' => [self::line($this->pen, '4')]];

        $this->confirm($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $issue);
        $this->confirm([...$envelope, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $issue);

        foreach ([[...$envelope, 'payload' => $other], [...$envelope, 'idempotencyKey' => (string) Str::uuid(), 'payload' => $other]] as $conflicting) {
            $this->confirm($conflicting)->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT')->assertJsonPath('outcome', 'HELD');
        }

        $this->assertSame($facts, $this->factsAndRows());
        $this->assertSame(['7.000', 2], $this->balanceOf($this->pen));

        // A status check recovers the original result.
        $resolved = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.id', $issue)->assertJsonPath('result.location', '/stock/issues/'.$issue);
        $this->assertJsonChannel($resolved, 'commands.resolve', 'ops');
    }

    public function test_a_failure_after_every_write_step_leaves_no_fact_and_the_receipt_pending(): void
    {
        // AC-023-04, TS-21: two products and three lines write 16 steps — each product's balance,
        // each product's valuation, the issue, each line's row, movement and allocation with its
        // projection, the audit event, the result.
        TokoContoh::stock($this->pen, '20', '20000');
        TokoContoh::stock($this->rice, '5', '5000');
        $lines = [self::line($this->pen, '2'), self::line($this->rice, '1.5'), self::line($this->pen, '3')];
        $this->as('Agus');

        for ($step = 1; $step <= 16; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $before = $this->factsAndRows();

            $this->confirm($this->envelopeOf($lines))->assertStatus(500);

            $this->assertSame($before, $this->factsAndRows(), sprintf('after write step %d', $step));
            $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', ConfirmManualIssue::OPERATION)->orderByDesc('id')->value('outcome'), (string) $step);
        }

        // The step after the last is no step of the command: it commits, through the locks of
        // TR §7.1 in their order.
        $points = new FailurePoints(17);
        $this->app->instance(CommandPoints::class, $points);
        $committed = $this->confirm($this->envelopeOf($lines))->assertStatus(201);
        $this->assertSame(1, $this->watcher()->table('command_results')->where('command_id', $committed->json('commandId'))->count());
        $this->assertSame(['after_lock:2.1', 'after_lock:2.3', 'after_lock:6', 'after_lock:7', ...array_map(static fn (int $step): string => 'after_write:'.$step, range(1, 16))], array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))));
        $this->app->instance(CommandPoints::class, new FailurePoints);

        $this->assertSame(['15.000', 3], $this->balanceOf($this->pen));
        $this->assertSame(['3.500', 2], $this->balanceOf($this->rice));
        $this->assertSame('KNOWN|15000.000000|3|3', $this->valuationOf($this->pen));
        $this->assertProjectionsEqualTheirFacts();
        $this->assertBalancesAreTheSumOfTheMovements();
    }

    public function test_only_cap_13_passes_the_gate_and_the_guard_in_the_transaction_holds_too(): void
    {
        // CAP-13: the Owner, with the explicit entry of D-018, OPERATIONS_ADMIN and the combined
        // account; SEC-AZ-04.
        TokoContoh::stock($this->pen, '10', '10000');

        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $response = $this->confirm($this->envelopeOf([self::line($this->pen, '1')]))->assertStatus(201);
            $this->assertJsonChannel($response, 'commands.committed', $actor);
            $this->assertSame($this->people[$person]['id'], $this->watcher()->table('manual_issues')->where('id', $response->json('result.id'))->value('actor_user_id'), $person);
        }

        $before = $this->factsAndRows();
        $this->as('Rina');
        $this->confirm($this->envelopeOf([self::line($this->pen, '1')]))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', ConfirmManualIssue::OPERATION)->where('actor_user_id', $this->people['Rina']['id'])->count());

        // The Action refuses a cashier even without the route's gate.
        $envelope = new Envelope(ConfirmManualIssue::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, ['products' => [['productId' => $this->pen, 'version' => 1]]], ['reasonCode' => 'DAMAGED', 'lines' => [self::line($this->pen, '1')]]);

        try {
            app(ConfirmManualIssue::class)->execute(new CommandContext($this->people['Rina']['id'], $envelope, app(Clock::class)->now()));
            $this->fail('A cashier passed the guard.');
        } catch (ActorNotAllowed) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($before, $this->factsAndRows());

        $this->browse(null);
        $this->confirm($this->envelopeOf([self::line($this->pen, '1')]))->assertStatus(401);
        $this->assertSame($before, $this->factsAndRows());
    }

    public function test_each_table_of_the_issue_and_its_allocation_has_its_one_writer(): void
    {
        // STK-12, ARC-20, ARC-21, G-14: the issue's tables are named by the Inventory module's
        // writer and its reads; the allocation's by the Costing module's writer alone, which inserts
        // the projection with its fact; nothing else updates a projection (ADR-008 section 4.3).
        $this->assertSame(['Domain/Inventory/ManualIssues.php', 'Queries/Inventory/ManualIssueRecords.php', 'Queries/Inventory/MovementDetail.php', 'Queries/Inventory/StockHistory.php'], self::filesNaming('manual_issues'));
        $this->assertSame(['Domain/Inventory/ManualIssues.php', 'Queries/Inventory/ManualIssueRecords.php', 'Queries/Inventory/MovementDetail.php', 'Queries/Inventory/StockHistory.php'], self::filesNaming('manual_issue_lines'));
        $this->assertSame(['Domain/Costing/Allocations/IssueAllocations.php'], self::filesNaming('issue_cost_allocations'));
        $this->assertSame(['Domain/Costing/Allocations/IssueAllocations.php'], self::filesNaming('issue_cost_allocation_projections'));
        $this->assertDoesNotMatchRegularExpression('/->(?:update|upsert|delete|increment|decrement|truncate)\(/', (string) file_get_contents(app_path('Domain/Costing/Allocations/IssueAllocations.php')));
        $this->assertDoesNotMatchRegularExpression('/->(?:insert|insertGetId|update|upsert|delete|increment|decrement|truncate)\(/', (string) file_get_contents(app_path('Queries/Inventory/ManualIssueRecords.php')));
        $this->assertSame([ConfirmManualIssue::OPERATION], array_values(array_filter(array_keys(Operations::FAMILIES), static fn (string $operation): bool => str_starts_with($operation, 'manual_issue'))));
    }

    /**
     * A line of RC-08.
     *
     * @return array<string, mixed>
     */
    private static function line(int $product, string $quantity): array
    {
        return ['productId' => $product, 'quantity' => $quantity];
    }

    /**
     * An envelope of RC-08 for these lines, with a reason and the version of each of their
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

        return $this->envelope(['reasonCode' => 'DAMAGED', 'note' => null, 'lines' => $lines, ...$payload], ['operation' => ConfirmManualIssue::OPERATION, 'expected' => ['products' => $expected]]);
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
     * RC-08 with this payload, with the versions of the products that its lines name, or of the
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

        return $this->confirm($this->envelope($payload, ['operation' => ConfirmManualIssue::OPERATION, 'expected' => ['products' => $expected]]));
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
     * The allocation facts of a command's movements, in their order, every column.
     *
     * @return list<array<string, mixed>>
     */
    private function allocations(int $commandId): array
    {
        return array_values($this->watcher()->table('issue_cost_allocations as a')->join('stock_movements as m', 'm.id', '=', 'a.movement_id')
            ->where('m.command_id', $commandId)->orderBy('a.id')
            ->get(['a.id', 'a.movement_id', 'a.product_id', 'a.issue_type', 'a.movement_quantity', 'a.product_sequence', 'a.issued_quantity', 'a.sale_line_id', 'a.at_commit_status', 'a.at_commit_amount', 'a.based_on_publication_no', 'a.recorded_at'])
            ->map(static fn (object $row): array => (array) $row)->all());
    }

    /**
     * The one allocation of a command of one line: its status, amount and the publication it was
     * based on.
     *
     * @return list<mixed>
     */
    private function allocationOf(int $commandId): array
    {
        $facts = $this->allocations($commandId);
        $this->assertCount(1, $facts);

        return [$facts[0]['at_commit_status'], $facts[0]['at_commit_amount'], $facts[0]['based_on_publication_no']];
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
     * The first message key of a field of a refusal.
     *
     * @param  TestResponse<Response>  $response
     */
    private static function firstKey(TestResponse $response, string $path): ?string
    {
        $fields = $response->json('fields');
        $entries = is_array($fields) ? ($fields[$path] ?? null) : null;
        $first = is_array($entries) ? ($entries[0] ?? null) : null;
        $key = is_array($first) ? ($first['key'] ?? null) : null;

        return is_string($key) ? $key : null;
    }

    /**
     * An instant read from the database, in UTC with its microseconds.
     */
    private static function instant(mixed $value): string
    {
        return CarbonImmutable::parse(self::text($value))->utc()->format('Y-m-d\TH:i:s.u\Z');
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
     * @return array<string, int>
     */
    private function facts(): array
    {
        $counts = [];

        foreach (self::FACTS as $table) {
            $counts[$table] = $this->watcher()->table($table)->count();
        }

        return $counts;
    }

    /**
     * The facts, with the rows that an issue changes: the balances, the valuations and the
     * products, whose versions it never moves.
     *
     * @return array<string, mixed>
     */
    private function factsAndRows(): array
    {
        return ['facts' => $this->facts(), 'balances' => $this->tableRows('stock_balances', 'product_id'), 'valuations' => $this->tableRows('inventory_valuations', 'product_id'), 'products' => $this->tableRows('products', 'id')];
    }
}
