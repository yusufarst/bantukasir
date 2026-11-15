<?php

namespace Tests\Feature\Inventory;

use App\Actions\Commands\CommandContext;
use App\Actions\Inventory\ConfirmOpeningStock;
use App\Actions\Inventory\ReleaseOnboarding;
use App\Actions\Inventory\ReplaceOpeningStock;
use App\Actions\Inventory\StartOnboarding;
use App\Actions\Inventory\WithdrawZeroCount;
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
 * RC-11 to RC-14 and RC-48 through their routes (AC-035-01 to AC-035-04; INTERACTION_CONTRACTS RC-11 to
 * RC-14, RC-48 and section 1.5; ROUTE_CONTRACTS section 6; TECHNICAL_RULES section 7.2; BR-INV-23 to
 * BR-INV-28, BR-INV-47, BR-INV-48; D-071, D-072, D-087; WF-24, WF-25; G-22, G-24, G-26; CAP-15;
 * EXECUTION_CONTEXT section 10, a command; suites OPEN01, AUTH03, CMD01; TASK-035 section 7, L2 and
 * L4): the Owner's one start, at a cutover, and one release, final; an entry of up to 100 rows, all or
 * nothing, each positive row an OPENING at the product's first place with its cost evidence, balance and
 * valuation, each zero row a zero count on the checklist; one effective entry per product; a zero count
 * withdrawn once, after which the product is counted again; a reversed OPENING replaced once, at its
 * product's next place; every refusal, which writes nothing; the corrections ended by the release; the
 * fields and the envelope; the failure after every write step; recent authentication, the roles and the
 * combined account; one writer of each table.
 */
final class OnboardingCommandsTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    /** The tables that a refused or failed command leaves as they were, beside the rows compared. */
    private const array FACTS = [
        'onboarding_releases', 'opening_stock_entries', 'opening_stock_lines', 'onboarding_zero_counts', 'onboarding_zero_count_withdrawals', 'stock_movements',
        'acquisition_cost_evidence', 'reversals', 'command_identities', 'command_results', 'audit_events',
    ];

    private const string LOCATION = '/stock/onboarding';

    private int $category = 0;

    /** Counted whole: `pcs`, precision 0. */
    private int $pen = 0;

    /** Weighed: `kg`, precision 3. */
    private int $rice = 0;

    private int $book = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category();
        $this->pen = TokoContoh::product('PEN-01', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam']);
        $this->rice = TokoContoh::product('BERAS-01', ['categoryId' => $this->category, 'name' => 'Beras', 'baseUnit' => 'kg', 'quantityPrecision' => 3]);
        $this->book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis']);
    }

    public function test_the_owner_starts_onboarding_once_and_releases_it_once_for_good(): void
    {
        // AC-035-04, WF-24: RC-12 from NOT_STARTED at its version, the cutover its time and actor; RC-13
        // with its durable release record; nothing starts it again (G-26; D-071).
        $this->owner();
        $hendra = $this->people['Hendra']['id'];
        $start = $this->envelopeOf(StartOnboarding::OPERATION, [], ['onboardingVersion' => 1]);

        $response = $this->send($start)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed', 'owner');
        $this->assertSame(['onboarding', null, self::LOCATION], [$response->json('result.type'), $response->json('result.id'), $response->json('result.location')]);
        $commandId = self::number($response->json('commandId'));
        $this->assertSame(['ACTIVE', 2, $hendra], array_values((array) $this->watcher()->table('onboarding_state')->first(['state', 'version', 'changed_by_user_id'])));

        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['BUSINESS', 'ONBOARDING_START', $hendra, 'onboarding', 1, null], [$event['category'], $event['action'], $event['actor_user_id'], $event['subject_type'], $event['subject_id'], $event['product_id']]);
        $this->assertSame(['from' => 'NOT_STARTED', 'to' => 'ACTIVE', 'version' => 2], self::dataOf($event['change_data']));
        $this->assertSame(['onboarding', null], array_values((array) $this->watcher()->table('command_results')->where('command_id', $commandId)->first(['result_type', 'result_ref'])));

        // A repeat answers the original; a status check recovers it; a second start, at the old version
        // or the new one, is refused with nothing written (RACE-32 in sequence).
        $this->send($start)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.location', self::LOCATION);
        $this->assertJsonChannel($this->check($start)->assertOk()->assertJsonPath('outcome', 'COMMITTED'), 'commands.resolve', 'owner');
        $before = $this->factsAndRows();

        foreach ([1, 2] as $version) {
            $this->send($this->envelopeOf(StartOnboarding::OPERATION, [], ['onboardingVersion' => $version]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        }

        $this->assertSame($before, $this->factsAndRows());

        // RC-13: the release record, then RELEASED at version 3 (D-071).
        $this->send($this->envelopeOf(ReleaseOnboarding::OPERATION, [], ['onboardingVersion' => 1]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $released = $this->send($this->envelopeOf(ReleaseOnboarding::OPERATION, [], ['onboardingVersion' => 2]))->assertStatus(201);
        $this->assertSame(['onboarding', null, self::LOCATION], [$released->json('result.type'), $released->json('result.id'), $released->json('result.location')]);
        $releaseCommand = self::number($released->json('commandId'));
        $this->assertSame([1, 'RELEASE', $hendra, $releaseCommand], array_values((array) $this->watcher()->table('onboarding_releases')->first(['id', 'kind', 'actor_user_id', 'command_id'])));
        $this->assertSame(['RELEASED', 3, $hendra], array_values((array) $this->watcher()->table('onboarding_state')->first(['state', 'version', 'changed_by_user_id'])));
        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $releaseCommand)->sole();
        $this->assertSame(['ONBOARDING_RELEASE', 'onboarding', 1], [$event['action'], $event['subject_type'], $event['subject_id']]);
        $this->assertSame(['from' => 'ACTIVE', 'to' => 'RELEASED', 'version' => 3], self::dataOf($event['change_data']));

        // After the release neither a start nor a second release runs, at any version (G-26).
        $after = $this->factsAndRows();

        foreach ([StartOnboarding::OPERATION, ReleaseOnboarding::OPERATION] as $operation) {
            foreach ([1, 2, 3] as $version) {
                $this->send($this->envelopeOf($operation, [], ['onboardingVersion' => $version]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
            }
        }

        $this->assertSame($after, $this->factsAndRows());
    }

    public function test_a_restore_hold_waiting_for_its_reconciliation_refuses_every_onboarding_command(): void
    {
        // G-26, D-072; TECHNICAL_RULES section 7.2: while a restore hold — which TASK-037's after-restore
        // procedure sets — waits for its reconciliation, no start, entry, correction or release runs, each
        // STALE_VERSION with nothing written; once the Owner has reconciled it, each runs again.
        $this->owner();
        $hold = $this->hold('NOT_STARTED');
        $before = $this->factsAndRows();
        $this->send($this->envelopeOf(StartOnboarding::OPERATION, [], ['onboardingVersion' => 1]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->assertSame($before, $this->factsAndRows());

        $this->reconcile($hold);
        $this->send($this->envelopeOf(StartOnboarding::OPERATION, [], ['onboardingVersion' => 1]))->assertStatus(201);
        $zeroCount = TokoContoh::zeroCount($this->book);
        $opening = TokoContoh::openingStock($this->pen, '3');
        TokoContoh::reverse($opening);
        $replacement = ['replacesLineId' => TokoContoh::openingLineOf($opening), 'quantity' => '2', 'countReference' => 'LH-02', 'cost' => ['status' => 'UNKNOWN'], 'reason' => 'Hitung ulang'];
        $commands = [
            ConfirmOpeningStock::OPERATION => fn (): array => $this->entryOf([self::zero($this->rice)]),
            WithdrawZeroCount::OPERATION => fn (): array => $this->envelopeOf(WithdrawZeroCount::OPERATION, ['zeroCountId' => $zeroCount, 'reason' => 'Salah hitung'], ['onboardingVersion' => 2]),
            ReplaceOpeningStock::OPERATION => fn (): array => $this->envelopeOf(ReplaceOpeningStock::OPERATION, $replacement, ['onboardingVersion' => 2, 'productVersion' => $this->versionOf($this->pen)]),
            ReleaseOnboarding::OPERATION => fn (): array => $this->envelopeOf(ReleaseOnboarding::OPERATION, [], ['onboardingVersion' => 2]),
        ];

        $hold = $this->hold('ACTIVE');
        $before = $this->factsAndRows();

        foreach ($commands as $operation => $envelope) {
            $this->send($envelope())->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
            $this->assertSame($before, $this->factsAndRows(), $operation);
        }

        // Reconciled, each runs, the release last.
        $this->reconcile($hold);

        foreach ($commands as $envelope) {
            $this->send($envelope())->assertStatus(201);
        }

        $this->assertSame(['RELEASED', 1, 1, 1], [
            $this->watcher()->table('onboarding_state')->value('state'),
            $this->watcher()->table('onboarding_zero_counts')->where('product_id', $this->rice)->count(),
            $this->watcher()->table('onboarding_zero_count_withdrawals')->where('zero_count_id', $zeroCount)->count(),
            $this->watcher()->table('opening_stock_lines')->where('product_id', $this->pen)->whereNotNull('replaces_line_id')->count(),
        ]);
    }

    public function test_an_entry_posts_each_positive_row_as_an_opening_and_records_each_zero_row_on_the_checklist(): void
    {
        // AC-035-02, WF-25; BR-INV-25 to BR-INV-27; BR-COST-02 to BR-COST-04; ADR-008 section 4.2: 12 pens
        // at Rp60.000, 2.5 kg of rice at UNKNOWN, a book counted zero, in one entry.
        TokoContoh::startOnboarding();
        $this->as('Hendra');
        $hendra = $this->people['Hendra']['id'];
        $envelope = $this->entryOf([
            self::positive($this->pen, '12', ['status' => 'KNOWN', 'amount' => '60000'], 'LH-01'),
            self::positive($this->rice, '2.5', ['status' => 'UNKNOWN'], 'LH-02'),
            self::zero($this->book),
        ]);

        // No recent authentication (ROUTE_CONTRACTS RC-11).
        $response = $this->send($envelope)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed', 'owner');
        $entry = self::number($response->json('result.id'));
        $commandId = self::number($response->json('commandId'));
        $this->assertSame(['opening_stock_entry', self::LOCATION], [$response->json('result.type'), $response->json('result.location')]);

        $this->assertSame([3, $hendra, 'Hendra', $commandId], array_values((array) $this->watcher()->table('opening_stock_entries')->where('id', $entry)->first(['row_count', 'actor_user_id', 'actor_display_name', 'command_id'])));
        $lines = $this->watcher()->table('opening_stock_lines')->where('opening_stock_entry_id', $entry)->orderBy('row_no')
            ->get(['row_no', 'product_id', 'quantity', 'count_reference', 'replaces_line_id', 'replaces_movement_id', 'replacement_reason', 'is_replacement'])->map(static fn (object $row): array => array_values((array) $row))->all();
        $this->assertSame([[1, $this->pen, '12.000', 'LH-01', null, null, null, false], [2, $this->rice, '2.500', 'LH-02', null, null, null, false]], array_values($lines));

        // One OPENING per positive row, at its product's first place (BR-INV-26).
        $movements = $this->watcher()->table('stock_movements')->where('command_id', $commandId)->orderBy('id')
            ->get(['product_id', 'product_sequence', 'movement_type', 'quantity', 'line_quantity', 'opening_replacement', 'id'])->map(static fn (object $row): array => (array) $row)->all();
        $ids = array_map(static fn (array $movement): int => self::number($movement['id']), array_values($movements));
        $this->assertSame([[$this->pen, 1, 'OPENING', '12.000', '12.000', false], [$this->rice, 1, 'OPENING', '2.500', '2.500', false]], array_map(static fn (array $movement): array => array_values(array_diff_key($movement, ['id' => true])), array_values($movements)));

        // The cost evidence of each, KNOWN or UNKNOWN, with the row's count reference (BR-COST-02).
        $this->assertSame([
            ['OPENING', '12.000', 'KNOWN', '60000.000000', 'LH-01'],
            ['OPENING', '2.500', 'UNKNOWN', null, 'LH-02'],
        ], array_map(fn (int $movement): array => array_values((array) $this->watcher()->table('acquisition_cost_evidence')->where('movement_id', $movement)->first(['movement_type', 'quantity', 'cost_status', 'total_amount', 'reference'])), $ids));

        // The zero row is a zero count on the checklist, with no movement (BR-INV-27).
        $zeroCount = (array) $this->watcher()->table('onboarding_zero_counts')->where('product_id', $this->book)->sole();
        $this->assertSame([null, $hendra, $commandId], [$zeroCount['count_reference'], $zeroCount['actor_user_id'], $zeroCount['command_id']]);
        $this->assertSame(0, $this->watcher()->table('stock_movements')->where('product_id', $this->book)->count());

        // The balances and the valuations.
        $this->assertSame([['12.000', 1], ['2.500', 1], null], [$this->balanceOf($this->pen), $this->balanceOf($this->rice), $this->balanceOf($this->book)]);
        $this->assertSame(['KNOWN|60000.000000|1|1', 'UNKNOWN||1|1'], [$this->valuationOf($this->pen), $this->valuationOf($this->rice)]);

        // One audit event; the costs are the Owner's part (BR-AUD-02). Three products, so no product filter.
        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['BUSINESS', 'OPENING_STOCK_CONFIRM', $hendra, 'opening_stock_entry', $entry, null], [$event['category'], $event['action'], $event['actor_user_id'], $event['subject_type'], $event['subject_id'], $event['product_id']]);
        $this->assertSame(['rowCount' => 3, 'rows' => [
            ['countReference' => 'LH-01', 'movementId' => $ids[0], 'productId' => $this->pen, 'quantity' => '12', 'rowNo' => 1],
            ['countReference' => 'LH-02', 'movementId' => $ids[1], 'productId' => $this->rice, 'quantity' => '2.5', 'rowNo' => 2],
            ['countReference' => null, 'productId' => $this->book, 'quantity' => '0', 'rowNo' => 3, 'zeroCountId' => self::number($zeroCount['id'])],
        ]], self::dataOf($event['change_data']));
        $this->assertStringNotContainsString('60000', self::text($event['change_data']));
        $evidence = array_map(fn (int $movement): int => self::number($this->watcher()->table('acquisition_cost_evidence')->where('movement_id', $movement)->value('id')), $ids);
        $this->assertSame(['rows' => [
            ['amount' => '60000', 'evidenceId' => $evidence[0], 'rowNo' => 1, 'status' => 'KNOWN', 'zeroNote' => null],
            ['amount' => null, 'evidenceId' => $evidence[1], 'rowNo' => 2, 'status' => 'UNKNOWN', 'zeroNote' => null],
        ]], self::dataOf($event['owner_only_data']));

        // The result holds references only; no version moved; a repeat answers it; a status check recovers it.
        $this->assertSame(['opening_stock_entry', $entry], array_values((array) $this->watcher()->table('command_results')->where('command_id', $commandId)->first(['result_type', 'result_ref'])));
        $this->assertSame([1, 1, 1], [$this->versionOf($this->pen), $this->versionOf($this->rice), $this->versionOf($this->book)]);
        $facts = $this->factsAndRows();
        $this->send($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $entry);
        $this->send([...$envelope, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $entry);
        $this->assertSame($facts, $this->factsAndRows());
        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.location', self::LOCATION);
    }

    public function test_an_entry_is_all_or_nothing_and_takes_only_products_without_an_entry_or_an_earlier_movement(): void
    {
        // AC-035-02, AC-035-03; BR-INV-25, BR-INV-26, BR-INV-47; G-22; INTERACTION_CONTRACTS RC-11, "Under locks".
        TokoContoh::stock($this->book, '5', '5000');
        $sugar = TokoContoh::product('GULA-01', ['categoryId' => $this->category, 'name' => 'Gula', 'baseUnit' => 'kg', 'quantityPrecision' => 2]);
        $ink = TokoContoh::product('TINTA-01', ['categoryId' => $this->category, 'name' => 'Tinta']);
        $eraser = TokoContoh::product('HAPUS-01', ['categoryId' => $this->category, 'name' => 'Penghapus']);
        $glue = TokoContoh::product('LEM-01', ['categoryId' => $this->category, 'name' => 'Lem']);
        $this->as('Hendra');
        $this->command('product.deactivate', ['productId' => $sugar], ['productVersion' => 1])->assertStatus(201);
        $known = ['status' => 'KNOWN', 'amount' => '1000'];

        // Before the start, no entry is taken (STALE_VERSION; D-071).
        $this->send($this->entryOf([self::positive($this->pen, '1', $known)], 1))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');

        TokoContoh::startOnboarding();
        TokoContoh::openingStock($ink, '3');
        TokoContoh::zeroCount($eraser);
        TokoContoh::postingBlock($glue);
        $before = $this->factsAndRows();

        // Each row whose product holds an entry or another movement is refused at its product; the
        // rows beside it are not posted either (BR-INV-25).
        foreach ([
            [self::positive($this->pen, '1', $known), self::positive($this->book, '1', $known)],
            [self::positive($this->pen, '1', $known), self::zero($this->book)],
            [self::positive($this->pen, '1', $known), self::positive($ink, '1', $known)],
            [self::positive($this->pen, '1', $known), self::zero($ink)],
        ] as $index => $rows) {
            $refused = $this->send($this->entryOf($rows))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $this->assertSame(['payload.rows.1.productId' => [['key' => 'inventory.opening_entry.not_eligible', 'params' => []]]], $refused->json('fields'), (string) $index);
        }

        foreach ([self::positive($eraser, '1', $known), self::zero($eraser)] as $row) {
            $refused = $this->send($this->entryOf([self::zero($this->rice), $row]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $this->assertSame(['payload.rows.1.productId' => [['key' => 'inventory.opening_entry.already_zero', 'params' => []]]], $refused->json('fields'));
        }

        // Each product once (INTERACTION_CONTRACTS section 1.6); an inactive or blocked product; a version
        // that changed; the onboarding version that changed; a quantity beyond its product's precision.
        $twice = $this->send($this->entryOf([self::positive($this->pen, '1', $known), self::zero($this->pen)], null, [['productId' => $this->pen, 'version' => 1]]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame([1 => ['productId' => $this->pen]], $twice->json('lines'));
        $this->send($this->entryOf([self::positive($this->pen, '1', $known), self::positive($sugar, '1', $known)]))->assertStatus(422)->assertJsonPath('code', 'PRODUCT_INACTIVE')->assertJsonPath('lines', [1 => ['productId' => $sugar]]);
        $this->send($this->entryOf([self::positive($this->pen, '1', $known), self::positive($glue, '1', $known)]))->assertStatus(422)->assertJsonPath('code', 'POSTING_BLOCKED')->assertJsonPath('lines', [1 => ['productId' => $glue]]);
        $this->send($this->entryOf([self::positive($this->pen, '1', $known)], null, [['productId' => $this->pen, 'version' => 2]]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION')->assertJsonPath('lines', [['productId' => $this->pen]]);
        $this->send($this->entryOf([self::positive($this->pen, '1', $known)], 1))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $precision = $this->send($this->entryOf([self::positive($this->pen, '1.5', $known), self::positive($this->rice, '1.25', $known)]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame(['payload.rows.0.quantity' => [['key' => 'ux.validation.quantity_whole', 'params' => []]]], $precision->json('fields'));

        $this->assertSame($before, $this->factsAndRows());

        // The same rows without the refused one post (BR-INV-25).
        $this->send($this->entryOf([self::positive($this->pen, '1', $known), self::positive($this->rice, '1.25', $known)]))->assertStatus(201);
        $this->assertSame([['1.000', 1], ['1.250', 1]], [$this->balanceOf($this->pen), $this->balanceOf($this->rice)]);
    }

    public function test_the_fields_of_an_entry_are_validated_with_their_keys_before_any_lock(): void
    {
        // TR-VAL-01 to TR-VAL-07; TECHNICAL_RULES sections 11.2 and 11.3; COPY_DECK section 16.1; BR-INV-25.
        TokoContoh::startOnboarding();
        $this->as('Hendra');
        $known = ['status' => 'KNOWN', 'amount' => '1000'];
        $before = $this->factsAndRows();

        foreach ([
            [[], 'payload.rows', 'ux.validation.lines_min', []],
            [array_map(static fn (int $product): array => self::zero($product), range(1, 101)), 'payload.rows', 'ux.validation.lines_max', ['maks' => 100]],
            [[self::positive($this->pen, '-1', $known)], 'payload.rows.0.quantity', 'ux.validation.quantity_positive', []],
            [[self::positive($this->pen, '1,5', $known)], 'payload.rows.0.quantity', 'ux.validation.quantity_positive', []],
            [[self::positive($this->pen, '100000000', $known)], 'payload.rows.0.quantity', 'ux.validation.quantity_max', []],
            [[['productId' => $this->pen, 'quantity' => '1', 'countReference' => null, 'cost' => $known]], 'payload.rows.0.countReference', 'ux.validation.required', []],
            [[self::positive($this->pen, '1', $known, str_repeat('c', 101))], 'payload.rows.0.countReference', 'ux.validation.text_max', ['maks' => 100]],
            [[['productId' => $this->pen, 'quantity' => '1', 'countReference' => 'LH-01']], 'payload.rows.0.cost', 'ux.validation.required', []],
            [[self::positive($this->pen, '1', ['status' => 'KNOWN'])], 'payload.rows.0.cost.amount', 'ux.validation.required', []],
            [[self::positive($this->pen, '1', ['status' => 'KNOWN', 'amount' => '0'])], 'payload.rows.0.cost.zeroEvidence', 'ux.validation.zero_evidence', []],
            [[self::positive($this->pen, '1', ['status' => 'KNOWN', 'amount' => '1,5'])], 'payload.rows.0.cost.amount', 'ux.validation.money', []],
        ] as $index => [$rows, $path, $key, $params]) {
            $refused = $this->send($this->entryOf($rows, null, [['productId' => $this->pen, 'version' => 1]]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $fields = (array) $refused->json('fields');
            $this->assertSame(['key' => $key, 'params' => $params], ((array) ($fields[$path] ?? []))[0] ?? null, sprintf('case %d at %s', $index, $path));
        }

        // What no page sends: a cost on a zero row, UNKNOWN with an amount, evidence for a cost above zero,
        // a quantity as a number, a key as a text, a control character, another field, rows as a map.
        foreach ([
            [[...self::zero($this->pen), 'cost' => $known]],
            [[...self::zero($this->pen), 'cost' => null]],
            [self::positive($this->pen, '1', ['status' => 'UNKNOWN', 'amount' => '5'])],
            [self::positive($this->pen, '1', ['status' => 'KNOWN', 'amount' => '5', 'zeroEvidence' => 'x'])],
            [[...self::zero($this->pen), 'quantity' => 0]],
            [[...self::zero($this->pen), 'productId' => (string) $this->pen]],
            [self::positive($this->pen, '1', $known, "LH\n01")],
            [[...self::zero($this->pen), 'supplier' => 'Toko Lain']],
            [0 => self::zero($this->pen), 2 => self::zero($this->pen)],
        ] as $index => $rows) {
            $this->send($this->entryOf($rows, null, [['productId' => $this->pen, 'version' => 1]]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // The versions reviewed: the onboarding's, and each product's of the rows once, as whole numbers.
        foreach ([
            ['products' => [['productId' => $this->pen, 'version' => 1]]],
            ['onboardingVersion' => '2', 'products' => [['productId' => $this->pen, 'version' => 1]]],
            ['onboardingVersion' => 2, 'products' => []],
            ['onboardingVersion' => 2, 'products' => [['productId' => $this->rice, 'version' => 1]]],
            ['onboardingVersion' => 2, 'products' => [['productId' => $this->pen, 'version' => 1], ['productId' => $this->pen, 'version' => 1]]],
            ['onboardingVersion' => 2, 'products' => [['productId' => $this->pen, 'version' => '1']]],
        ] as $expected) {
            $this->send($this->envelopeOf(ConfirmOpeningStock::OPERATION, ['rows' => [self::zero($this->pen)]], $expected))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($before, $this->factsAndRows());

        // The longest texts and 100 rows are kept whole.
        $products = [];

        for ($row = 1; $row <= 100; $row++) {
            $products[] = TokoContoh::product(sprintf('MASSAL-%03d', $row), ['categoryId' => $this->category, 'name' => sprintf('Produk %03d', $row)]);
        }

        $rows = array_map(static fn (int $product): array => self::positive($product, '2', ['status' => 'KNOWN', 'amount' => '100'], str_repeat('c', 100)), $products);
        $commandId = self::number($this->send($this->entryOf($rows))->assertStatus(201)->json('commandId'));
        $this->assertSame(100, $this->watcher()->table('stock_movements')->where('command_id', $commandId)->where('movement_type', 'OPENING')->where('product_sequence', 1)->count());
        $this->assertSame(range(1, 100), $this->watcher()->table('opening_stock_lines')->orderBy('row_no')->pluck('row_no')->all());
    }

    public function test_a_zero_count_is_withdrawn_once_and_its_product_is_counted_again(): void
    {
        // AC-035-03, WF-25; BR-INV-27, BR-INV-47; D-087: the Owner withdraws a zero count with a reason; the
        // zero count stays, append-only, and the product may be counted by RC-11.
        TokoContoh::startOnboarding();
        $zeroCount = TokoContoh::zeroCount($this->book, 'LH-07');
        $this->owner();
        $hendra = $this->people['Hendra']['id'];
        $envelope = $this->envelopeOf(WithdrawZeroCount::OPERATION, ['zeroCountId' => $zeroCount, 'reason' => 'Salah hitung'], ['onboardingVersion' => 2]);

        $response = $this->send($envelope)->assertStatus(201);
        $this->assertJsonChannel($response, 'commands.committed', 'owner');
        $this->assertSame(['onboarding_zero_count', $zeroCount, self::LOCATION], [$response->json('result.type'), $response->json('result.id'), $response->json('result.location')]);
        $commandId = self::number($response->json('commandId'));
        $this->assertSame([$this->book, 'Salah hitung', $hendra, 'Hendra', $commandId], array_values((array) $this->watcher()->table('onboarding_zero_count_withdrawals')->where('zero_count_id', $zeroCount)->first(['product_id', 'reason', 'actor_user_id', 'actor_display_name', 'command_id'])));
        $this->assertSame(1, $this->watcher()->table('onboarding_zero_counts')->where('id', $zeroCount)->count());

        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['ZERO_COUNT_WITHDRAW', 'onboarding_zero_count', $zeroCount, $this->book, 'Salah hitung'], [$event['action'], $event['subject_type'], $event['subject_id'], $event['product_id'], $event['reason']]);
        $this->assertSame(['productId' => $this->book, 'zeroCountId' => $zeroCount], self::dataOf($event['change_data']));

        // A second withdrawal, another intent, finds it withdrawn; an unknown zero count is not found.
        $facts = $this->factsAndRows();
        $this->send($envelope)->assertStatus(200)->assertJsonPath('repeated', true);
        $this->send($this->envelopeOf(WithdrawZeroCount::OPERATION, ['zeroCountId' => $zeroCount, 'reason' => 'Lagi'], ['onboardingVersion' => 2]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->send($this->envelopeOf(WithdrawZeroCount::OPERATION, ['zeroCountId' => 999999, 'reason' => 'Lagi'], ['onboardingVersion' => 2]))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->send($this->envelopeOf(WithdrawZeroCount::OPERATION, ['zeroCountId' => $zeroCount, 'reason' => 'Lagi'], ['onboardingVersion' => 1]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->assertSame($facts, $this->factsAndRows());

        // The book is not counted again until RC-11 counts it, now with an OPENING (G-22).
        $this->send($this->entryOf([self::positive($this->book, '4', ['status' => 'KNOWN', 'amount' => '8000'])]))->assertStatus(201);
        $this->assertSame([['4.000', 1], 1], [$this->balanceOf($this->book), $this->watcher()->table('onboarding_zero_counts')->where('product_id', $this->book)->count()]);
    }

    public function test_a_reversed_opening_is_replaced_once_at_its_products_next_place(): void
    {
        // AC-035-03, WF-25; BR-INV-34, BR-INV-48; D-087; G-24: 10 pens at Rp100.000, reversed through RC-10
        // during onboarding, replaced by 8 at Rp96.000 at the third place, once.
        TokoContoh::startOnboarding();
        $original = TokoContoh::openingStock($this->pen, '10', '100000');
        $line = TokoContoh::openingLineOf($original);
        $this->owner();
        $hendra = $this->people['Hendra']['id'];
        $payload = ['replacesLineId' => $line, 'quantity' => '8', 'countReference' => 'LH-09', 'cost' => ['status' => 'KNOWN', 'amount' => '96000'], 'reason' => 'Hitung ulang'];

        // Not before the reversal (STALE_VERSION).
        $before = $this->factsAndRows();
        $this->send($this->envelopeOf(ReplaceOpeningStock::OPERATION, $payload, ['onboardingVersion' => 2, 'productVersion' => 1]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->assertSame($before, $this->factsAndRows());

        // The OPENING reversed while onboarding is active (BR-INV-34; D-087).
        $reversal = $this->send($this->envelopeOf('reversal.confirm', ['movementId' => $original, 'reason' => 'Salah hitung'], []))->assertStatus(201);
        $this->assertSame(['0.000', 2], $this->balanceOf($this->pen));
        $this->assertSame('reversal', $reversal->json('result.type'));

        $envelope = $this->envelopeOf(ReplaceOpeningStock::OPERATION, $payload, ['onboardingVersion' => 2, 'productVersion' => 1]);
        $response = $this->send($envelope)->assertStatus(201);
        $this->assertJsonChannel($response, 'commands.committed', 'owner');
        $entry = self::number($response->json('result.id'));
        $commandId = self::number($response->json('commandId'));
        $this->assertSame(['opening_stock_entry', self::LOCATION], [$response->json('result.type'), $response->json('result.location')]);

        // The entry of one row, whose line names the line it replaces and its OPENING.
        $this->assertSame([1, $hendra, $commandId], array_values((array) $this->watcher()->table('opening_stock_entries')->where('id', $entry)->first(['row_count', 'actor_user_id', 'command_id'])));
        $newLine = (array) $this->watcher()->table('opening_stock_lines')->where('opening_stock_entry_id', $entry)->sole();
        $this->assertSame([1, $this->pen, '8.000', 'LH-09', $line, $original, 'Hitung ulang', true], [$newLine['row_no'], $newLine['product_id'], $newLine['quantity'], $newLine['count_reference'], $newLine['replaces_line_id'], $newLine['replaces_movement_id'], $newLine['replacement_reason'], $newLine['is_replacement']]);

        // The replacement OPENING at the third place, with its evidence; the original and its REVERSAL stay.
        $replacement = (array) $this->watcher()->table('stock_movements')->where('command_id', $commandId)->sole();
        $this->assertSame(['OPENING', 3, '8.000', true, $newLine['id']], [$replacement['movement_type'], $replacement['product_sequence'], $replacement['quantity'], $replacement['opening_replacement'], $replacement['opening_line_id']]);
        $this->assertSame(['OPENING', 'REVERSAL', 'OPENING'], $this->watcher()->table('stock_movements')->where('product_id', $this->pen)->orderBy('product_sequence')->pluck('movement_type')->all());
        $evidence = (array) $this->watcher()->table('acquisition_cost_evidence')->where('movement_id', $replacement['id'])->sole();
        $this->assertSame(['KNOWN', '96000.000000', 'LH-09'], [$evidence['cost_status'], $evidence['total_amount'], $evidence['reference']]);
        $this->assertSame(['8.000', 3], $this->balanceOf($this->pen));
        $this->assertSame('KNOWN|96000.000000|3|3', $this->valuationOf($this->pen));

        // One audit event, with the reason in its own column and the cost the Owner's (BR-AUD-02).
        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['OPENING_STOCK_REPLACE', 'opening_stock_entry', $entry, $this->pen, 'Hitung ulang'], [$event['action'], $event['subject_type'], $event['subject_id'], $event['product_id'], $event['reason']]);
        $this->assertSame(['countReference' => 'LH-09', 'lineId' => $newLine['id'], 'movementId' => $replacement['id'], 'productSequence' => 3, 'quantity' => '8', 'replacesLineId' => $line, 'replacesMovementId' => $original], self::dataOf($event['change_data']));
        $this->assertSame(['amount' => '96000', 'evidenceId' => $evidence['id'], 'status' => 'KNOWN', 'zeroNote' => null], self::dataOf($event['owner_only_data']));

        // A second replacement of the line, another intent, finds it replaced; an unknown line is not found;
        // the product, now counted, takes no new entry (G-22, G-24).
        $facts = $this->factsAndRows();
        $this->send($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $entry);
        $this->send($this->envelopeOf(ReplaceOpeningStock::OPERATION, $payload, ['onboardingVersion' => 2, 'productVersion' => 1]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->send($this->envelopeOf(ReplaceOpeningStock::OPERATION, [...$payload, 'replacesLineId' => 999999], ['onboardingVersion' => 2, 'productVersion' => 1]))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->send($this->entryOf([self::zero($this->pen)]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame($facts, $this->factsAndRows());
    }

    public function test_a_product_whose_opening_was_reversed_takes_a_zero_count_or_its_replacement_never_both(): void
    {
        // AC-035-03; G-22, G-24; INTERACTION_CONTRACTS RC-11 and RC-48, "Under locks" (review R-01): once its
        // OPENING is reversed, a product takes no positive row, since only RC-48 records it again; a zero row
        // records its zero count, and while that is effective RC-48 is refused; once it is withdrawn, RC-48
        // records the replacement. Each refusal writes nothing.
        TokoContoh::startOnboarding();
        $opening = TokoContoh::openingStock($this->pen, '10', '100000');
        TokoContoh::reverse($opening);
        $line = TokoContoh::openingLineOf($opening);
        $this->owner();
        $known = ['status' => 'KNOWN', 'amount' => '1000'];
        $replacement = ['replacesLineId' => $line, 'quantity' => '8', 'countReference' => 'LH-09', 'cost' => ['status' => 'KNOWN', 'amount' => '96000'], 'reason' => 'Hitung ulang'];
        $before = $this->factsAndRows();

        // A positive row of it is not eligible, and the row beside it is not posted either.
        $refused = $this->send($this->entryOf([self::positive($this->rice, '1', $known), self::positive($this->pen, '8', $known)]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame(['payload.rows.1.productId' => [['key' => 'inventory.opening_entry.not_eligible', 'params' => []]]], $refused->json('fields'));
        $this->assertSame($before, $this->factsAndRows());

        // A zero row of it records its zero count, with no movement.
        $this->send($this->entryOf([self::zero($this->pen)]))->assertStatus(201);
        $zeroCount = self::number($this->watcher()->table('onboarding_zero_counts')->where('product_id', $this->pen)->value('id'));
        $this->assertSame(['OPENING', 'REVERSAL'], $this->watcher()->table('stock_movements')->where('product_id', $this->pen)->orderBy('product_sequence')->pluck('movement_type')->all());

        // While that zero count is effective, the replacement is not eligible (G-22).
        $counted = $this->factsAndRows();
        $refused = $this->send($this->envelopeOf(ReplaceOpeningStock::OPERATION, $replacement, ['onboardingVersion' => 2, 'productVersion' => $this->versionOf($this->pen)]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame(['payload.replacesLineId' => [['key' => 'inventory.opening_entry.not_eligible', 'params' => []]]], $refused->json('fields'));
        $this->assertSame($counted, $this->factsAndRows());

        // Once it is withdrawn, the replacement is recorded at the product's third place.
        $this->send($this->envelopeOf(WithdrawZeroCount::OPERATION, ['zeroCountId' => $zeroCount, 'reason' => 'Salah hitung'], ['onboardingVersion' => 2]))->assertStatus(201);
        $this->send($this->envelopeOf(ReplaceOpeningStock::OPERATION, $replacement, ['onboardingVersion' => 2, 'productVersion' => $this->versionOf($this->pen)]))->assertStatus(201);
        $this->assertSame(['OPENING', 'REVERSAL', 'OPENING'], $this->watcher()->table('stock_movements')->where('product_id', $this->pen)->orderBy('product_sequence')->pluck('movement_type')->all());
        $this->assertSame(['8.000', 3], $this->balanceOf($this->pen));
    }

    public function test_the_refusals_of_a_withdrawal_and_a_replacement_write_nothing_and_settle_their_receipts(): void
    {
        // EXECUTION_CONTEXT section 10, a command and a route (review R-02); TR-VAL-01 to TR-VAL-07; COPY_DECK
        // section 16.1; INTERACTION_CONTRACTS RC-11, RC-14 and RC-48, "Under locks"; G-25: each field refused
        // with its key, each refusal under the locks, a guessed product NOT_FOUND; every receipt REJECTED.
        TokoContoh::startOnboarding();
        $zeroCount = TokoContoh::zeroCount($this->book);
        $penOpening = TokoContoh::openingStock($this->pen, '10', '100000');
        TokoContoh::reverse($penOpening);
        $glue = TokoContoh::product('LEM-01', ['categoryId' => $this->category, 'name' => 'Lem']);
        $glueOpening = TokoContoh::openingStock($glue, '2');
        TokoContoh::reverse($glueOpening);
        TokoContoh::postingBlock($glue);
        $sugar = TokoContoh::product('GULA-01', ['categoryId' => $this->category, 'name' => 'Gula']);
        $sugarOpening = TokoContoh::openingStock($sugar, '2');
        TokoContoh::reverse($sugarOpening);
        $this->owner();
        $this->command('product.deactivate', ['productId' => $sugar], ['productVersion' => $this->versionOf($sugar)])->assertStatus(201);
        $before = $this->factsAndRows();

        // RC-48's fields, each with its key before any lock.
        foreach ([
            [['quantity' => '0'], 'payload.quantity', 'ux.validation.quantity_positive', []],
            [['quantity' => '100000000'], 'payload.quantity', 'ux.validation.quantity_max', []],
            [['countReference' => null], 'payload.countReference', 'ux.validation.required', []],
            [['countReference' => str_repeat('c', 101)], 'payload.countReference', 'ux.validation.text_max', ['maks' => 100]],
            [['reason' => null], 'payload.reason', 'ux.validation.required', []],
            [['reason' => str_repeat('r', 1001)], 'payload.reason', 'ux.validation.text_max', ['maks' => 1000]],
            [['cost' => null], 'payload.cost', 'ux.validation.required', []],
            [['cost' => ['status' => 'KNOWN']], 'payload.cost.amount', 'ux.validation.required', []],
            [['cost' => ['status' => 'KNOWN', 'amount' => '0']], 'payload.cost.zeroEvidence', 'ux.validation.zero_evidence', []],
        ] as $index => [$change, $path, $key, $params]) {
            $fields = (array) $this->send($this->replacementOf($penOpening, $this->pen, $change))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')->json('fields');
            $this->assertSame(['key' => $key, 'params' => $params], ((array) ($fields[$path] ?? []))[0] ?? null, sprintf('case %d at %s', $index, $path));
        }

        // What no page sends: another field, a key as a text, a quantity as a number, UNKNOWN with an
        // amount, a control character.
        foreach ([['supplier' => 'Toko Lain'], ['replacesLineId' => (string) TokoContoh::openingLineOf($penOpening)], ['quantity' => 2], ['cost' => ['status' => 'UNKNOWN', 'amount' => '5']], ['reason' => "Salah\nhitung"]] as $change) {
            $this->send($this->replacementOf($penOpening, $this->pen, $change))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // Under the locks: a blocked product, an inactive one, a version that changed, a quantity beyond
        // the product's precision.
        $this->send($this->replacementOf($glueOpening, $glue))->assertStatus(422)->assertJsonPath('code', 'POSTING_BLOCKED');
        $this->send($this->replacementOf($sugarOpening, $sugar))->assertStatus(422)->assertJsonPath('code', 'PRODUCT_INACTIVE');
        $this->send($this->replacementOf($penOpening, $this->pen, [], 1))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $precision = $this->send($this->replacementOf($penOpening, $this->pen, ['quantity' => '2.5']))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame(['payload.quantity' => [['key' => 'ux.validation.quantity_whole', 'params' => []]]], $precision->json('fields'));

        // RC-14's fields; and RC-11 of a product that does not exist (TR-ERR-03).
        foreach ([[['reason' => null], 'ux.validation.required', []], [['reason' => str_repeat('r', 1001)], 'ux.validation.text_max', ['maks' => 1000]]] as $index => [$change, $key, $params]) {
            $fields = (array) $this->send($this->envelopeOf(WithdrawZeroCount::OPERATION, [...['zeroCountId' => $zeroCount, 'reason' => 'Salah hitung'], ...$change], ['onboardingVersion' => 2]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')->json('fields');
            $this->assertSame(['key' => $key, 'params' => $params], ((array) ($fields['payload.reason'] ?? []))[0] ?? null, sprintf('case %d', $index));
        }

        foreach ([['zeroCountId' => (string) $zeroCount], ['zeroCountId' => 0], ['supplier' => 'Toko Lain']] as $change) {
            $this->send($this->envelopeOf(WithdrawZeroCount::OPERATION, [...['zeroCountId' => $zeroCount, 'reason' => 'Salah hitung'], ...$change], ['onboardingVersion' => 2]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->send($this->envelopeOf(ConfirmOpeningStock::OPERATION, ['rows' => [self::zero(999999)]], ['onboardingVersion' => 2, 'products' => [['productId' => 999999, 'version' => 1]]]))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        // Nothing was written, and every receipt of the three is REJECTED (G-25).
        $this->assertSame($before, $this->factsAndRows());

        foreach ([ReplaceOpeningStock::OPERATION, WithdrawZeroCount::OPERATION, ConfirmOpeningStock::OPERATION] as $operation) {
            $this->assertSame(['REJECTED'], $this->watcher()->table('command_request_receipts')->where('operation', $operation)->distinct()->pluck('outcome')->all(), $operation);
        }

        // The same commands, as the page sends them, commit, and their receipts say so.
        $this->send($this->envelopeOf(WithdrawZeroCount::OPERATION, ['zeroCountId' => $zeroCount, 'reason' => 'Salah hitung'], ['onboardingVersion' => 2]))->assertStatus(201);
        $this->send($this->replacementOf($penOpening, $this->pen))->assertStatus(201);

        foreach ([ReplaceOpeningStock::OPERATION, WithdrawZeroCount::OPERATION] as $operation) {
            $this->assertSame(1, $this->watcher()->table('command_request_receipts')->where('operation', $operation)->where('outcome', 'COMMITTED')->count(), $operation);
        }
    }

    public function test_the_release_ends_every_entry_and_every_correction_and_the_postings_run_again(): void
    {
        // AC-035-04; BR-INV-29, BR-INV-47; D-071, D-087; G-10, G-26: after the release no entry, zero count,
        // withdrawal or replacement is taken, and the ordinary postings run again.
        TokoContoh::startOnboarding();
        $zeroCount = TokoContoh::zeroCount($this->book);
        $opening = TokoContoh::openingStock($this->rice, '4', '4000');
        TokoContoh::reverse($opening);
        TokoContoh::releaseOnboarding();
        $this->owner();
        $before = $this->factsAndRows();

        $this->send($this->entryOf([self::positive($this->pen, '1', ['status' => 'KNOWN', 'amount' => '1000'])], 3))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->send($this->envelopeOf(WithdrawZeroCount::OPERATION, ['zeroCountId' => $zeroCount, 'reason' => 'Salah hitung'], ['onboardingVersion' => 3]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->send($this->envelopeOf(ReplaceOpeningStock::OPERATION, ['replacesLineId' => TokoContoh::openingLineOf($opening), 'quantity' => '4', 'countReference' => 'LH-02', 'cost' => ['status' => 'UNKNOWN'], 'reason' => 'Hitung ulang'], ['onboardingVersion' => 3, 'productVersion' => 1]))
            ->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->assertSame($before, $this->factsAndRows());

        TokoContoh::stock($this->pen, '5', '5000');
        $this->assertSame(['5.000', 1], $this->balanceOf($this->pen));
    }

    public function test_recent_authentication_and_the_owner_alone_and_the_guard_in_the_transaction_holds_too(): void
    {
        // AC-035-01; AUTH03, INTERACTION_CONTRACTS section 1.5: RC-12, RC-13, RC-14 and RC-48 answer 423
        // without a recent password confirmation and run nothing, with no receipt; RC-11 needs none.
        // CAP-15 is the Owner's alone: Operations, the cashier and the combined account are refused at the
        // gate, with no receipt, and by the guard in the transaction (SEC-AZ-04).
        $this->as('Hendra');
        $this->send($this->envelopeOf(StartOnboarding::OPERATION, [], ['onboardingVersion' => 1]))->assertStatus(423)->assertJsonPath('code', 'RECENT_AUTH_REQUIRED');
        TokoContoh::startOnboarding();
        $zeroCount = TokoContoh::zeroCount($this->book);
        $opening = TokoContoh::openingStock($this->rice, '4', '4000');
        TokoContoh::reverse($opening);
        $commands = [
            [StartOnboarding::OPERATION, [], ['onboardingVersion' => 2]],
            [ReleaseOnboarding::OPERATION, [], ['onboardingVersion' => 2]],
            [WithdrawZeroCount::OPERATION, ['zeroCountId' => $zeroCount, 'reason' => 'Salah hitung'], ['onboardingVersion' => 2]],
            [ReplaceOpeningStock::OPERATION, ['replacesLineId' => TokoContoh::openingLineOf($opening), 'quantity' => '4', 'countReference' => 'LH-02', 'cost' => ['status' => 'UNKNOWN'], 'reason' => 'Hitung ulang'], ['onboardingVersion' => 2, 'productVersion' => 1]],
        ];
        $before = $this->factsAndRows();

        foreach ($commands as [$operation, $payload, $expected]) {
            $this->send($this->envelopeOf($operation, $payload, $expected))->assertStatus(423)->assertJsonPath('code', 'RECENT_AUTH_REQUIRED');
        }

        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->whereIn('operation', [StartOnboarding::OPERATION, ReleaseOnboarding::OPERATION, WithdrawZeroCount::OPERATION, ReplaceOpeningStock::OPERATION])->count());
        $this->assertSame($before, $this->factsAndRows());

        $entry = [ConfirmOpeningStock::OPERATION, ['rows' => [self::zero($this->pen)]], ['onboardingVersion' => 2, 'products' => [['productId' => $this->pen, 'version' => 1]]]];

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->postJson('/user/confirm-password', ['password' => $this->people[$person]['password']])->assertStatus(201);

            foreach ([...$commands, $entry] as [$operation, $payload, $expected]) {
                $this->send($this->envelopeOf($operation, $payload, $expected))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
            }

            $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('actor_user_id', $this->people[$person]['id'])->count(), $person);
        }

        foreach (['Agus', 'Dewi'] as $person) {
            foreach ([ConfirmOpeningStock::class => $entry, StartOnboarding::class => $commands[0]] as $action => [$operation, $payload, $expected]) {
                $envelope = new Envelope($operation, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, $expected, $payload);

                try {
                    app($action)->execute(new CommandContext($this->people[$person]['id'], $envelope, app(Clock::class)->now()));
                    $this->fail($person.' passed the guard of '.$operation.'.');
                } catch (ActorNotAllowed) {
                    $this->addToAssertionCount(1);
                }
            }
        }

        $this->assertSame($before, $this->factsAndRows());

        $this->browse(null);
        $this->send($this->envelopeOf(ConfirmOpeningStock::OPERATION, $entry[1], $entry[2]))->assertStatus(401);
        $this->assertSame($before, $this->factsAndRows());
    }

    public function test_a_failure_after_every_write_step_leaves_no_fact_and_the_receipt_pending(): void
    {
        // TS-21. RC-11 of a positive row and a zero row writes 9 steps — the balance, the valuation, the
        // entry, the line, the OPENING, its evidence, the zero count, the audit event, the result; RC-12
        // 3 — the state, the audit event, the result; RC-48 8 — the balance, the valuation, the entry, the
        // line, the OPENING, its evidence, the audit event, the result; RC-14 3 — the withdrawal, the audit
        // event, the result; RC-13 3 — the release record with the state, the audit event, the result.
        $this->owner();
        $this->assertWriteSteps(StartOnboarding::OPERATION, fn (): array => $this->envelopeOf(StartOnboarding::OPERATION, [], ['onboardingVersion' => 1]), ['after_lock:2.1', 'after_lock:2.3'], 3);
        $this->assertSame('ACTIVE', $this->watcher()->table('onboarding_state')->value('state'));

        $rows = [self::positive($this->pen, '3', ['status' => 'KNOWN', 'amount' => '3000']), self::zero($this->book)];
        $this->assertWriteSteps(ConfirmOpeningStock::OPERATION, fn (): array => $this->entryOf($rows), ['after_lock:2.1', 'after_lock:2.3', 'after_lock:6', 'after_lock:7'], 9);
        $this->assertSame(['3.000', 1], $this->balanceOf($this->pen));

        $opening = TokoContoh::openingOf($this->pen);
        TokoContoh::reverse($opening);
        $payload = ['replacesLineId' => TokoContoh::openingLineOf($opening), 'quantity' => '2', 'countReference' => 'LH-02', 'cost' => ['status' => 'UNKNOWN'], 'reason' => 'Hitung ulang'];
        $this->assertWriteSteps(ReplaceOpeningStock::OPERATION, fn (): array => $this->envelopeOf(ReplaceOpeningStock::OPERATION, $payload, ['onboardingVersion' => 2, 'productVersion' => 1]), ['after_lock:2.1', 'after_lock:2.3', 'after_lock:6', 'after_lock:7'], 8);
        $this->assertSame(['2.000', 3], $this->balanceOf($this->pen));

        $zeroCount = self::number($this->watcher()->table('onboarding_zero_counts')->where('product_id', $this->book)->value('id'));
        $this->assertWriteSteps(WithdrawZeroCount::OPERATION, fn (): array => $this->envelopeOf(WithdrawZeroCount::OPERATION, ['zeroCountId' => $zeroCount, 'reason' => 'Salah hitung'], ['onboardingVersion' => 2]), ['after_lock:2.1', 'after_lock:2.3', 'after_lock:6'], 3);
        $this->assertSame(1, $this->watcher()->table('onboarding_zero_count_withdrawals')->where('zero_count_id', $zeroCount)->count());

        $this->assertWriteSteps(ReleaseOnboarding::OPERATION, fn (): array => $this->envelopeOf(ReleaseOnboarding::OPERATION, [], ['onboardingVersion' => 2]), ['after_lock:2.1', 'after_lock:2.3'], 3);
        $this->assertSame(['RELEASED', 3, 1], [$this->watcher()->table('onboarding_state')->value('state'), $this->onboardingVersion(), $this->watcher()->table('onboarding_releases')->count()]);
    }

    public function test_each_table_of_the_onboarding_has_its_one_writer(): void
    {
        // STK-12, ARC-20, ARC-21: the onboarding's tables are named by the Inventory module's writers
        // alone, and the checklist reads the lines; the restore holds are TASK-037's to write.
        $this->assertSame(['Domain/Inventory/Onboarding/OpeningStock.php'], self::filesNaming('opening_stock_entries'));
        $this->assertSame(['Domain/Inventory/Onboarding/OpeningStock.php', 'Queries/Inventory/OnboardingChecklist.php'], self::filesNaming('opening_stock_lines'));
        $this->assertSame(['Domain/Inventory/Onboarding/OpeningStock.php'], self::filesNaming('onboarding_zero_counts'));
        $this->assertSame(['Domain/Inventory/Onboarding/OpeningStock.php'], self::filesNaming('onboarding_zero_count_withdrawals'));
        $this->assertSame(['Domain/Inventory/OnboardingState.php'], self::filesNaming('onboarding_releases'));
        $this->assertSame(['Domain/Inventory/OnboardingState.php'], self::filesNaming('onboarding_state'));
        $this->assertSame([], self::filesNaming('onboarding_restore_holds'));
        $this->assertSame([], self::filesNaming('onboarding_restore_reconciliations'));
        $this->assertSame(['onboarding.release', 'onboarding.start', 'onboarding.withdraw_zero_count', 'opening_stock.confirm', 'opening_stock.replace'], self::operationsOf(['onboarding.', 'opening_stock.']));
    }

    /**
     * Each write step of an operation fails in its turn, leaving the facts as they were and its receipt
     * PENDING; then all of them pass, through these locks, and the command commits.
     *
     * @param  callable(): array<string, mixed>  $envelope  a new envelope each time
     * @param  list<string>  $locks
     */
    private function assertWriteSteps(string $operation, callable $envelope, array $locks, int $steps): void
    {
        for ($step = 1; $step <= $steps; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $before = $this->factsAndRows();

            $this->send($envelope())->assertStatus(500);

            $this->assertSame($before, $this->factsAndRows(), sprintf('%s after write step %d', $operation, $step));
            $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', $operation)->orderByDesc('id')->value('outcome'), $operation.' '.$step);
        }

        $points = new FailurePoints($steps + 1);
        $this->app->instance(CommandPoints::class, $points);
        $this->send($envelope())->assertStatus(201);
        $this->assertSame([...$locks, ...array_map(static fn (int $step): string => 'after_write:'.$step, range(1, $steps))], array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))), $operation);
        $this->app->instance(CommandPoints::class, new FailurePoints);
    }

    /**
     * A positive row of RC-11.
     *
     * @param  array<string, string>  $cost
     * @return array<string, mixed>
     */
    private static function positive(int $product, string $quantity, array $cost, string $countReference = 'LH-01'): array
    {
        return ['productId' => $product, 'quantity' => $quantity, 'countReference' => $countReference, 'cost' => $cost];
    }

    /**
     * A zero row of RC-11, without a count reference.
     *
     * @return array<string, mixed>
     */
    private static function zero(int $product): array
    {
        return ['productId' => $product, 'quantity' => '0', 'countReference' => null];
    }

    /** The Owner, with the recent authentication of RC-12 to RC-14 and RC-48 (INTERACTION_CONTRACTS section 1.5). */
    private function owner(): void
    {
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
    }

    /**
     * An envelope of RC-11 for these rows, at the onboarding version and the version of each of their
     * products as they stand, once, unless given.
     *
     * @param  array<array-key, array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>|null  $products
     * @return array<string, mixed>
     */
    private function entryOf(array $rows, ?int $onboardingVersion = null, ?array $products = null): array
    {
        if ($products === null) {
            $versions = [];

            foreach ($rows as $row) {
                $product = self::number($row['productId'] ?? null);
                $versions[$product] ??= ['productId' => $product, 'version' => $this->versionOf($product)];
            }

            $products = array_values($versions);
        }

        return $this->envelopeOf(ConfirmOpeningStock::OPERATION, ['rows' => $rows], ['onboardingVersion' => $onboardingVersion ?? $this->onboardingVersion(), 'products' => $products]);
    }

    /**
     * An envelope of an operation with its payload and the values reviewed, under a new intent and key.
     *
     * @param  array<array-key, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function envelopeOf(string $operation, array $payload, array $expected): array
    {
        return $this->envelope($payload, ['operation' => $operation, 'expected' => $expected === [] ? (object) [] : $expected]);
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function send(array $envelope): TestResponse
    {
        return $this->postJson('/commands/'.self::text($envelope['operation'] ?? null), $envelope);
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
        return $this->send($this->envelopeOf($operation, $payload, $expected));
    }

    private function onboardingVersion(): int
    {
        return self::number($this->watcher()->table('onboarding_state')->value('version'));
    }

    private function versionOf(int $product): int
    {
        return self::number($this->watcher()->table('products')->where('id', $product)->value('version'));
    }

    /**
     * An envelope of RC-48 for the line of this OPENING of a product, at the versions as they stand, the
     * product's moved on by the step, with these changes to the payload as a page sends it.
     *
     * @param  array<string, mixed>  $change
     * @return array<string, mixed>
     */
    private function replacementOf(int $opening, int $product, array $change = [], int $versionStep = 0): array
    {
        $payload = [...['replacesLineId' => TokoContoh::openingLineOf($opening), 'quantity' => '2', 'countReference' => 'LH-09', 'cost' => ['status' => 'UNKNOWN'], 'reason' => 'Hitung ulang'], ...$change];

        return $this->envelopeOf(ReplaceOpeningStock::OPERATION, $payload, ['onboardingVersion' => $this->onboardingVersion(), 'productVersion' => $this->versionOf($product) + $versionStep]);
    }

    /**
     * A restore hold waiting for its reconciliation, as the after-restore procedure of TASK-037 sets it
     * for the state that the restored backup held (D-072).
     */
    private function hold(string $restoredState): int
    {
        return $this->runtime()->table('onboarding_restore_holds')->insertGetId(['restored_state' => $restoredState, 'recovery_epoch' => '\x00112233445566778899aabbccddeeff', 'set_at' => now()]);
    }

    /** The Owner's reconciliation of a restore hold (D-072). */
    private function reconcile(int $hold): void
    {
        $this->runtime()->table('onboarding_restore_reconciliations')->insert(['hold_id' => $hold, 'outcome' => 'NEVER_RELEASED', 'decided_by_user_id' => $this->people['Hendra']['id'], 'operator_note' => 'Belum pernah diakhiri', 'recorded_at' => now()]);
    }

    /**
     * A product's balance: on hand and the last sequence, or null without one.
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
     * The operations of the families that begin so, in their order.
     *
     * @param  list<string>  $prefixes
     * @return list<string>
     */
    private static function operationsOf(array $prefixes): array
    {
        $operations = array_values(array_filter(array_keys(Operations::FAMILIES), static fn (string $operation): bool => array_filter($prefixes, static fn (string $prefix): bool => str_starts_with($operation, $prefix)) !== []));
        sort($operations);

        return $operations;
    }

    /**
     * The files of app/ that name this table in a string, as the query builder takes it; a mention in a
     * comment or inside a statement is no access.
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
     * The facts, with the rows that an onboarding command changes: the onboarding state, the balances,
     * the valuations and the products, whose versions it never moves.
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
            'onboarding' => $this->tableRows('onboarding_state', 'id'),
            'balances' => $this->tableRows('stock_balances', 'product_id'),
            'valuations' => $this->tableRows('inventory_valuations', 'product_id'),
            'products' => $this->tableRows('products', 'id'),
        ];
    }
}
