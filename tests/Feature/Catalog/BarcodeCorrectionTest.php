<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\CorrectBarcodeMisassignment;
use App\Actions\Commands\CommandContext;
use App\Database\CommandPoints;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Catalog\Barcodes\Barcodes;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\Operations;
use App\Support\Time\Clock;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-30, RD-05, the context `correction` of RD-01 and RP-305 through their routes
 * (AC-020-01, AC-020-02; INTERACTION_CONTRACTS RC-30, sections 3.1 and 3.5; TECHNICAL_RULES
 * section 7.2; BR-CAT-19, BR-CAT-50 to BR-CAT-52; D-051, D-052; G-09; CAP-07; EXECUTION_CONTEXT
 * section 10, a command, a read and a page; suites BARCODE01, AUTH01, AUTH02, AUTH03, CMD01): a
 * move and a re-attachment, each with its facts, audit event, durable result and repeat; the
 * history that stays; every token that is refused; the mapping that changed since the lookup;
 * recent authentication and the roles, in the route and in the transaction; the failure after
 * every write step.
 */
final class BarcodeCorrectionTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string LOOKUP = '/data/barcode-tokens/lookup';

    private const string SEARCH = '/data/products/search';

    private const string COMMAND = '/commands/barcode.correct_misassignment';

    private const string ACTIVE = 'ACTIVE';

    private const string RETIRED = 'RETIRED_MISASSIGNMENT';

    /** The tables that a refused command leaves as they were. */
    private const array FACTS = ['barcode_tokens', 'barcode_mappings', 'barcode_mapping_retirements', 'barcode_active_mappings', 'command_identities', 'command_results', 'audit_events'];

    private int $category = 0;

    private int $wrong = 0;

    private int $right = 0;

    private int $third = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category();
        $this->wrong = TokoContoh::product('WRONG-1', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam']);
        $this->right = TokoContoh::product('RIGHT-1', ['categoryId' => $this->category, 'name' => 'Pulpen Biru']);
        $this->third = TokoContoh::product('THIRD-1', ['categoryId' => $this->category, 'name' => 'Pensil']);
    }

    public function test_an_active_token_moves_to_the_correct_product_with_its_history_kept(): void
    {
        // AC-020-02: BR-CAT-50, D-051.
        $old = TokoContoh::barcode($this->wrong, 'SALAH-1');
        $this->owner();
        $before = $this->tableRows('barcode_mappings');
        $envelope = $this->envelopeOf('SALAH-1', $this->right, 'Salah tempel di kemasan', $this->wrong, self::ACTIVE);

        $response = $this->postJson(self::COMMAND, $envelope)->assertStatus(201);
        $this->assertJsonChannel($response, 'commands.committed.barcode', 'owner');
        $mapping = self::number($response->json('result.id'));
        $commandId = self::number($response->json('commandId'));
        $owner = $this->people['Hendra']['id'];
        $this->assertSame(['barcode_mapping', 'SALAH-1', '/products/'.$this->right], [$response->json('result.type'), $response->json('result.number'), $response->json('result.location')]);

        // The new mapping, with the reason and the earlier mapping it corrects.
        $new = (array) $this->watcher()->table('barcode_mappings')->where('id', $mapping)->sole();
        $this->assertSame([$this->right, 'MANUFACTURER', 'MISASSIGNMENT_CORRECTION', $old, 'MISASSIGNMENT', 'Salah tempel di kemasan', $owner, 'Hendra', $commandId], [$new['product_id'], $new['token_kind'], $new['attach_kind'], $new['corrects_mapping_id'], $new['corrected_reason_class'], $new['reason'], $new['actor_user_id'], $new['actor_display_name'], $new['command_id']]);

        // The old one retired as a misassignment, in the same command, and no longer active.
        $retirement = (array) $this->watcher()->table('barcode_mapping_retirements')->where('mapping_id', $old)->sole();
        $this->assertSame(['MISASSIGNMENT', $owner, 'Hendra', $commandId], [$retirement['reason_class'], $retirement['actor_user_id'], $retirement['actor_display_name'], $retirement['command_id']]);
        $tokenId = $new['token_id'];
        $active = (array) $this->watcher()->table('barcode_active_mappings')->where('token_id', $tokenId)->sole();
        $this->assertSame([$mapping, $this->right], [$active['mapping_id'], $active['product_id']]);

        // The earlier mapping stands as it was (BR-AUD-03), beside the new one.
        $this->assertSame($before, array_slice($this->tableRows('barcode_mappings'), 0, count($before)));

        // One audit event with from, to and why (BR-CAT-50).
        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['BUSINESS', 'BARCODE_CORRECTION', 'USER', $owner, 'Hendra', 'barcode_mapping', $mapping, $this->right, 'Salah tempel di kemasan'], [$event['category'], $event['action'], $event['actor_kind'], $event['actor_user_id'], $event['actor_display_name'], $event['subject_type'], $event['subject_id'], $event['product_id'], $event['reason']]);
        $this->assertSame(['barcode' => 'SALAH-1', 'correctedMappingId' => $old, 'fromProductId' => $this->wrong, 'kind' => 'MOVE', 'mappingId' => $mapping, 'toProductId' => $this->right], self::dataOf($event['change_data']));

        // The result holds references only (SCHEMA section 5.3), and a repeat returns it, writing nothing.
        $stored = (array) $this->watcher()->table('command_results')->where('command_id', $commandId)->sole();
        $this->assertSame(['barcode_mapping', $mapping, []], [$stored['result_type'], $stored['result_ref'], self::dataOf($stored['result_detail'])]);
        $this->assertSame(['COMMITTED'], $this->outcomesOf($envelope));
        $facts = $this->facts();
        $this->postJson(self::COMMAND, $envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('commandId', $commandId)->assertJsonPath('result.id', $mapping)->assertJsonPath('result.number', 'SALAH-1');
        $this->assertSame($facts, $this->facts());

        // A barcode changes no field of its product; a scan now finds the correct one.
        $this->assertSame([1, 1], [$this->watcher()->table('products')->where('id', $this->wrong)->value('version'), $this->watcher()->table('products')->where('id', $this->right)->value('version')]);
        $this->assertSame([$this->right], $this->exactProducts('SALAH-1', 'catalog'));

        // An ordinary add still refuses it (BR-CAT-19).
        $this->postJson('/commands/barcode.add', $this->envelope(['productId' => $this->third, 'token' => 'SALAH-1'], ['operation' => 'barcode.add']))->assertStatus(422)->assertJsonPath('code', 'DUPLICATE_BARCODE');
    }

    public function test_a_token_retired_as_a_misassignment_is_attached_again_with_its_history_kept(): void
    {
        // AC-020-02: BR-CAT-51, D-052.
        $old = TokoContoh::barcode($this->wrong, 'SALAH-2');
        TokoContoh::retireBarcode($old, 'MISASSIGNMENT');
        $this->owner();
        $retirements = $this->tableRows('barcode_mapping_retirements');
        $this->assertSame([], $this->exactProducts('SALAH-2', 'catalog'));

        $response = $this->postJson(self::COMMAND, $this->envelopeOf('SALAH-2', $this->right, 'Terbukti salah pasang', $this->wrong, self::RETIRED))->assertStatus(201);
        $mapping = self::number($response->json('result.id'));
        $commandId = self::number($response->json('commandId'));
        $this->assertSame('/products/'.$this->right, $response->json('result.location'));

        $new = (array) $this->watcher()->table('barcode_mappings')->where('id', $mapping)->sole();
        $this->assertSame([$this->right, 'MISASSIGNMENT_CORRECTION', $old, 'MISASSIGNMENT', 'Terbukti salah pasang'], [$new['product_id'], $new['attach_kind'], $new['corrects_mapping_id'], $new['corrected_reason_class'], $new['reason']]);

        // No retirement of its own: the earlier one stands, as it was.
        $this->assertSame($retirements, $this->tableRows('barcode_mapping_retirements'));
        $this->assertSame(1, $this->watcher()->table('barcode_active_mappings')->where('mapping_id', $mapping)->where('product_id', $this->right)->count());
        $this->assertSame([$this->right], $this->exactProducts('SALAH-2', 'catalog'));

        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['BARCODE_CORRECTION', 'barcode_mapping', $mapping, $this->right, 'Terbukti salah pasang'], [$event['action'], $event['subject_type'], $event['subject_id'], $event['product_id'], $event['reason']]);
        $this->assertSame(['barcode' => 'SALAH-2', 'correctedMappingId' => $old, 'fromProductId' => $this->wrong, 'kind' => 'REATTACH', 'mappingId' => $mapping, 'toProductId' => $this->right], self::dataOf($event['change_data']));
    }

    public function test_a_token_retired_as_a_misassignment_never_returns_to_the_product_it_left(): void
    {
        // G-09: a correction moves the token to a different product; going back to the wrong
        // one would reuse a retired token and correct nothing (BR-CAT-19; review finding R-01).
        $old = TokoContoh::barcode($this->wrong, 'SALAH-3');
        TokoContoh::retireBarcode($old, 'MISASSIGNMENT');
        $this->owner();
        $before = $this->factsAndRows();

        $this->assertNotCorrectable($this->postCommand('SALAH-3', $this->wrong, 'Pensiun keliru', $this->wrong, self::RETIRED));

        $this->assertSame($before, $this->factsAndRows());
        $this->assertSame([], $this->exactProducts('SALAH-3', 'catalog'));

        // Nor, after a move, to the product of the mapping retired after it.
        $moved = self::number($this->postCommand('SALAH-3', $this->right, 'Benar', $this->wrong, self::RETIRED)->assertStatus(201)->json('result.id'));
        TokoContoh::retireBarcode($moved, 'MISASSIGNMENT');
        $this->owner();
        $this->assertNotCorrectable($this->postCommand('SALAH-3', $this->right, 'Lagi', $this->right, self::RETIRED));

        // The product it left first is another product now: the last mapping decides.
        $this->postCommand('SALAH-3', $this->wrong, 'Ternyata', $this->right, self::RETIRED)->assertStatus(201);
        $this->assertSame([$this->wrong], $this->exactProducts('SALAH-3', 'catalog'));
    }

    public function test_a_target_that_became_inactive_still_takes_the_token(): void
    {
        // RC-30 lists no refusal for it, and RC-27 lets an inactive product take a token
        // (UXI-171): only RD-01's context leaves it out (evidence reading 5; review finding R-06).
        TokoContoh::barcode($this->wrong, 'AKTIF-1');
        $this->owner();
        $this->postJson('/commands/product.deactivate', $this->envelope(['productId' => $this->right], ['operation' => 'product.deactivate', 'expected' => ['productVersion' => 1]]))->assertStatus(201);

        $this->postCommand('AKTIF-1', $this->right, 'Alasan', $this->wrong, self::ACTIVE)->assertStatus(201);
        $this->assertSame([$this->right], $this->exactProducts('AKTIF-1', 'catalog'));
        $this->assertSame([], $this->exactProducts('AKTIF-1', 'correction'));
    }

    public function test_a_token_read_as_a_token_keeps_its_case_with_only_the_terminator_and_outer_spaces_removed(): void
    {
        TokoContoh::barcode($this->wrong, 'aB-c.1');
        $this->owner();

        // Another case is another token (BR-CAT-17).
        $this->assertNotCorrectable($this->postCommand('AB-C.1', $this->right, 'Alasan', $this->wrong, self::ACTIVE));

        $this->postCommand("  aB-c.1 \r\n", $this->right, 'Alasan', $this->wrong, self::ACTIVE)->assertStatus(201)->assertJsonPath('result.number', 'aB-c.1');
    }

    public function test_every_token_that_cannot_be_corrected_is_refused_at_its_field(): void
    {
        // AC-020-02: BR-CAT-19, BR-CAT-51; COPY_DECK section 13.2.
        TokoContoh::retireBarcode(TokoContoh::barcode($this->wrong, 'LAIN-1'), 'OTHER');
        TokoContoh::barcode($this->right, 'SUDAH-BENAR');
        $this->owner();
        $generated = $this->generated($this->wrong);
        $retiredCode = $this->generated($this->wrong);
        TokoContoh::retireBarcode(self::number($this->mappingOf($retiredCode)), 'MISASSIGNMENT');
        $this->owner();
        $before = $this->facts();

        foreach ([
            // Retired for another reason, whatever the page sends as expected.
            ['LAIN-1', self::RETIRED],
            ['LAIN-1', self::ACTIVE],
            // Internal codes, active or retired, even as a misassignment.
            [$generated, self::ACTIVE],
            [$retiredCode, self::RETIRED],
            // A code never mapped; another case of a token.
            ['TIDAK-ADA', self::ACTIVE],
            ['lain-1', self::RETIRED],
        ] as [$token, $state]) {
            $this->assertNotCorrectable($this->postCommand($token, $this->right, 'Alasan', $this->wrong, $state));
        }

        // Active on the product named: there is nothing to move.
        $this->assertNotCorrectable($this->postCommand('SUDAH-BENAR', $this->right, 'Alasan', $this->right, self::ACTIVE));

        $this->assertSame($before, $this->facts());
    }

    public function test_a_token_can_be_corrected_again_only_through_its_last_mapping(): void
    {
        $first = TokoContoh::barcode($this->wrong, 'RANTAI-1');
        $this->owner();

        // Moved twice: each correction names the one before it.
        $second = self::number($this->postCommand('RANTAI-1', $this->right, 'Pertama', $this->wrong, self::ACTIVE)->assertStatus(201)->json('result.id'));
        $third = self::number($this->postCommand('RANTAI-1', $this->third, 'Kedua', $this->right, self::ACTIVE)->assertStatus(201)->json('result.id'));
        $this->assertSame($second, $this->watcher()->table('barcode_mappings')->where('id', $third)->value('corrects_mapping_id'));
        $this->assertSame($first, $this->watcher()->table('barcode_mappings')->where('id', $second)->value('corrects_mapping_id'));

        // Retired for another reason afterwards: the earlier misassignments open nothing again.
        TokoContoh::retireBarcode($third, 'OTHER');
        $this->owner();
        $this->assertNotCorrectable($this->postCommand('RANTAI-1', $this->wrong, 'Ketiga', $this->third, self::RETIRED));

        // Its answer says so too.
        $this->getJson(self::LOOKUP.'?token=RANTAI-1')->assertOk()->assertJsonPath('correctable', false)->assertJsonPath('mapping', null)->assertJsonPath('activeProduct', null);
    }

    public function test_a_mapping_that_changed_since_the_lookup_is_stale_and_leaves_everything_as_it_was(): void
    {
        // WF-17: STALE_VERSION when the mapping changed since the lookup.
        TokoContoh::barcode($this->wrong, 'AKTIF-1');
        TokoContoh::retireBarcode(TokoContoh::barcode($this->wrong, 'PENSIUN-1'), 'MISASSIGNMENT');
        $this->owner();
        $before = $this->facts();

        foreach ([
            ['AKTIF-1', $this->third, self::ACTIVE],
            ['AKTIF-1', $this->wrong, self::RETIRED],
            ['PENSIUN-1', $this->third, self::RETIRED],
            ['PENSIUN-1', $this->wrong, self::ACTIVE],
        ] as [$token, $expectedProduct, $state]) {
            $refused = $this->postCommand($token, $this->right, 'Alasan', $expectedProduct, $state)->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION')->assertJsonPath('messageKey', 'ux.state.stale_version');
            $this->assertJsonChannel($refused, 'commands.refused', 'owner');
        }

        $this->assertSame($before, $this->facts());
    }

    public function test_only_the_owner_passes_the_gate_and_the_guard_in_the_transaction_holds_too(): void
    {
        // AC-020-01: BR-CAT-22, CAP-07.
        TokoContoh::barcode($this->wrong, 'AKTIF-1');

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
        }

        $before = $this->facts();

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->postCommand('AKTIF-1', $this->right, 'Alasan', $this->wrong, self::ACTIVE)->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
            $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', 'barcode.correct_misassignment')->where('actor_user_id', $this->people[$person]['id'])->count(), $person);

            // SEC-AZ-04: the Action refuses them even without the route's gate.
            $envelope = new Envelope(CorrectBarcodeMisassignment::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, ['mapping' => ['productId' => $this->wrong, 'state' => self::ACTIVE]], ['token' => 'AKTIF-1', 'targetProductId' => $this->right, 'reason' => 'Alasan']);

            try {
                app(CorrectBarcodeMisassignment::class)->execute(new CommandContext($this->people[$person]['id'], $envelope, app(Clock::class)->now()));
                $this->fail($person.' passed the guard.');
            } catch (ActorNotAllowed) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame($before, $this->facts());

        // Without a sign-in session nothing is answered.
        $this->browse(null);
        $this->postJson(self::COMMAND, $this->envelopeOf('AKTIF-1', $this->right, 'Alasan', $this->wrong, self::ACTIVE))->assertStatus(401);
        $this->assertSame($before, $this->facts());
    }

    public function test_recent_authentication_comes_first_and_the_same_request_then_commits(): void
    {
        // AUTH03; AC-020-01; UXS-11.
        TokoContoh::barcode($this->wrong, 'AKTIF-1');
        $this->as('Hendra');
        $before = $this->facts();
        $envelope = $this->envelopeOf('AKTIF-1', $this->right, 'Alasan', $this->wrong, self::ACTIVE);

        $this->postJson(self::COMMAND, $envelope)->assertStatus(423)->assertJsonPath('code', 'RECENT_AUTH_REQUIRED');
        $this->assertSame($before, $this->facts());

        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
        $this->postJson(self::COMMAND, $envelope)->assertStatus(201);
        $this->assertSame([$this->right], $this->exactProducts('AKTIF-1', 'catalog'));
    }

    public function test_the_fields_of_the_command_and_a_guessed_identifier_are_refused(): void
    {
        TokoContoh::barcode($this->wrong, 'AKTIF-1');
        $this->owner();
        $before = $this->facts();
        $valid = ['token' => 'AKTIF-1', 'targetProductId' => $this->right, 'reason' => 'Alasan'];
        $expected = ['mapping' => ['productId' => $this->wrong, 'state' => self::ACTIVE]];

        // A product that does not exist, as a guess is.
        $this->post30([...$valid, 'targetProductId' => 999999], $expected)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        foreach ([
            [['token' => ''], 'payload.token', 'ux.validation.required'],
            [['token' => null], 'payload.token', 'ux.validation.required'],
            [['token' => '   '], 'payload.token', 'ux.validation.required'],
            [['token' => 7], 'payload.token', 'ux.validation.barcode_token'],
            [['token' => str_repeat('t', 129)], 'payload.token', 'ux.validation.barcode_token'],
            [['token' => "AB\nCD"], 'payload.token', 'ux.validation.barcode_token'],
            [['targetProductId' => null], 'payload.targetProductId', 'ux.validation.required'],
            [['targetProductId' => 0], 'payload.targetProductId', 'ux.validation.choose_one'],
            [['targetProductId' => 'x'], 'payload.targetProductId', 'ux.validation.choose_one'],
            [['reason' => ''], 'payload.reason', 'ux.validation.required'],
            [['reason' => null], 'payload.reason', 'ux.validation.required'],
            [['reason' => '   '], 'payload.reason', 'ux.validation.required'],
            [['reason' => 7], 'payload.reason', 'ux.validation.required'],
            [['reason' => str_repeat('a', 1001)], 'payload.reason', 'ux.validation.text_max'],
        ] as [$change, $path, $key]) {
            $this->assertSame($key, self::firstKey($this->post30([...$valid, ...$change], $expected)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED'), $path), (string) json_encode($change));
        }

        // Each field that must be there, with no key of its own when it holds what no field may.
        foreach ([
            [['token' => 'AKTIF-1', 'targetProductId' => $this->right], $expected],
            [['token' => 'AKTIF-1', 'reason' => 'Alasan'], $expected],
            [['targetProductId' => $this->right, 'reason' => 'Alasan'], $expected],
            [[...$valid, 'targetProductId' => (string) $this->right], $expected],
            [[...$valid, 'reason' => "Satu\nDua"], $expected],
            [[...$valid, 'kind' => 'MOVE'], $expected],
            [$valid, []],
            [$valid, ['mapping' => []]],
            [$valid, ['mapping' => ['productId' => $this->wrong]]],
            [$valid, ['mapping' => ['state' => self::ACTIVE]]],
            [$valid, ['mapping' => ['productId' => (string) $this->wrong, 'state' => self::ACTIVE]]],
            [$valid, ['mapping' => ['productId' => $this->wrong, 'state' => 'RETIRED']]],
            [$valid, ['mapping' => ['productId' => $this->wrong, 'state' => self::ACTIVE, 'mappingId' => 1]]],
            [$valid, [...$expected, 'productVersion' => 1]],
        ] as [$payload, $sent]) {
            $this->post30($payload, $sent)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($before, $this->facts());

        // The longest reason is kept whole.
        $this->post30([...$valid, 'reason' => str_repeat('a', 1000)], $expected)->assertStatus(201);
    }

    public function test_a_failure_after_every_write_step_leaves_no_fact_and_the_receipt_pending(): void
    {
        // TS-21: the steps of a move and of a re-attachment, as the Action lists them.
        foreach ([
            'a move' => [6, 'MOVE-1', self::ACTIVE],
            'a re-attachment' => [4, 'REATTACH-1', self::RETIRED],
        ] as $case => [$steps, $token, $state]) {
            $mapping = TokoContoh::barcode($this->wrong, $token);

            if ($state === self::RETIRED) {
                TokoContoh::retireBarcode($mapping, 'MISASSIGNMENT');
            }

            $this->owner();

            for ($step = 1; $step <= $steps; $step++) {
                $this->app->instance(CommandPoints::class, new FailurePoints($step));
                $before = $this->factsAndRows();

                $this->postCommand($token, $this->right, 'Alasan', $this->wrong, $state)->assertStatus(500);

                $this->assertSame($before, $this->factsAndRows(), sprintf('%s after write step %d', $case, $step));
                $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', 'barcode.correct_misassignment')->orderByDesc('id')->value('outcome'), $case);
            }

            // The step after the last is no step of the command: it commits.
            $this->app->instance(CommandPoints::class, new FailurePoints($steps + 1));
            $committed = $this->postCommand($token, $this->right, 'Alasan', $this->wrong, $state)->assertStatus(201);
            $this->assertSame(1, $this->watcher()->table('command_results')->where('command_id', $committed->json('commandId'))->count(), $case);
            $this->app->instance(CommandPoints::class, new FailurePoints);
        }
    }

    public function test_the_keys_of_the_schema_refuse_a_second_correction_as_duplicate_barcode(): void
    {
        // RC-30: at most one active mapping after commit. Under the locks no command reaches
        // these keys, so the writes meet them directly.
        $old = TokoContoh::barcode($this->wrong, 'KUNCI-1');
        $this->owner();
        $mapping = self::number($this->postCommand('KUNCI-1', $this->right, 'Alasan', $this->wrong, self::ACTIVE)->assertStatus(201)->json('result.id'));
        $row = (array) $this->watcher()->table('barcode_mappings')->where('id', $mapping)->sole();
        $tokenId = self::number($row['token_id']);
        $commandId = self::number($row['command_id']);
        $owner = $this->people['Hendra']['id'];
        $barcodes = app(Barcodes::class);
        $before = $this->facts();

        foreach ([
            'corrected once' => fn () => $barcodes->insertCorrection($tokenId, $old, $this->third, 'Lagi', $owner, 'Hendra', $commandId, 'payload.token'),
            'active once' => fn () => $barcodes->activateCorrection($tokenId, $mapping, $this->right, 'payload.token'),
        ] as $case => $write) {
            try {
                DB::transaction($write);
                $this->fail($case);
            } catch (CommandRefused $refusal) {
                $this->assertSame(ErrorCode::DuplicateBarcode, $refusal->error, $case);
            }
        }

        $this->assertSame($before, $this->facts());
    }

    public function test_a_status_check_recovers_the_original_result(): void
    {
        // EXECUTION_CONTEXT section 10; RC-50; ADR-007 section 3.
        TokoContoh::barcode($this->wrong, 'AKTIF-1');
        $this->owner();
        $envelope = $this->envelopeOf('AKTIF-1', $this->right, 'Alasan', $this->wrong, self::ACTIVE);
        $mapping = self::number($this->postJson(self::COMMAND, $envelope)->assertStatus(201)->json('result.id'));

        $response = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.id', $mapping)->assertJsonPath('result.number', 'AKTIF-1')->assertJsonPath('result.location', '/products/'.$this->right);
        $this->assertJsonChannel($response, 'commands.resolve.barcode', 'owner');
    }

    public function test_an_envelope_is_refused_as_every_command_refuses_one_and_no_answer_is_stored_by_a_cache(): void
    {
        // CMD01; G-25; TR-HTTP-09.
        TokoContoh::barcode($this->wrong, 'AKTIF-1');
        $this->owner();
        $envelope = $this->envelopeOf('AKTIF-1', $this->right, 'Alasan', $this->wrong, self::ACTIVE);
        $before = $this->facts();

        foreach ([['version' => 2], ['tambahan' => 'x'], ['expected' => ['mapping' => ['productId' => 1.5, 'state' => self::ACTIVE]]]] as $changes) {
            $this->postJson(self::COMMAND, [...$envelope, 'idempotencyKey' => (string) Str::uuid(), ...$changes])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')->assertHeader('Cache-Control', 'no-store, private');
        }

        $this->assertSame($before, $this->facts());

        // An earlier epoch is refused before any fact.
        $this->postJson(self::COMMAND, [...$envelope, 'sourceIntentId' => (string) Str::uuid(), 'idempotencyKey' => (string) Str::uuid(), 'recoveryEpoch' => str_repeat('ab', 32)])->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->assertSame(0, $this->watcher()->table('barcode_mapping_retirements')->count());

        // Another payload under the same key is held, never run.
        $this->postJson(self::COMMAND, $envelope)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $held = $this->postJson(self::COMMAND, [...$envelope, 'payload' => ['token' => 'AKTIF-1', 'targetProductId' => $this->third, 'reason' => 'Alasan']])->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        $this->assertSame('no-store, private', $held->headers->get('Cache-Control'));
        $this->assertSame([$this->right], $this->exactProducts('AKTIF-1', 'catalog'));
    }

    public function test_rd_05_shows_the_kind_the_active_product_the_retirements_and_whether_it_is_correctable(): void
    {
        // AC-020-02; INTERACTION_CONTRACTS section 3.5; COPY_DECK section 8.6.
        TokoContoh::barcode($this->wrong, 'AKTIF-1');
        TokoContoh::retireBarcode(TokoContoh::barcode($this->wrong, 'SALAH-1'), 'MISASSIGNMENT');
        TokoContoh::retireBarcode(TokoContoh::barcode($this->wrong, 'LAIN-1'), 'OTHER');
        $this->owner();
        $code = $this->generated($this->wrong);

        // Active on a product.
        $this->getJson(self::LOOKUP.'?token=AKTIF-1')->assertOk()->assertExactJson([
            'token' => 'AKTIF-1', 'kind' => 'MANUFACTURER', 'activeProduct' => ['id' => $this->wrong, 'name' => 'Pulpen Hitam'], 'retirements' => [],
            'mapping' => ['productId' => $this->wrong, 'productName' => 'Pulpen Hitam', 'state' => self::ACTIVE], 'correctable' => true,
        ]);

        // Retired as a misassignment.
        $retired = $this->getJson(self::LOOKUP.'?token=SALAH-1')->assertOk()->assertJsonPath('kind', 'MANUFACTURER')->assertJsonPath('activeProduct', null)->assertJsonPath('correctable', true)
            ->assertJsonPath('mapping', ['productId' => $this->wrong, 'productName' => 'Pulpen Hitam', 'state' => self::RETIRED])
            ->assertJsonPath('retirements.0.productId', $this->wrong)->assertJsonPath('retirements.0.productName', 'Pulpen Hitam')->assertJsonPath('retirements.0.reasonClass', 'MISASSIGNMENT')->assertJsonPath('retirements.0.actorDisplayName', 'Hendra');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', self::text($retired->json('retirements.0.retiredAt')));

        // Retired for another reason: its record shows, and it cannot be corrected.
        $this->getJson(self::LOOKUP.'?token=LAIN-1')->assertOk()->assertJsonPath('kind', 'MANUFACTURER')->assertJsonPath('retirements.0.reasonClass', 'OTHER')->assertJsonPath('mapping', null)->assertJsonPath('correctable', false);

        // An internal code never is.
        $this->getJson(self::LOOKUP.'?token='.$code)->assertOk()->assertJsonPath('kind', 'INTERNAL')->assertJsonPath('activeProduct.id', $this->wrong)->assertJsonPath('mapping', null)->assertJsonPath('correctable', false);

        // A code that was never mapped says so, without a refusal (UXS-33).
        $this->getJson(self::LOOKUP.'?token=TIDAK-ADA')->assertOk()->assertExactJson(['token' => 'TIDAK-ADA', 'kind' => null, 'activeProduct' => null, 'retirements' => [], 'mapping' => null, 'correctable' => false]);

        // A token read as a token: exact, with the terminator and outer spaces removed.
        $this->getJson(self::LOOKUP.'?token=aktif-1')->assertOk()->assertJsonPath('kind', null);
        $this->getJson(self::LOOKUP.'?token='.rawurlencode(" AKTIF-1\r\n"))->assertOk()->assertJsonPath('token', 'AKTIF-1')->assertJsonPath('correctable', true);

        // After a move: every field, in the channel's list, and the newest retirement first.
        $this->postCommand('AKTIF-1', $this->right, 'Pertama', $this->wrong, self::ACTIVE)->assertStatus(201);
        $this->postCommand('AKTIF-1', $this->third, 'Kedua', $this->right, self::ACTIVE)->assertStatus(201);
        $moved = $this->getJson(self::LOOKUP.'?token=AKTIF-1')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('activeProduct.id', $this->third)->assertJsonPath('mapping.productId', $this->third)
            ->assertJsonPath('retirements.0.productId', $this->right)->assertJsonPath('retirements.1.productId', $this->wrong);
        $this->assertCount(2, (array) $moved->json('retirements'));
        $this->assertJsonChannel($moved, 'data.barcodes.lookup', 'owner');
    }

    public function test_rd_05_takes_only_its_token_and_only_the_owner_may_read_it(): void
    {
        TokoContoh::barcode($this->wrong, 'AKTIF-1');
        $this->owner();

        $this->assertSame('ux.validation.required', self::firstKey($this->getJson(self::LOOKUP)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED'), 'token'));
        $this->assertSame('ux.validation.required', self::firstKey($this->getJson(self::LOOKUP.'?token=')->assertStatus(422), 'token'));

        foreach ([str_repeat('t', 129), "AB\nCD", "\tAB", "AB\u{00A0}"] as $token) {
            $this->assertSame('ux.validation.barcode_token', self::firstKey($this->getJson(self::LOOKUP.'?token='.rawurlencode($token))->assertStatus(422), 'token'), $token);
        }

        $this->getJson(self::LOOKUP.'?token=AKTIF-1&context=catalog')->assertStatus(422);
        $this->getJson(self::LOOKUP.'?token[]=AKTIF-1')->assertStatus(422);

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->getJson(self::LOOKUP.'?token=AKTIF-1')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        }

        $this->browse(null);
        $this->getJson(self::LOOKUP.'?token=AKTIF-1')->assertStatus(401);
    }

    public function test_the_correction_context_answers_active_products_only_for_the_owner(): void
    {
        // INTERACTION_CONTRACTS section 3.1; TASK-020 scope item 1.
        TokoContoh::barcode($this->wrong, 'KODE-MATI');
        TokoContoh::product('KODE-MATI', ['categoryId' => $this->category, 'name' => 'Penghapus Hidup']);
        $this->owner();
        $this->postJson('/commands/product.deactivate', $this->envelope(['productId' => $this->wrong], ['operation' => 'product.deactivate', 'expected' => ['productVersion' => 1]]))->assertStatus(201);

        // In the search, the inactive one is left out; in the catalog it stays, marked.
        $this->assertSame([$this->right], $this->searchedIds('Pulpen', 'correction'));
        $this->assertSame([$this->wrong, $this->right], $this->searchedIds('Pulpen', 'catalog'));

        $answer = $this->withHeader('X-Search-Sequence', '7')->getJson(self::SEARCH.'?context=correction&q=Pulpen&perPage=1')->assertOk()->assertHeader('X-Search-Sequence', '7')->assertJsonPath('hasMore', false);
        $this->assertJsonChannel($answer, 'data.products.search', 'owner');

        // A code whose product is inactive answers none, never another product's SKU: the
        // catalog's own answer names the code's product (TASK-016).
        $this->assertSame([], $this->exactProducts('KODE-MATI', 'correction'));
        $this->assertSame([$this->wrong], $this->exactProducts('KODE-MATI', 'catalog'));

        // An exact code of an active product.
        TokoContoh::barcode($this->right, 'KODE-HIDUP');
        $this->owner();
        $this->assertSame([$this->right], $this->exactProducts('KODE-HIDUP', 'correction'));

        // Its filters and paging are the shared ones, without those of the catalog alone.
        $this->assertSame([$this->right], $this->searchedIds('Pulpen', 'correction', '&category='.$this->category.'&page=1&perPage=100'));

        foreach (['&status=active', '&status=inactive', '&import=1', '&mode=exact&category=1'] as $parameter) {
            $this->getJson(self::SEARCH.'?context=correction&q=Pulpen'.$parameter)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // CAP-02 opens the route, and CAP-07 the context.
        foreach (['Agus', 'Dewi'] as $person) {
            $this->as($person);
            $this->getJson(self::SEARCH.'?context=correction&q=Pulpen')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        }

        $this->as('Rina');
        $this->getJson(self::SEARCH.'?context=correction&q=Pulpen')->assertStatus(403);
    }

    public function test_rp_305_opens_for_the_owner_alone_and_rp_302_offers_it_to_the_owner_alone(): void
    {
        // AC-020-01, AC-020-04; SEC-AZ-07.
        $this->as('Hendra');
        $response = $this->get('/products/'.$this->wrong.'/barcode-correction')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertPageChannel($response, 'owner', array_values(array_map(static fn (array $one): string => $one['password'], $this->people)));
        $this->assertSame('catalog/BarcodeCorrection', $this->inertiaPage($response)['component'] ?? null);
        $this->assertSame(['id' => $this->wrong], $this->propsOf($response)['product'] ?? null);
        $this->assertTrue($this->propsOf($this->get('/products/'.$this->wrong)->assertOk())['canCorrectBarcode'] ?? null);

        foreach (['999999', 'abc', '-1', '0'] as $identifier) {
            $this->getJson('/products/'.$identifier.'/barcode-correction')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        $this->getJson('/products/'.$this->wrong.'/barcode-correction?token=AKTIF-1')->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');

        foreach (['Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $page = $this->get('/products/'.$this->wrong.'/barcode-correction')->assertStatus(403)->viewData('page');
            $this->assertIsArray($page);
            $this->assertSame('shell/Error', $page['component'] ?? null, $actor);
            $this->assertFalse($this->propsOf($this->get('/products/'.$this->wrong)->assertOk())['canCorrectBarcode'] ?? null, $actor);
        }

        $this->as('Rina');
        $this->get('/products/'.$this->wrong.'/barcode-correction')->assertStatus(403);
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<array-key, mixed>
     */
    private function propsOf(TestResponse $response): array
    {
        return (array) ($this->inertiaPage($response)['props'] ?? []);
    }

    /** The Owner, signed in with a recent authentication (UXS-11). */
    private function owner(): void
    {
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
    }

    /**
     * An internal code of the product, generated at run time (EXC-63).
     */
    private function generated(int $product): string
    {
        return self::text($this->postJson('/commands/barcode.generate', $this->envelope(['productId' => $product], ['operation' => 'barcode.generate']))->assertStatus(201)->json('result.number'));
    }

    private function mappingOf(string $token): mixed
    {
        return $this->watcher()->table('barcode_active_mappings', 'a')->join('barcode_tokens as t', 't.id', '=', 'a.token_id')->where('t.token', $token)->value('a.mapping_id');
    }

    /**
     * An envelope of RC-30.
     *
     * @return array<string, mixed>
     */
    private function envelopeOf(string $token, int $target, string $reason, int $expectedProduct, string $state): array
    {
        return $this->envelope(['token' => $token, 'targetProductId' => $target, 'reason' => $reason], ['operation' => 'barcode.correct_misassignment', 'expected' => ['mapping' => ['productId' => $expectedProduct, 'state' => $state]]]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function postCommand(string $token, int $target, string $reason, int $expectedProduct, string $state): TestResponse
    {
        return $this->postJson(self::COMMAND, $this->envelopeOf($token, $target, $reason, $expectedProduct, $state));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return TestResponse<Response>
     */
    private function post30(array $payload, array $expected): TestResponse
    {
        return $this->postJson(self::COMMAND, $this->envelope($payload, ['operation' => 'barcode.correct_misassignment', 'expected' => $expected === [] ? (object) [] : $expected]));
    }

    /**
     * A refusal BARCODE_NOT_ELIGIBLE with the reason of COPY_DECK section 13.2, as a safe
     * parameter and at the field, and the receipt that records it.
     *
     * @param  TestResponse<Response>  $response
     */
    private function assertNotCorrectable(TestResponse $response): void
    {
        $response->assertStatus(422)->assertJsonPath('code', 'BARCODE_NOT_ELIGIBLE')->assertJsonPath('messageKey', 'ux.state.barcode_ineligible')->assertJsonPath('params.reason', 'not_correctable')->assertJsonPath('outcome', 'REJECTED');
        $this->assertSame('ux.state.barcode_ineligible.not_correctable', self::firstKey($response, 'payload.token'));
    }

    /**
     * The products of the mode `exact`, in this context.
     *
     * @return list<mixed>
     */
    private function exactProducts(string $code, string $context): array
    {
        return array_values(array_map(static fn (mixed $row): mixed => ((array) $row)['id'] ?? null, (array) $this->getJson(self::SEARCH.'?context='.$context.'&mode=exact&q='.rawurlencode($code))->assertOk()->json('rows')));
    }

    /**
     * The products of a search, in this context.
     *
     * @return list<mixed>
     */
    private function searchedIds(string $text, string $context, string $parameters = ''): array
    {
        $ids = array_values(array_map(static fn (mixed $row): mixed => ((array) $row)['id'] ?? null, (array) $this->getJson(self::SEARCH.'?context='.$context.'&q='.rawurlencode($text).$parameters)->assertOk()->json('rows')));
        sort($ids);

        return $ids;
    }

    /**
     * An event's data with its keys sorted, since jsonb keeps no order of its own.
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
     * Every row of a table, in key order.
     *
     * @return list<array<string, mixed>>
     */
    private function tableRows(string $table): array
    {
        return array_values($this->watcher()->table($table)->orderBy($table === 'barcode_mapping_retirements' ? 'mapping_id' : 'id')->get()->map(static fn (object $row): array => (array) $row)->all());
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
     * The facts, with the rows that a command changes.
     *
     * @return array<string, mixed>
     */
    private function factsAndRows(): array
    {
        return ['facts' => $this->facts(), 'active' => $this->watcher()->table('barcode_active_mappings')->orderBy('token_id')->get()->map(static fn (object $row): array => (array) $row)->all()];
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
