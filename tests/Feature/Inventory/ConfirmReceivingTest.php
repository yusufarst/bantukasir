<?php

namespace Tests\Feature\Inventory;

use App\Actions\Commands\CommandContext;
use App\Actions\Inventory\ConfirmReceiving;
use App\Database\CommandPoints;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Catalog\Products;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Operations;
use App\Domain\Costing\InventoryValuations;
use App\Domain\Costing\ProductValuation;
use App\Http\Commands\ReceivedAt;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Limits;
use App\Support\Time\Clock;
use App\Support\Time\TestClock;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use FilesystemIterator;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
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
use Tests\Harness\Http\RouteInventory;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-07 `receiving.confirm` through its route (AC-021-01 to AC-021-07; INTERACTION_CONTRACTS
 * RC-07; ROUTE_CONTRACTS section 6; TECHNICAL_RULES sections 7.1, 7.2, 11.1 to 11.3;
 * BR-INV-02 to BR-INV-17, BR-INV-45; BR-COST-02 to BR-COST-15; ADR-008 sections 4.1, 4.2 and 5;
 * D-033, D-047, D-069 (a); G-02, G-03, G-10, G-11; CAP-11; EXECUTION_CONTEXT section 10, a
 * command; suites INV01, FIN01, FIN02, CMD01, AUTH01, AUTH02; TASK-021, L2 and L4): the commit
 * with each line's movement and evidence, the balance that is the sum of the movements, the
 * valuation, the audit event and the result; KNOWN, UNKNOWN and the Owner's explicit zero; the
 * server's time and the posting order, whatever the document's date; every refusal, which writes
 * nothing; the fields and the envelope; the ranges; the failure after every write step; the roles;
 * and no draft and no other writer of the projections.
 */
final class ConfirmReceivingTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string COMMAND = '/commands/receiving.confirm';

    /** The tables that a refused confirmation leaves as they were. */
    private const array FACTS = ['stock_movements', 'stock_balances', 'receiving_documents', 'receiving_lines', 'acquisition_cost_evidence', 'inventory_valuations', 'command_identities', 'command_results', 'audit_events'];

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

    public function test_a_confirmation_posts_each_line_with_its_evidence_balance_valuation_audit_and_result(): void
    {
        // AC-021-01, AC-021-02, AC-021-04: BR-INV-02, BR-INV-03, BR-INV-05, BR-INV-08, BR-INV-12,
        // BR-INV-16; BR-COST-02, BR-COST-03, BR-COST-09; D-047. OPERATIONS_ADMIN confirms, with a
        // product on two lines, each of which posts its own movement in the order of the lines.
        $this->as('Agus');
        $agus = $this->people['Agus']['id'];
        $envelope = $this->envelopeOf([
            self::line($this->pen, '10', self::known('100000')),
            self::line($this->rice, '2.5', self::unknown()),
            self::line($this->pen, '10', self::known('140000')),
        ], ['reference' => 'SJ-0012', 'documentDate' => '2026-10-15']);

        $response = $this->confirm($envelope)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed', 'ops');
        $document = self::number($response->json('result.id'));
        $commandId = self::number($response->json('commandId'));
        $this->assertSame(['receiving_document', '/stock/receivings/'.$document], [$response->json('result.type'), $response->json('result.location')]);

        // The document exists once it is confirmed, with its actor's name frozen (SCHEMA section 3.5).
        $row = (array) $this->watcher()->table('receiving_documents')->where('id', $document)->sole();
        $this->assertSame(['SJ-0012', '2026-10-15', 3, $agus, 'Agus', $commandId], [$row['reference'], $row['document_date'], $row['line_count'], $row['actor_user_id'], $row['actor_display_name'], $row['command_id']]);

        // One RECEIPT per line, in its product's sequence, with the unit and precision it posted in.
        $movements = $this->watcher()->table('stock_movements', 'm')->join('receiving_lines as l', 'l.id', '=', 'm.receiving_line_id')
            ->where('m.command_id', $commandId)->orderBy('m.id')
            ->get(['l.receiving_document_id', 'l.line_no', 'l.product_id as line_product', 'l.quantity as line_quantity_of_line', 'm.product_id', 'm.base_unit', 'm.quantity_precision', 'm.product_sequence', 'm.movement_type', 'm.quantity', 'm.line_quantity', 'm.actor_user_id', 'm.id'])
            ->map(static fn (object $movement): array => (array) $movement)->all();
        $this->assertSame([
            [$document, 1, $this->pen, '10.000', $this->pen, 'pcs', 0, 1, 'RECEIPT', '10.000', '10.000', $agus],
            [$document, 2, $this->rice, '2.500', $this->rice, 'kg', 3, 1, 'RECEIPT', '2.500', '2.500', $agus],
            [$document, 3, $this->pen, '10.000', $this->pen, 'pcs', 0, 2, 'RECEIPT', '10.000', '10.000', $agus],
        ], array_map(static fn (array $movement): array => array_values(array_diff_key($movement, ['id' => true])), $movements));
        $movementIds = array_map(static fn (array $movement): int => self::number($movement['id']), $movements);

        // Each movement's evidence, KNOWN with its amount or UNKNOWN with none, never zero.
        $evidence = $this->watcher()->table('acquisition_cost_evidence')->where('command_id', $commandId)->orderBy('id')
            ->get(['movement_id', 'product_id', 'movement_type', 'quantity', 'cost_status', 'total_amount', 'zero_cost_note', 'reference', 'actor_user_id', 'actor_display_name', 'id'])
            ->map(static fn (object $item): array => (array) $item)->all();
        $this->assertSame([
            [$movementIds[0], $this->pen, 'RECEIPT', '10.000', 'KNOWN', '100000.000000', null, 'SJ-0012', $agus, 'Agus'],
            [$movementIds[1], $this->rice, 'RECEIPT', '2.500', 'UNKNOWN', null, null, 'SJ-0012', $agus, 'Agus'],
            [$movementIds[2], $this->pen, 'RECEIPT', '10.000', 'KNOWN', '140000.000000', null, 'SJ-0012', $agus, 'Agus'],
        ], array_map(static fn (array $item): array => array_values(array_diff_key($item, ['id' => true])), $evidence));

        // One instant of the server for every fact of the command.
        $at = self::instant($row['confirmed_at']);

        foreach (['stock_movements' => 'recorded_at', 'acquisition_cost_evidence' => 'recorded_at', 'audit_events' => 'occurred_at'] as $table => $column) {
            foreach ($this->watcher()->table($table)->where('command_id', $commandId)->pluck($column) as $time) {
                $this->assertSame($at, self::instant($time), $table);
            }
        }

        // The balance: the sum of the product's movements, and its last sequence (AC-021-01; G-03).
        $this->assertSame([[$this->pen, '20.000', 2, $at], [$this->rice, '2.500', 1, $at]], $this->balances());
        $this->assertBalancesAreTheSumOfTheMovements();

        // The valuation, one publication per movement: 10 for Rp100.000 and 10 for Rp140.000; the
        // UNKNOWN cost makes the other product's value UNKNOWN, never zero (BR-COST-13).
        $this->assertSame([[$this->pen, 'KNOWN', '240000.000000', 2, 2, $at], [$this->rice, 'UNKNOWN', null, 1, 1, $at]], $this->valuations());

        // One audit event of the document, with no cost where staff may read (BR-AUD-02,
        // BR-COST-07): the costs are the Owner's part of it. Two products, so no product filter.
        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['BUSINESS', 'RECEIVING_CONFIRM', 'USER', $agus, 'Agus', 'receiving_document', $document, null, 'SJ-0012'], [$event['category'], $event['action'], $event['actor_kind'], $event['actor_user_id'], $event['actor_display_name'], $event['subject_type'], $event['subject_id'], $event['product_id'], $event['reference']]);
        $this->assertSame(['documentDate' => '2026-10-15', 'lineCount' => 3, 'lines' => [
            ['lineNo' => 1, 'movementId' => $movementIds[0], 'productId' => $this->pen, 'productSequence' => 1, 'quantity' => '10'],
            ['lineNo' => 2, 'movementId' => $movementIds[1], 'productId' => $this->rice, 'productSequence' => 1, 'quantity' => '2.5'],
            ['lineNo' => 3, 'movementId' => $movementIds[2], 'productId' => $this->pen, 'productSequence' => 2, 'quantity' => '10'],
        ]], self::dataOf($event['change_data']));

        foreach (['amount', 'cost', 'KNOWN', 'zero'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, self::text($event['change_data']), $word);
        }

        $this->assertSame(['lines' => [
            ['amount' => '100000', 'evidenceId' => $evidence[0]['id'], 'lineNo' => 1, 'status' => 'KNOWN', 'zeroNote' => null],
            ['amount' => null, 'evidenceId' => $evidence[1]['id'], 'lineNo' => 2, 'status' => 'UNKNOWN', 'zeroNote' => null],
            ['amount' => '140000', 'evidenceId' => $evidence[2]['id'], 'lineNo' => 3, 'status' => 'KNOWN', 'zeroNote' => null],
        ]], self::dataOf($event['owner_only_data']));

        // The result holds references only (SCHEMA section 5.3), and the receipt is settled.
        $stored = (array) $this->watcher()->table('command_results')->where('command_id', $commandId)->sole();
        $this->assertSame(['receiving_document', $document, []], [$stored['result_type'], $stored['result_ref'], self::dataOf($stored['result_detail'])]);
        $this->assertSame(['COMMITTED'], $this->outcomesOf($envelope));

        // A movement changes no field of its product, so no version moved.
        $this->assertSame([1, 1], [$this->versionOf($this->pen), $this->versionOf($this->rice)]);

        // A repeat answers the original and writes nothing.
        $facts = $this->factsAndRows();
        $this->confirm($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('commandId', $commandId)->assertJsonPath('result.id', $document)->assertJsonPath('result.location', '/stock/receivings/'.$document);
        $this->assertSame($facts, $this->factsAndRows());
    }

    public function test_the_receipts_of_l_15_value_twenty_units_at_rp240000_and_unknown_cost_stays_unknown_while_stock_remains(): void
    {
        // AC-021-04: COSTING section 4, the L-15 example; ADR-008 sections 4.1, 4.2 and 5; BR-COST-09,
        // BR-COST-12, BR-COST-13, BR-COST-15. Each document is one publication of the valuation.
        $this->as('Hendra');
        $this->confirm($this->envelopeOf([self::line($this->pen, '10', self::known('100000'))]))->assertStatus(201);
        $this->assertSame('KNOWN|100000.000000|1|1', $this->valuationOf($this->pen));

        $this->confirm($this->envelopeOf([self::line($this->pen, '10', self::known('140000'))]))->assertStatus(201);
        $this->assertSame('KNOWN|240000.000000|2|2', $this->valuationOf($this->pen));
        $this->assertSame(['20.000', 2], $this->balanceOf($this->pen));

        // The average, Rp12.000, is one division of the exact sums, rounded once.
        $value = $this->watcher()->table('inventory_valuations')->where('product_id', $this->pen)->value('value');
        $this->assertSame('12000', Cost::quotient(Cost::fromDatabase(self::text($value), 'KNOWN')->amount(), BigDecimal::of('20'))->toCanonical());

        // Stock with UNKNOWN cost makes the value UNKNOWN; a later KNOWN receipt leaves it so while
        // stock remains, since only stock at zero starts a new pool.
        $this->confirm($this->envelopeOf([self::line($this->pen, '5', self::unknown())]))->assertStatus(201);
        $this->assertSame('UNKNOWN||3|3', $this->valuationOf($this->pen));
        $this->confirm($this->envelopeOf([self::line($this->pen, '5', self::known('60000'))]))->assertStatus(201);
        $this->assertSame('UNKNOWN||4|4', $this->valuationOf($this->pen));

        // The UNKNOWN evidence keeps its status and no amount; the KNOWN ones keep theirs.
        $this->assertSame([['KNOWN', '100000.000000'], ['KNOWN', '140000.000000'], ['UNKNOWN', null], ['KNOWN', '60000.000000']], $this->watcher()->table('acquisition_cost_evidence')->where('product_id', $this->pen)->orderBy('id')->get(['cost_status', 'total_amount'])->map(static fn (object $row): array => array_values((array) $row))->all());
        $this->assertSame(['30.000', 4], $this->balanceOf($this->pen));
        $this->assertBalancesAreTheSumOfTheMovements();

        // One document with a KNOWN and an UNKNOWN line of a new product: UNKNOWN from its start.
        $this->confirm($this->envelopeOf([self::line($this->rice, '1', self::known('15000')), self::line($this->rice, '2', self::unknown())]))->assertStatus(201);
        $this->assertSame('UNKNOWN||2|2', $this->valuationOf($this->rice));
    }

    public function test_an_explicit_zero_needs_its_evidence_or_reason_and_is_the_owners_alone(): void
    {
        // AC-021-02: BR-COST-04, BR-INV-13; INFORMATION_ARCHITECTURE section 14; evidence reading 3.
        $this->as('Hendra');
        $before = $this->factsAndRows();

        // Without its evidence or reason, refused at its field, for the Owner too.
        foreach (['0', '0.000000'] as $zero) {
            $refused = $this->confirm($this->envelopeOf([self::line($this->pen, '1', self::known($zero))]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $this->assertSame('ux.validation.zero_evidence', self::firstKey($refused, 'payload.lines.0.cost.zeroEvidence'), $zero);
        }

        // Evidence goes with a zero alone, and UNKNOWN takes neither an amount nor evidence.
        foreach ([[...self::known('1000'), 'zeroEvidence' => 'Bukan nol'], [...self::unknown(), 'zeroEvidence' => 'Tidak diketahui'], [...self::unknown(), 'amount' => '0']] as $cost) {
            $this->confirm($this->envelopeOf([self::line($this->pen, '1', $cost)]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // Staff are refused at the amount, under the actor's lock, even with the evidence: they
        // mark the cost unknown, and the Owner completes it.
        foreach (['Agus', 'Dewi'] as $person) {
            $this->as($person);
            $refused = $this->confirm($this->envelopeOf([self::line($this->rice, '1', self::known('1000')), self::line($this->pen, '1', [...self::known('0'), 'zeroEvidence' => 'Hadiah pemasok'])]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $this->assertSame(['payload.lines.1.cost.amount' => [['key' => 'costing.field.zero_disabled', 'params' => []]]], $refused->json('fields'), $person);
        }

        $this->assertSame($before, $this->factsAndRows());

        // The Owner, with the evidence: a KNOWN zero kept with its note, never an UNKNOWN.
        $this->as('Hendra');
        $commandId = self::number($this->confirm($this->envelopeOf([self::line($this->pen, '4', [...self::known('0'), 'zeroEvidence' => 'Hadiah pemasok, nota 7'])]))->assertStatus(201)->json('commandId'));
        $evidence = (array) $this->watcher()->table('acquisition_cost_evidence')->where('command_id', $commandId)->sole();
        $this->assertSame(['KNOWN', '0.000000', 'Hadiah pemasok, nota 7'], [$evidence['cost_status'], $evidence['total_amount'], $evidence['zero_cost_note']]);
        $this->assertSame('KNOWN|0.000000|1|1', $this->valuationOf($this->pen));
        $owner = self::dataOf($this->watcher()->table('audit_events')->where('command_id', $commandId)->value('owner_only_data'));
        $this->assertSame(['amount' => '0', 'evidenceId' => $evidence['id'], 'lineNo' => 1, 'status' => 'KNOWN', 'zeroNote' => 'Hadiah pemasok, nota 7'], ((array) ($owner['lines'] ?? []))[0] ?? null);
    }

    public function test_a_document_date_in_the_past_or_the_future_moves_no_fact(): void
    {
        // AC-021-07: REQ-INV-13, BR-INV-05; evidence reading 13. The time of every fact is the
        // server's, here a clock that stands still, and the order is the posting order.
        $this->as('Agus');
        $clock = new TestClock('2026-10-17T03:00:00.250000Z');
        $this->app->instance(Clock::class, $clock);

        $future = $this->confirmAt($clock, $this->envelopeOf([self::line($this->pen, '4', self::known('4000'))], ['documentDate' => '2099-12-31']));
        $clock->advanceSeconds(60);
        $past = $this->confirmAt($clock, $this->envelopeOf([self::line($this->pen, '1', self::known('1500'))], ['documentDate' => '2001-01-01']));
        $clock->advanceSeconds(60);
        $undated = $this->confirmAt($clock, $this->envelopeOf([self::line($this->pen, '2', self::known('3000'))]));

        foreach ([[$future, '2099-12-31', '2026-10-17T03:00:00.250000Z', 1], [$past, '2001-01-01', '2026-10-17T03:01:00.250000Z', 2], [$undated, null, '2026-10-17T03:02:00.250000Z', 3]] as [$command, $date, $at, $sequence]) {
            $document = (array) $this->watcher()->table('receiving_documents')->where('command_id', $command)->sole();
            $movement = (array) $this->watcher()->table('stock_movements')->where('command_id', $command)->sole();
            $evidence = (array) $this->watcher()->table('acquisition_cost_evidence')->where('command_id', $command)->sole();
            $event = (array) $this->watcher()->table('audit_events')->where('command_id', $command)->sole();

            $this->assertSame([$date, $at, $at, $at, $at, $sequence], [
                $document['document_date'], self::instant($document['confirmed_at']), self::instant($movement['recorded_at']), self::instant($evidence['recorded_at']), self::instant($event['occurred_at']), $movement['product_sequence'],
            ], (string) $date);
        }

        // The balance and the valuation follow the posting order, whatever the dates say.
        $this->assertSame([[$this->pen, '7.000', 3, '2026-10-17T03:02:00.250000Z']], $this->balances());
        $this->assertSame([[$this->pen, 'KNOWN', '8500.000000', 3, 3, '2026-10-17T03:02:00.250000Z']], $this->valuations());

        // A document date is a date of the calendar (TECHNICAL_RULES section 11.4).
        $this->app->instance(Clock::class, new TestClock(CarbonImmutable::now('UTC')));
        $before = $this->factsAndRows();

        foreach (['2026-02-30', '2026-13-01', '0000-01-01', '26-10-17', '17-10-2026', '2026-10-17T00:00:00Z', '2026-1-7', 20261017] as $invalid) {
            $this->confirm($this->envelopeOf([self::line($this->pen, '1', self::known('1000'))], ['documentDate' => $invalid]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($before, $this->factsAndRows());
    }

    public function test_each_refusal_under_the_locks_names_its_lines_in_the_order_of_rc_07_and_writes_nothing(): void
    {
        // INTERACTION_CONTRACTS RC-07, "Under locks"; TR-VAL-08; BR-INV-06, BR-INV-11; evidence
        // reading 2: a product that does not exist, active, not blocked, the version reviewed, then
        // the precision, each refusal naming every line it concerns.
        $sugar = TokoContoh::product('GULA-01', ['categoryId' => $this->category, 'name' => 'Gula', 'baseUnit' => 'kg', 'quantityPrecision' => 2]);
        TokoContoh::postingBlock($sugar);
        $sweet = TokoContoh::product('MANIS-01', ['categoryId' => $this->category, 'name' => 'Pemanis', 'baseUnit' => 'kg', 'quantityPrecision' => 2]);
        $this->as('Agus');
        $this->command('product.deactivate', ['productId' => $this->rice], ['productVersion' => 1])->assertStatus(201);
        $before = $this->factsAndRows();
        $one = self::known('1000');

        // A product that does not exist answers as a guessed identifier does, before anything else.
        $missing = $this->confirm($this->envelopeOf([self::line($this->rice, '1', $one), self::line(999999, '1', $one)], [], [['productId' => $this->rice, 'version' => 2], ['productId' => 999999, 'version' => 1]]))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND')->assertJsonPath('messageKey', 'ux.state.not_found');
        $this->assertJsonChannel($missing, 'commands.refused', 'ops');

        // An inactive product, before a block, with every line of it.
        $inactive = $this->confirm($this->envelopeOf([self::line($this->rice, '1', $one)]))->assertStatus(422)->assertJsonPath('code', 'PRODUCT_INACTIVE')->assertJsonPath('messageKey', 'ux.state.inactive.product')->assertJsonPath('lines', [['productId' => $this->rice]]);
        $this->assertJsonChannel($inactive, 'commands.refused.lines', 'ops');
        $this->confirm($this->envelopeOf([self::line($this->pen, '1', $one), self::line($sugar, '1', $one), self::line($this->rice, '1', $one), self::line($this->rice, '2', $one)]))->assertStatus(422)->assertJsonPath('code', 'PRODUCT_INACTIVE')->assertJsonPath('lines', [2 => ['productId' => $this->rice], 3 => ['productId' => $this->rice]]);

        // A blocked product, before its version.
        $this->confirm($this->envelopeOf([self::line($this->pen, '1', $one), self::line($sugar, '1', $one)], [], [['productId' => $this->pen, 'version' => 2], ['productId' => $sugar, 'version' => 2]]))->assertStatus(422)->assertJsonPath('code', 'POSTING_BLOCKED')->assertJsonPath('messageKey', 'ux.state.blocked')->assertJsonPath('lines', [1 => ['productId' => $sugar]]);

        // The version reviewed: the products in `lines`.
        $stale = $this->confirm($this->envelopeOf([self::line($this->pen, '1', $one), self::line($sweet, '1', $one), self::line($this->pen, '1', $one)], [], [['productId' => $sweet, 'version' => 1], ['productId' => $this->pen, 'version' => 2]]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION')->assertJsonPath('messageKey', 'ux.state.stale_version')->assertJsonPath('lines', [0 => ['productId' => $this->pen], 2 => ['productId' => $this->pen]]);
        $this->assertSame('REJECTED', $stale->json('outcome'));
        $this->assertJsonChannel($this->confirm($this->envelopeOf([self::line($this->pen, '1', $one)], [], [['productId' => $this->pen, 'version' => 2]]))->assertStatus(409), 'commands.refused.lines', 'ops');

        // The precision, which only the product's lock can tell, at the field of every line that
        // it does not fit.
        $precision = $this->confirm($this->envelopeOf([self::line($this->pen, '1.5', $one), self::line($sweet, '1.234', $one), self::line($sweet, '1.23', $one), self::line($this->pen, '2.0', $one)]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame([
            'payload.lines.0.quantity' => [['key' => 'ux.validation.quantity_whole', 'params' => []]],
            'payload.lines.1.quantity' => [['key' => 'ux.validation.quantity_decimals', 'params' => ['desimal' => '2']]],
            'payload.lines.3.quantity' => [['key' => 'ux.validation.quantity_whole', 'params' => []]],
        ], $precision->json('fields'));

        $this->assertSame($before, $this->factsAndRows());
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', ConfirmReceiving::OPERATION)->where('outcome', '<>', 'REJECTED')->count());

        // The same lines, at their precision and versions, post.
        $this->confirm($this->envelopeOf([self::line($this->pen, '1', $one), self::line($sweet, '1.23', $one)]))->assertStatus(201);
        $this->assertSame(['1.230', 1], $this->balanceOf($sweet));
    }

    public function test_onboarding_freezes_every_receipt_and_only_its_active_state_does(): void
    {
        // G-10, BR-INV-24: the onboarding state under its share lock (TR §7.1 step 2.3). Only
        // TASK-035 starts onboarding, so the state is set here directly, as the runtime role
        // (evidence reading 11).
        $this->runtime()->table('onboarding_state')->where('id', 1)->update(['state' => 'ACTIVE', 'version' => 2]);
        $before = $this->factsAndRows();

        foreach (['Hendra' => 'owner', 'Agus' => 'ops'] as $person => $actor) {
            $this->as($person);
            $frozen = $this->confirm($this->envelopeOf([self::line($this->pen, '1', self::known('1000'))]))->assertStatus(422)->assertJsonPath('code', 'ONBOARDING_FREEZE')->assertJsonPath('messageKey', 'ux.state.onboarding')->assertJsonPath('outcome', 'REJECTED');
            $this->assertJsonChannel($frozen, 'commands.refused', $actor);
        }

        $this->assertSame($before, $this->factsAndRows());

        $this->runtime()->table('onboarding_state')->where('id', 1)->update(['state' => 'NOT_STARTED', 'version' => 3]);
        $this->confirm($this->envelopeOf([self::line($this->pen, '1', self::known('1000'))]))->assertStatus(201);
        $this->assertSame(['1.000', 1], $this->balanceOf($this->pen));
    }

    public function test_a_blocked_product_is_refused_posting_blocked_until_its_reopening_while_another_product_posts(): void
    {
        // AC-021-06: G-11, BR-INV-45, BR-INV-46. The blocks and the reopening come from the
        // Inventory module's own service until TASK-036 (TASK-021 scope item 7; EXC-45).
        $block = TokoContoh::postingBlock($this->pen);
        $this->as('Agus');
        $before = $this->factsAndRows();

        $blocked = $this->confirm($this->envelopeOf([self::line($this->pen, '1', self::known('1000'))]))->assertStatus(422)->assertJsonPath('code', 'POSTING_BLOCKED')->assertJsonPath('messageKey', 'ux.state.blocked')->assertJsonPath('lines', [['productId' => $this->pen]]);
        $this->assertJsonChannel($blocked, 'commands.refused.lines', 'ops');
        $this->confirm($this->envelopeOf([self::line($this->rice, '1', self::known('1000')), self::line($this->pen, '1', self::known('1000'))]))->assertStatus(422)->assertJsonPath('code', 'POSTING_BLOCKED')->assertJsonPath('lines', [1 => ['productId' => $this->pen]]);
        $this->assertSame($before, $this->factsAndRows());

        // Another product posts.
        $this->confirm($this->envelopeOf([self::line($this->rice, '1', self::known('1000'))]))->assertStatus(201);

        // Reopened by the Owner, it posts again; a new block refuses it again.
        TokoContoh::reopenPostings($block, 'Selisih sudah diperiksa');
        $this->as('Agus');
        $this->confirm($this->envelopeOf([self::line($this->pen, '1', self::known('1000'))]))->assertStatus(201);
        TokoContoh::postingBlock($this->pen, 'COST_MISMATCH');
        $this->as('Agus');
        $this->confirm($this->envelopeOf([self::line($this->pen, '1', self::known('1000'))]))->assertStatus(422)->assertJsonPath('code', 'POSTING_BLOCKED');

        $this->assertSame([['1.000', 1], ['1.000', 1]], [$this->balanceOf($this->pen), $this->balanceOf($this->rice)]);
    }

    public function test_the_fields_are_validated_with_their_keys_before_any_lock(): void
    {
        // TR-VAL-01 to TR-VAL-07; TECHNICAL_RULES sections 11.2 and 11.3; COPY_DECK section 16.1;
        // evidence reading 4: an amount reads the keys of an amount of rupiah.
        $line = self::line($this->pen, '1', self::known('1000'));
        $before = $this->factsAndRows();

        foreach ([
            [['lines' => []], 'payload.lines', 'ux.validation.lines_min', []],
            [['lines' => null], 'payload.lines', 'ux.validation.lines_min', []],
            [['reference' => str_repeat('r', 101)], 'payload.reference', 'ux.validation.text_max', ['maks' => 100]],
            [['lines' => [[...$line, 'productId' => 0]]], 'payload.lines.0.productId', 'ux.validation.choose_one', []],
            [['lines' => [[...$line, 'productId' => -1]]], 'payload.lines.0.productId', 'ux.validation.choose_one', []],
            [['lines' => [[...$line, 'productId' => 'x']]], 'payload.lines.0.productId', 'ux.validation.choose_one', []],
            [['lines' => [[...$line, 'productId' => null]]], 'payload.lines.0.productId', 'ux.validation.required', []],
            [['lines' => [[...$line, 'quantity' => '0']]], 'payload.lines.0.quantity', 'ux.validation.quantity_positive', []],
            [['lines' => [[...$line, 'quantity' => '-1']]], 'payload.lines.0.quantity', 'ux.validation.quantity_positive', []],
            [['lines' => [[...$line, 'quantity' => '1,5']]], 'payload.lines.0.quantity', 'ux.validation.quantity_positive', []],
            [['lines' => [[...$line, 'quantity' => '1 ']]], 'payload.lines.0.quantity', 'ux.validation.quantity_positive', []],
            [['lines' => [[...$line, 'quantity' => 1]]], 'payload.lines.0.quantity', 'ux.validation.quantity_positive', []],
            [['lines' => [[...$line, 'quantity' => '1.2345']]], 'payload.lines.0.quantity', 'ux.validation.quantity_decimals', ['desimal' => '3']],
            [['lines' => [[...$line, 'quantity' => '100000000']]], 'payload.lines.0.quantity', 'ux.validation.quantity_max', []],
            [['lines' => [[...$line, 'quantity' => null]]], 'payload.lines.0.quantity', 'ux.validation.required', []],
            [['lines' => [[...$line, 'cost' => null]]], 'payload.lines.0.cost', 'ux.validation.required', []],
            [['lines' => [[...$line, 'cost' => ['amount' => '1000']]]], 'payload.lines.0.cost.status', 'ux.validation.required', []],
            [['lines' => [[...$line, 'cost' => ['status' => 'LAIN', 'amount' => '1000']]]], 'payload.lines.0.cost.status', 'ux.validation.choose_one', []],
            [['lines' => [[...$line, 'cost' => ['status' => 7, 'amount' => '1000']]]], 'payload.lines.0.cost.status', 'ux.validation.choose_one', []],
            [['lines' => [[...$line, 'cost' => ['status' => 'KNOWN']]]], 'payload.lines.0.cost.amount', 'ux.validation.required', []],
            [['lines' => [[...$line, 'cost' => self::known('-1')]]], 'payload.lines.0.cost.amount', 'ux.validation.money', []],
            [['lines' => [[...$line, 'cost' => self::known('1.1234567')]]], 'payload.lines.0.cost.amount', 'ux.validation.money', []],
            [['lines' => [[...$line, 'cost' => self::known('Rp1.000')]]], 'payload.lines.0.cost.amount', 'ux.validation.money', []],
            [['lines' => [[...$line, 'cost' => ['status' => 'KNOWN', 'amount' => 1000]]]], 'payload.lines.0.cost.amount', 'ux.validation.money', []],
            [['lines' => [[...$line, 'cost' => self::known('1000000000000')]]], 'payload.lines.0.cost.amount', 'ux.validation.money_max', []],
            [['lines' => [[...$line, 'cost' => [...self::known('0'), 'zeroEvidence' => str_repeat('b', 1001)]]]], 'payload.lines.0.cost.zeroEvidence', 'ux.validation.text_max', ['maks' => 1000]],
            [['lines' => array_fill(0, 501, $line)], 'payload.lines', 'ux.validation.lines_max', ['maks' => ConfirmReceiving::MAX_LINES]],
        ] as $index => [$payload, $path, $key, $params]) {
            $this->as('Agus');
            $refused = $this->confirmPayload([...['lines' => [$line]], ...$payload])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $fields = (array) $refused->json('fields');
            $this->assertSame(['key' => $key, 'params' => $params], ((array) ($fields[$path] ?? []))[0] ?? null, sprintf('case %d at %s', $index, $path));
        }

        // Refused with no key of a field: what no page sends.
        foreach ([
            ['reference' => "SJ\n1"],
            ['reference' => 7],
            ['lines' => 'x'],
            ['lines' => ['x']],
            ['lines' => [[...$line, 'cost' => 'x']]],
            ['lines' => [[...$line, 'productId' => (string) $this->pen]]],
            ['lines' => [[...$line, 'price' => '1000']]],
            ['lines' => [[...$line, 'cost' => [...self::known('1000'), 'currency' => 'IDR']]]],
            ['lines' => [[...$line, 'cost' => [...self::known('1000'), 'zeroEvidence' => "Hadiah\u{0007}"]]]],
            ['supplier' => 'Toko Lain'],
            ['lines' => [0 => $line, 2 => $line]],
        ] as $payload) {
            $this->as('Agus');
            $this->confirmPayload([...['lines' => [$line]], ...$payload])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // The versions reviewed name each product of the lines once, as whole numbers.
        foreach ([
            [],
            [['productId' => $this->rice, 'version' => 1]],
            [['productId' => $this->pen, 'version' => 1], ['productId' => $this->rice, 'version' => 1]],
            [['productId' => $this->pen, 'version' => 1], ['productId' => $this->pen, 'version' => 1]],
            [['productId' => $this->pen, 'version' => '1']],
            [['productId' => $this->pen, 'version' => 0]],
            [['productId' => $this->pen]],
            [['productId' => $this->pen, 'version' => 1, 'sku' => 'PEN-01']],
        ] as $expected) {
            $this->as('Agus');
            $this->confirm($this->envelopeOf([$line], [], $expected))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->as('Agus');
        $this->confirm($this->envelope(['lines' => [$line]], ['operation' => ConfirmReceiving::OPERATION, 'expected' => ['products' => [$this->pen => 1]]]))->assertStatus(422);
        $this->confirm($this->envelope(['lines' => [$line]], ['operation' => ConfirmReceiving::OPERATION, 'expected' => (object) []]))->assertStatus(422);

        $this->assertSame($before, $this->factsAndRows());
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', ConfirmReceiving::OPERATION)->where('outcome', '<>', 'REJECTED')->count());

        // The longest texts and the largest values are kept whole.
        $this->as('Agus');
        $this->confirmPayload(['reference' => str_repeat('r', 100), 'lines' => [self::line($this->rice, Limits::QUANTITY_MAX, self::known(Limits::COST_MAX))]])->assertStatus(201);
        $this->as('Hendra');
        $this->confirmPayload(['lines' => [self::line($this->pen, '1', [...self::known('0'), 'zeroEvidence' => str_repeat('b', 1000)])]])->assertStatus(201);
    }

    public function test_a_document_holds_up_to_500_lines(): void
    {
        // TECHNICAL_RULES section 11.2; INTERACTION_CONTRACTS RC-07. One product on every line:
        // each line posts its movement, in the sequence of the lines.
        $this->as('Agus');
        $commandId = self::number($this->confirm($this->envelopeOf(array_fill(0, ConfirmReceiving::MAX_LINES, self::line($this->pen, '1', self::known('100')))))->assertStatus(201)->json('commandId'));

        $this->assertSame(500, $this->watcher()->table('receiving_lines')->count());
        $this->assertSame(range(1, 500), $this->watcher()->table('stock_movements')->where('command_id', $commandId)->orderBy('id')->pluck('product_sequence')->all());
        $this->assertSame(500, $this->watcher()->table('acquisition_cost_evidence')->where('command_id', $commandId)->count());
        $this->assertSame(['500.000', 500], $this->balanceOf($this->pen));
        $this->assertSame('KNOWN|50000.000000|500|500', $this->valuationOf($this->pen));
        $this->assertBalancesAreTheSumOfTheMovements();
    }

    public function test_a_balance_or_a_value_past_its_range_refuses_the_whole_confirmation(): void
    {
        // TECHNICAL_RULES section 11.1; G-02: the lines of a product are added together, and every
        // position and valuation is computed before anything is written.
        $this->as('Agus');
        $before = $this->factsAndRows();
        $largest = self::line($this->rice, Limits::QUANTITY_MAX, self::known('1'));

        $refused = $this->confirm($this->envelopeOf(array_fill(0, 101, $largest)))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame(['payload.lines.100.quantity' => [['key' => 'ux.validation.quantity_max', 'params' => []]]], $refused->json('fields'));
        $this->assertSame($before, $this->factsAndRows());

        // A hundred of them fit.
        $this->confirm($this->envelopeOf(array_fill(0, 100, $largest)))->assertStatus(201);
        $this->assertSame([(string) BigDecimal::of(Limits::QUANTITY_MAX)->multipliedBy(100), 100], $this->balanceOf($this->rice));

        // A value at the limit, published by the Costing module's own service (EXC-45): one more
        // unit at any KNOWN cost would pass it; an UNKNOWN cost passes no range.
        TokoContoh::stock($this->pen, '1');
        DB::transaction(function (): void {
            app(Products::class)->lockForPosting([$this->pen]);
            app(InventoryValuations::class)->publish(new ProductValuation($this->pen, Cost::of(Limits::VALUE_MAX), 2, 1, true), CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.uP'));
        });
        $this->as('Agus');
        $before = $this->factsAndRows();

        $refused = $this->confirm($this->envelopeOf([self::line($this->pen, '1', self::known('0.000001'))]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame(['payload.lines.0.cost.amount' => [['key' => 'ux.validation.money_max', 'params' => []]]], $refused->json('fields'));
        $this->assertSame($before, $this->factsAndRows());

        // The limit itself is kept: the Owner's evidenced zero adds nothing to it.
        $this->as('Hendra');
        $this->confirm($this->envelopeOf([self::line($this->pen, '1', [...self::known('0'), 'zeroEvidence' => 'Hadiah pemasok'])]))->assertStatus(201);
        $this->assertSame('KNOWN|'.Limits::VALUE_MAX.'|3|2', $this->valuationOf($this->pen));
        $this->confirm($this->envelopeOf([self::line($this->pen, '1', self::unknown())]))->assertStatus(201);
        $this->assertSame('UNKNOWN||4|3', $this->valuationOf($this->pen));
    }

    public function test_an_envelope_is_refused_as_every_command_refuses_one_and_a_repeat_or_a_status_check_recovers_the_result(): void
    {
        // CMD01; AC-021-03; G-12, G-25; RC-50; ADR-007 section 3; TR-HTTP-09; RACE-01 and RACE-02
        // in sequence (their races are ReceivingRaceTest's).
        $this->as('Agus');
        $envelope = $this->envelopeOf([self::line($this->pen, '3', self::known('3000'))]);
        $before = $this->factsAndRows();

        foreach ([['version' => 2], ['tambahan' => 'x'], ['operation' => 'manual_issue.confirm'], ['expected' => ['products' => [['productId' => $this->pen, 'version' => 1.5]]]]] as $changes) {
            $this->confirm([...$envelope, 'idempotencyKey' => (string) Str::uuid(), ...$changes])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')->assertHeader('Cache-Control', 'no-store, private');
        }

        // An earlier epoch is refused before any fact.
        $this->confirm([...$envelope, 'sourceIntentId' => (string) Str::uuid(), 'idempotencyKey' => (string) Str::uuid(), 'recoveryEpoch' => str_repeat('ab', 32)])->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE')->assertJsonPath('outcome', 'HELD');
        $this->assertSame($before, $this->factsAndRows());

        // The command runs once; a repeat answers the original; another payload under the same
        // key, or under another key of the same intent, is held and never run.
        $committed = $this->confirm($envelope)->assertStatus(201);
        $document = self::number($committed->json('result.id'));
        $facts = $this->factsAndRows();
        $other = ['lines' => [self::line($this->pen, '4', self::known('3000'))]];

        $this->confirm($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $document);
        $this->confirm([...$envelope, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $document);

        foreach ([[...$envelope, 'payload' => $other], [...$envelope, 'idempotencyKey' => (string) Str::uuid(), 'payload' => $other]] as $conflicting) {
            $held = $this->confirm($conflicting)->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT')->assertJsonPath('outcome', 'HELD');
            $this->assertSame('no-store, private', $held->headers->get('Cache-Control'));
        }

        $this->assertSame($facts, $this->factsAndRows());

        // A status check recovers the original result.
        $resolved = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.id', $document)->assertJsonPath('result.location', '/stock/receivings/'.$document);
        $this->assertJsonChannel($resolved, 'commands.resolve', 'ops');
    }

    public function test_a_failure_after_every_write_step_leaves_no_fact_and_the_receipt_pending(): void
    {
        // AC-021-03, TS-21: two products and three lines write 16 steps — each product's balance,
        // each product's valuation, the document, each line's row, movement and evidence, the audit
        // event, the result — first as each product's first rows, then as updates of them.
        $lines = [self::line($this->pen, '2', self::known('2000')), self::line($this->rice, '1.5', self::unknown()), self::line($this->pen, '3', self::known('3300'))];

        foreach (['the first movements', 'later movements'] as $case) {
            $this->as('Agus');

            for ($step = 1; $step <= 16; $step++) {
                $this->app->instance(CommandPoints::class, new FailurePoints($step));
                $before = $this->factsAndRows();

                $this->confirm($this->envelopeOf($lines))->assertStatus(500);

                $this->assertSame($before, $this->factsAndRows(), sprintf('%s after write step %d', $case, $step));
                $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', ConfirmReceiving::OPERATION)->orderByDesc('id')->value('outcome'), $case);
            }

            // The step after the last is no step of the command: it commits, through the locks of
            // TR §7.1 in their order.
            $points = new FailurePoints(17);
            $this->app->instance(CommandPoints::class, $points);
            $committed = $this->confirm($this->envelopeOf($lines))->assertStatus(201);
            $this->assertSame(1, $this->watcher()->table('command_results')->where('command_id', $committed->json('commandId'))->count(), $case);
            $this->assertSame(['after_lock:2.1', 'after_lock:2.3', 'after_lock:6', 'after_lock:7', ...array_map(static fn (int $step): string => 'after_write:'.$step, range(1, 16))], array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))), $case);
            $this->app->instance(CommandPoints::class, new FailurePoints);
        }

        $this->assertSame(['10.000', 4], $this->balanceOf($this->pen));
        $this->assertSame(['3.000', 2], $this->balanceOf($this->rice));
        $this->assertSame('KNOWN|10600.000000|4|4', $this->valuationOf($this->pen));
        $this->assertSame('UNKNOWN||2|2', $this->valuationOf($this->rice));
        $this->assertBalancesAreTheSumOfTheMovements();
    }

    public function test_only_cap_11_passes_the_gate_and_the_guard_in_the_transaction_holds_too(): void
    {
        // CAP-11: the Owner, OPERATIONS_ADMIN and the combined account; SEC-AZ-04.
        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $response = $this->confirm($this->envelopeOf([self::line($this->pen, '1', self::known('1000'))]))->assertStatus(201);
            $this->assertJsonChannel($response, 'commands.committed', $actor);
            $this->assertSame($this->people[$person]['id'], $this->watcher()->table('receiving_documents')->where('id', $response->json('result.id'))->value('actor_user_id'), $person);
        }

        $before = $this->factsAndRows();
        $this->as('Rina');
        $this->confirm($this->envelopeOf([self::line($this->pen, '1', self::known('1000'))]))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', ConfirmReceiving::OPERATION)->where('actor_user_id', $this->people['Rina']['id'])->count());

        // The Action refuses a cashier even without the route's gate.
        $envelope = new Envelope(ConfirmReceiving::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, ['products' => [['productId' => $this->pen, 'version' => 1]]], ['lines' => [self::line($this->pen, '1', self::known('1000'))]]);

        try {
            app(ConfirmReceiving::class)->execute(new CommandContext($this->people['Rina']['id'], $envelope, app(Clock::class)->now()));
            $this->fail('A cashier passed the guard.');
        } catch (ActorNotAllowed) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($before, $this->factsAndRows());

        // Without a sign-in session nothing is answered.
        $this->browse(null);
        $this->confirm($this->envelopeOf([self::line($this->pen, '1', self::known('1000'))]))->assertStatus(401);
        $this->assertSame($before, $this->factsAndRows());
        $this->assertSame(['3.000', 3], $this->balanceOf($this->pen));
    }

    public function test_no_draft_route_exists_and_each_projection_has_its_one_writer(): void
    {
        // AC-021-01, AC-021-02: D-069 (a), BR-INV-17; STK-12, ARC-20, ARC-21. A receiving that is
        // not confirmed lives in page memory, so no route writes one before RC-07.
        $routes = array_keys(RouteInventory::routes(app('router')));

        foreach ($routes as $route) {
            $this->assertStringNotContainsStringIgnoringCase('draft', $route);
        }

        $this->assertSame(['POST /commands/receiving.confirm'], array_values(array_filter($routes, static fn (string $route): bool => str_contains($route, 'receiving') && ! str_starts_with($route, 'GET '))));

        // A scan of app/: the tables of the ledger and the valuation are named only by their one
        // writer, and the read of RD-01 that shows a balance writes nothing (AC-021-01).
        $this->assertSame(['Domain/Inventory/StockLedger.php', 'Queries/Inventory/ProductStock.php'], self::filesNaming('stock_balances'));
        $this->assertSame(['Domain/Inventory/StockLedger.php'], self::filesNaming('stock_movements'));
        $this->assertSame(['Domain/Costing/InventoryValuations.php'], self::filesNaming('inventory_valuations'));
        $this->assertSame(['Domain/Costing/CostEvidence.php'], self::filesNaming('acquisition_cost_evidence'));
        $this->assertSame(['Domain/Inventory/Receivings.php'], self::filesNaming('receiving_documents'));
        $this->assertSame(['Domain/Inventory/Receivings.php'], self::filesNaming('receiving_lines'));
        $this->assertDoesNotMatchRegularExpression('/->(?:insert|insertGetId|update|upsert|delete|increment|decrement|truncate)\(/', (string) file_get_contents(app_path('Queries/Inventory/ProductStock.php')));
    }

    /**
     * A line of RC-07.
     *
     * @param  array<string, mixed>  $cost
     * @return array<string, mixed>
     */
    private static function line(int $product, string $quantity, array $cost): array
    {
        return ['productId' => $product, 'quantity' => $quantity, 'cost' => $cost];
    }

    /**
     * @return array<string, string>
     */
    private static function known(string $amount): array
    {
        return ['status' => 'KNOWN', 'amount' => $amount];
    }

    /**
     * @return array<string, string>
     */
    private static function unknown(): array
    {
        return ['status' => 'UNKNOWN'];
    }

    /**
     * An envelope of RC-07 for these lines, with the version of each of their products as it
     * stands, once, unless $expected names others.
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

        return $this->envelope(['lines' => $lines, ...$payload], ['operation' => ConfirmReceiving::OPERATION, 'expected' => ['products' => $expected]]);
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
     * RC-07 with this payload, with the versions of the products that its lines name, or of the
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

        return $this->confirm($this->envelope($payload, ['operation' => ConfirmReceiving::OPERATION, 'expected' => ['products' => $expected]]));
    }

    /**
     * RC-07 received at the clock's instant, as the web server would stamp it (TR-TIME-03): its
     * command's identity.
     *
     * @param  array<string, mixed>  $envelope
     */
    private function confirmAt(TestClock $clock, array $envelope): int
    {
        $response = $this->withServerVariables([ReceivedAt::PARAMETER => $clock->now()->format('U.u')])->postJson(self::COMMAND, $envelope)->assertStatus(201);
        $this->withServerVariables([]);

        return self::number($response->json('commandId'));
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
     * Every balance: the product, on hand, the last sequence and the instant of its update.
     *
     * @return list<list<mixed>>
     */
    private function balances(): array
    {
        return array_values($this->watcher()->table('stock_balances')->orderBy('product_id')->get()->map(static fn (object $row): array => [((array) $row)['product_id'], ((array) $row)['on_hand'], ((array) $row)['last_sequence'], self::instant(((array) $row)['updated_at'])])->all());
    }

    /**
     * Every valuation: the product, its status, value, publication, last sequence and instant.
     *
     * @return list<list<mixed>>
     */
    private function valuations(): array
    {
        return array_values($this->watcher()->table('inventory_valuations')->orderBy('product_id')->get()->map(static function (object $row): array {
            $values = (array) $row;

            return [$values['product_id'], $values['value_status'], $values['value'], $values['publication_no'], $values['last_sequence'], self::instant($values['published_at'])];
        })->all());
    }

    /**
     * AC-021-01, G-03: each balance is the sum of its product's movements, and its last sequence
     * the number of them; no product has movements without its balance.
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
     * The facts, with the rows that a confirmation changes: the balances, the valuations and the
     * products, whose versions it never moves.
     *
     * @return array<string, mixed>
     */
    private function factsAndRows(): array
    {
        return ['facts' => $this->facts(), 'balances' => $this->tableRows('stock_balances', 'product_id'), 'valuations' => $this->tableRows('inventory_valuations', 'product_id'), 'products' => $this->tableRows('products', 'id')];
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
}
