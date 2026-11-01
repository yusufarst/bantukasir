<?php

namespace Tests\Feature\Catalog;

use App\Database\CommandPoints;
use App\Domain\Catalog\Barcodes\Barcodes;
use App\Domain\Catalog\Barcodes\InternalCodes;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Catalog\ScriptedInternalCodes;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-27 to RC-29 and the barcode of RC-17 through their routes (AC-016-01 to
 * AC-016-03; INTERACTION_CONTRACTS RC-17, RC-27 to RC-29; TECHNICAL_RULES section 7.2;
 * BR-CAT-16 to BR-CAT-22, BR-CAT-52; G-09; EXECUTION_CONTEXT section 10, a command;
 * suites CMD01, AUTH01, SEARCH01 and BARCODE01): each command's commit, audit event,
 * durable result and repeat; a token kept as typed, and its refusals, which write
 * nothing; the reserved namespace and the check digit; a retired token never added
 * again; the generated code and its clash; the roles, also when one changes before
 * the command; the failure after every write step.
 */
final class BarcodeCommandsTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    /** The tables that a refused command leaves as they were. */
    private const array FACTS = ['barcode_tokens', 'barcode_mappings', 'barcode_mapping_retirements', 'barcode_active_mappings', 'products', 'selling_price_revisions', 'command_identities', 'command_results', 'audit_events'];

    private int $category = 0;

    private int $product = 0;

    private int $other = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category();
        $this->product = TokoContoh::product('PEN-01', ['categoryId' => $this->category]);
        $this->other = TokoContoh::product('PEN-02', ['categoryId' => $this->category]);
    }

    public function test_a_manufacturer_token_is_added_audited_for_each_role_of_cap_06(): void
    {
        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $token = 'ALIAS-'.$person;
            $envelope = $this->commandEnvelope('barcode.add', ['productId' => $this->product, 'token' => $token], []);

            $response = $this->postJson('/commands/barcode.add', $envelope)->assertStatus(201);
            $this->assertJsonChannel($response, 'commands.committed.barcode', $actor);
            $mapping = self::number($response->json('result.id'));
            $commandId = self::number($response->json('commandId'));
            $this->assertSame(['barcode_mapping', $token, '/products/'.$this->product], [$response->json('result.type'), $response->json('result.number'), $response->json('result.location')]);

            $observer = $this->watcher();
            $row = (array) $observer->table('barcode_tokens')->where('token', $token)->sole();
            $this->assertSame(['MANUFACTURER', null], [$row['kind'], $row['symbology']]);

            $attached = (array) $observer->table('barcode_mappings')->where('id', $mapping)->sole();
            $this->assertSame([$row['id'], 'MANUFACTURER', $this->product, 'ORIGINAL', null, null, null, $this->people[$person]['id'], $person, $commandId], [$attached['token_id'], $attached['token_kind'], $attached['product_id'], $attached['attach_kind'], $attached['corrects_mapping_id'], $attached['corrected_reason_class'], $attached['reason'], $attached['actor_user_id'], $attached['actor_display_name'], $attached['command_id']]);

            $active = (array) $observer->table('barcode_active_mappings')->where('token_id', $row['id'])->sole();
            $this->assertSame([$mapping, $this->product], [$active['mapping_id'], $active['product_id']]);

            $event = (array) $observer->table('audit_events')->where('command_id', $commandId)->sole();
            $this->assertSame(['BUSINESS', 'BARCODE_ADD', 'USER', $this->people[$person]['id'], $person, 'barcode_mapping', $mapping, $this->product], [$event['category'], $event['action'], $event['actor_kind'], $event['actor_user_id'], $event['actor_display_name'], $event['subject_type'], $event['subject_id'], $event['product_id']]);
            $this->assertSame(['barcode' => $token, 'mappingId' => $mapping, 'symbology' => null], self::dataOf($event['change_data']));
            $this->assertSame(['COMMITTED'], $this->outcomesOf($envelope));

            // A repeat returns the original, and writes nothing.
            $before = $this->facts();
            $this->postJson('/commands/barcode.add', $envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('commandId', $commandId)->assertJsonPath('result.id', $mapping)->assertJsonPath('result.number', $token);
            $this->assertSame($before, $this->facts());
        }
    }

    public function test_a_token_round_trips_exactly_with_only_the_terminator_and_outer_spaces_removed(): void
    {
        // AC-016-01, through the global middleware.
        $this->as('Agus');

        foreach ([
            '0012345' => '0012345',
            'aB-c.D/e_f+g' => 'aB-c.D/e_f+g',
            'lower-a' => 'lower-a',
            'LOWER-A' => 'LOWER-A',
            '  spasi luar  ' => 'spasi luar',
            "enter\r\n" => 'enter',
            "enter-lf\n" => 'enter-lf',
            "enter-cr\r" => 'enter-cr',
            "  semua \r\n " => 'semua',
            str_repeat('t', 128) => str_repeat('t', 128),
            '~!@#$%^&*()' => '~!@#$%^&*()',
        ] as $typed => $stored) {
            $response = $this->postCommand('barcode.add', ['productId' => $this->product, 'token' => (string) $typed], [])->assertStatus(201);
            $this->assertSame($stored, $response->json('result.number'));
            $this->assertSame(1, $this->watcher()->table('barcode_tokens')->where('token', $stored)->count(), $stored);
        }
    }

    public function test_a_token_that_holds_what_no_token_may_is_refused_at_its_field(): void
    {
        $this->as('Agus');
        $before = $this->facts();

        foreach ([
            [['token' => ''], 'ux.validation.required'],
            [['token' => null], 'ux.validation.required'],
            [['token' => '   '], 'ux.validation.required'],
            [['token' => "\r\n"], 'ux.validation.required'],
            [['token' => 7], 'ux.validation.barcode_token'],
            [['token' => str_repeat('t', 129)], 'ux.validation.barcode_token'],
            [['token' => "AB\nCD"], 'ux.validation.barcode_token'],
            [['token' => "\nABCD"], 'ux.validation.barcode_token'],
            [['token' => "ABCD\n\n"], 'ux.validation.barcode_token'],
            [['token' => "\tABCD"], 'ux.validation.barcode_token'],
            [['token' => "AB\u{0007}CD"], 'ux.validation.barcode_token'],
            [['token' => "AB\u{007F}CD"], 'ux.validation.barcode_token'],
            [['token' => "\u{00A0}ABCD"], 'ux.validation.barcode_token'],
            [['token' => "ABCD\u{3000}"], 'ux.validation.barcode_token'],
            [['token' => 'ÉCLAIR'], 'ux.validation.barcode_token'],
        ] as [$payload, $key]) {
            $response = $this->postCommand('barcode.add', ['productId' => $this->product, ...$payload], [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $this->assertSame($key, self::firstKey($response, 'payload.token'), (string) json_encode($payload));
        }

        // The token's other refusals.
        $this->assertSame('ux.validation.required', self::firstKey($this->postCommand('barcode.add', ['productId' => $this->product], [])->assertStatus(422), 'payload.token'));
        $this->assertSame('ux.validation.choose_one', self::firstKey($this->postCommand('barcode.add', ['productId' => $this->product, 'token' => 'A1', 'symbology' => 'CODE_128'], [])->assertStatus(422), 'payload.symbology'));
        $this->assertSame('ux.validation.choose_one', self::firstKey($this->postCommand('barcode.add', ['productId' => $this->product, 'token' => 'A1', 'symbology' => 13], [])->assertStatus(422), 'payload.symbology'));

        $this->assertSame($before, $this->facts());
    }

    public function test_a_declared_symbology_validates_its_check_digit(): void
    {
        $this->as('Agus');

        foreach (['8991234567891' => 'EAN_13', '96385074' => 'EAN_8', '036000291452' => 'UPC_A', '01234565' => 'UPC_E'] as $token => $symbology) {
            $this->postCommand('barcode.add', ['productId' => $this->product, 'token' => (string) $token, 'symbology' => $symbology], [])->assertStatus(201);
            $this->assertSame($symbology, $this->watcher()->table('barcode_tokens')->where('token', (string) $token)->value('symbology'));
        }

        // Undeclared, nothing is judged: a code's digits are the shop's.
        $this->postCommand('barcode.add', ['productId' => $this->product, 'token' => '8991234567890'], [])->assertStatus(201);

        $before = $this->facts();

        foreach ([
            ['8991234567892', 'EAN_13'], ['96385075', 'EAN_8'], ['036000291453', 'UPC_A'], ['01234566', 'UPC_E'],
            // The wrong digits for the declared symbology have no valid check digit.
            ['12345678', 'EAN_13'], ['ABCDEFGH', 'EAN_8'], ['21234561', 'UPC_E'],
        ] as [$token, $symbology]) {
            $this->assertNotEligible($this->postCommand('barcode.add', ['productId' => $this->product, 'token' => $token, 'symbology' => $symbology], []), 'check_digit', 'payload.token');
        }

        $this->assertSame($before, $this->facts());
    }

    public function test_the_bk_namespace_is_reserved_and_a_token_is_refused_once_it_was_mapped(): void
    {
        // AC-016-02: BR-CAT-18, BR-CAT-19.
        $this->as('Agus');
        $generated = self::text($this->postCommand('barcode.generate', ['productId' => $this->product], [])->assertStatus(201)->json('result.number'));
        $active = TokoContoh::barcode($this->product, 'AKTIF-1');
        $misassigned = TokoContoh::barcode($this->product, 'SALAH-1');
        TokoContoh::retireBarcode($misassigned, 'MISASSIGNMENT');
        $retired = TokoContoh::barcode($this->product, 'LAMA-1');
        TokoContoh::retireBarcode($retired, 'OTHER');
        $before = $this->facts();

        foreach (['BK-', 'BK-123', 'BK-P-'.str_repeat('A', 16), $generated, " BK-1\r\n"] as $token) {
            $this->assertNotEligible($this->postCommand('barcode.add', ['productId' => $this->other, 'token' => $token], []), 'reserved', 'payload.token');
        }

        // Active on a product, this one or another.
        foreach ([$this->product, $this->other] as $product) {
            $response = $this->postCommand('barcode.add', ['productId' => $product, 'token' => 'AKTIF-1'], [])->assertStatus(422)->assertJsonPath('code', 'DUPLICATE_BARCODE')->assertJsonPath('messageKey', 'ux.state.duplicate_code.barcode');
            $this->assertSame('ux.state.duplicate_code.barcode', self::firstKey($response, 'payload.token'));
        }

        // Retired for either reason, on this product or another: only the Owner's
        // correction of TASK-020 attaches a token again.
        foreach (['SALAH-1', 'LAMA-1'] as $token) {
            foreach ([$this->product, $this->other] as $product) {
                $this->assertNotEligible($this->postCommand('barcode.add', ['productId' => $product, 'token' => $token], []), 'retired', 'payload.token');
            }
        }

        $this->assertSame($before, $this->facts());

        // Each reason has its text (COPY_DECK section 13.2), and the refused requests
        // left the active mapping where it was.
        foreach (['reserved', 'retired', 'check_digit'] as $reason) {
            $this->assertTrue(Lang::has('ux.state.barcode_ineligible.'.$reason, 'id'), $reason);
        }

        $this->assertSame([$this->product], $this->watcher()->table('barcode_active_mappings')->where('mapping_id', $active)->pluck('product_id')->all());

        // The namespace is what is written: another case is an ordinary token.
        $this->postCommand('barcode.add', ['productId' => $this->product, 'token' => 'bk-123'], [])->assertStatus(201);
    }

    public function test_a_generated_code_is_internal_new_for_each_request_and_audited(): void
    {
        $this->as('Agus');
        $envelope = $this->commandEnvelope('barcode.generate', ['productId' => $this->product], []);

        $response = $this->postJson('/commands/barcode.generate', $envelope)->assertStatus(201);
        $this->assertJsonChannel($response, 'commands.committed.barcode', 'ops');
        $code = self::text($response->json('result.number'));
        $mapping = self::number($response->json('result.id'));
        $this->assertMatchesRegularExpression('/\ABK-P-[0-9A-HJKMNP-TV-Z]{16}\z/', $code);
        $this->assertSame('/products/'.$this->product, $response->json('result.location'));

        $row = (array) $this->watcher()->table('barcode_tokens')->where('token', $code)->sole();
        $this->assertSame(['INTERNAL', null], [$row['kind'], $row['symbology']]);
        $attached = (array) $this->watcher()->table('barcode_mappings')->where('id', $mapping)->sole();
        $this->assertSame([$row['id'], 'INTERNAL', $this->product, 'ORIGINAL'], [$attached['token_id'], $attached['token_kind'], $attached['product_id'], $attached['attach_kind']]);
        $this->assertSame(1, $this->watcher()->table('barcode_active_mappings')->where('mapping_id', $mapping)->count());

        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $response->json('commandId'))->sole();
        $this->assertSame(['BARCODE_GENERATE', 'barcode_mapping', $mapping, $this->product], [$event['action'], $event['subject_type'], $event['subject_id'], $event['product_id']]);
        $this->assertSame(['barcode' => $code, 'mappingId' => $mapping], self::dataOf($event['change_data']));

        // A repeat answers the same code and draws none.
        $this->postJson('/commands/barcode.generate', $envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.number', $code);

        // A product may hold several, and a new request draws a new one.
        $again = self::text($this->postCommand('barcode.generate', ['productId' => $this->product], [])->assertStatus(201)->json('result.number'));
        $this->assertNotSame($code, $again);
        $this->assertSame(2, $this->watcher()->table('barcode_active_mappings')->where('product_id', $this->product)->count());
    }

    public function test_a_clash_of_a_generated_code_draws_again_and_a_retired_code_is_never_used_again(): void
    {
        $this->as('Agus');
        $taken = 'BK-P-'.str_repeat('A', 16);
        $retiredCode = 'BK-P-'.str_repeat('B', 16);
        $free = 'BK-P-'.str_repeat('C', 16);

        $this->app->instance(InternalCodes::class, new ScriptedInternalCodes([$taken]));
        $this->postCommand('barcode.generate', ['productId' => $this->product], [])->assertStatus(201)->assertJsonPath('result.number', $taken);

        $this->app->instance(InternalCodes::class, new ScriptedInternalCodes([$retiredCode]));
        $retired = self::number($this->postCommand('barcode.generate', ['productId' => $this->product], [])->assertStatus(201)->json('result.id'));
        TokoContoh::retireBarcode($retired, 'OTHER');

        // BR-CAT-18: both are drawn and passed over, and the same transaction goes on.
        $script = new ScriptedInternalCodes([$taken, $retiredCode, $free]);
        $this->app->instance(InternalCodes::class, $script);
        $this->postCommand('barcode.generate', ['productId' => $this->other], [])->assertStatus(201)->assertJsonPath('result.number', $free);
        $this->assertSame(3, $script->drawn);
        $this->assertSame(1, $this->watcher()->table('barcode_tokens')->where('token', $retiredCode)->count());
        $this->assertSame(0, $this->watcher()->table('barcode_active_mappings')->where('mapping_id', $retired)->count());

        // A source that only clashes gives up without a fact, as an uncertain answer.
        $before = $this->facts();
        $script = new ScriptedInternalCodes([$taken]);
        $this->app->instance(InternalCodes::class, $script);
        $this->postCommand('barcode.generate', ['productId' => $this->other], [])->assertStatus(503)->assertJsonPath('code', 'SERVICE_BUSY');
        $this->assertSame(Barcodes::CODE_ATTEMPTS, $script->drawn);
        $this->assertSame($before, $this->facts());
    }

    public function test_a_retirement_records_its_reason_class_ends_the_active_row_and_keeps_the_history(): void
    {
        foreach (['MISASSIGNMENT' => ['Hendra', 'owner'], 'OTHER' => ['Dewi', 'combined']] as $reasonClass => [$person, $actor]) {
            $this->as($person);
            $token = 'PENSIUN-'.$reasonClass;
            $mapping = TokoContoh::barcode($this->product, $token);
            $envelope = $this->commandEnvelope('barcode.retire', ['mappingId' => $mapping, 'reasonClass' => $reasonClass], []);
            $before = $this->snapshot('barcode_mappings');

            $response = $this->postJson('/commands/barcode.retire', $envelope)->assertStatus(201);
            $this->assertJsonChannel($response, 'commands.committed.barcode', $actor);
            $commandId = self::number($response->json('commandId'));
            $this->assertSame(['barcode_mapping', $mapping, $token, '/products/'.$this->product], [$response->json('result.type'), $response->json('result.id'), $response->json('result.number'), $response->json('result.location')]);

            $retirement = (array) $this->watcher()->table('barcode_mapping_retirements')->where('mapping_id', $mapping)->sole();
            $this->assertSame([$reasonClass, $this->people[$person]['id'], $person, $commandId], [$retirement['reason_class'], $retirement['actor_user_id'], $retirement['actor_display_name'], $retirement['command_id']]);

            // G-09: no active row; the token and the mapping stay as they were.
            $this->assertSame(0, $this->watcher()->table('barcode_active_mappings')->where('mapping_id', $mapping)->count());
            $this->assertSame(1, $this->watcher()->table('barcode_tokens')->where('token', $token)->count());
            $this->assertSame($before, $this->snapshot('barcode_mappings'));

            $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
            $this->assertSame(['BARCODE_RETIRE', 'barcode_mapping', $mapping, $this->product], [$event['action'], $event['subject_type'], $event['subject_id'], $event['product_id']]);
            $this->assertSame(['barcode' => $token, 'mappingId' => $mapping, 'reasonClass' => $reasonClass], self::dataOf($event['change_data']));

            // A repeat returns the original.
            $this->postJson('/commands/barcode.retire', $envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $mapping);

            // Another request finds it no longer active.
            $refused = $this->postCommand('barcode.retire', ['mappingId' => $mapping, 'reasonClass' => 'OTHER'], [])->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION')->assertJsonPath('messageKey', 'ux.state.stale_version');
            $this->assertJsonChannel($refused, 'commands.refused', $actor);
            $this->assertSame(1, $this->watcher()->table('barcode_mapping_retirements')->where('mapping_id', $mapping)->count());
        }

        // An internal code is retired the same way.
        $this->as('Agus');
        $generated = self::number($this->postCommand('barcode.generate', ['productId' => $this->product], [])->assertStatus(201)->json('result.id'));
        $this->postCommand('barcode.retire', ['mappingId' => $generated, 'reasonClass' => 'OTHER'], [])->assertStatus(201);
    }

    public function test_the_fields_of_each_command_and_a_guessed_identifier_are_refused(): void
    {
        $this->as('Agus');
        $mapping = TokoContoh::barcode($this->product, 'AKTIF-1');
        $before = $this->facts();

        // A guessed identifier is NOT_FOUND, never another's record.
        $this->postCommand('barcode.add', ['productId' => 999999, 'token' => 'BARU-1'], [])->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->postCommand('barcode.generate', ['productId' => 999999], [])->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->postCommand('barcode.retire', ['mappingId' => 999999, 'reasonClass' => 'OTHER'], [])->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        foreach ([
            ['barcode.add', ['token' => 'BARU-1'], 'payload.productId', 'ux.validation.required'],
            ['barcode.generate', [], 'payload.productId', 'ux.validation.required'],
            ['barcode.retire', ['reasonClass' => 'OTHER'], 'payload.mappingId', 'ux.validation.required'],
            ['barcode.retire', ['mappingId' => $mapping], 'payload.reasonClass', 'ux.validation.required'],
            ['barcode.retire', ['mappingId' => $mapping, 'reasonClass' => 'LAIN'], 'payload.reasonClass', 'ux.validation.choose_one'],
            ['barcode.retire', ['mappingId' => $mapping, 'reasonClass' => 'misassignment'], 'payload.reasonClass', 'ux.validation.choose_one'],
        ] as [$operation, $payload, $path, $key]) {
            $this->assertSame($key, self::firstKey($this->postCommand($operation, $payload, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED'), $path), $operation.' '.$path);
        }

        // Unknown fields, identifiers that only look like numbers, and an expected
        // version that no command of these names: refused, never trimmed.
        foreach ([
            ['barcode.add', ['productId' => $this->product, 'token' => 'BARU-1', 'kind' => 'INTERNAL'], []],
            ['barcode.add', ['productId' => (string) $this->product, 'token' => 'BARU-1'], []],
            ['barcode.add', ['productId' => $this->product, 'token' => 'BARU-1'], ['productVersion' => 1]],
            ['barcode.generate', ['productId' => $this->product, 'token' => 'BARU-1'], []],
            ['barcode.generate', ['productId' => (string) $this->product], []],
            ['barcode.retire', ['mappingId' => (string) $mapping, 'reasonClass' => 'OTHER'], []],
            ['barcode.retire', ['mappingId' => $mapping, 'reasonClass' => 'OTHER', 'reason' => 'Bebas'], []],
        ] as [$operation, $payload, $expected]) {
            $this->postCommand($operation, $payload, $expected)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($before, $this->facts());
    }

    public function test_an_inactive_product_takes_a_barcode_and_keeps_its_own(): void
    {
        // UXI-171 and UXI-172, "Always"; a deactivation deletes nothing (BR-CAT-08).
        $this->as('Agus');
        $mapping = TokoContoh::barcode($this->product, 'AKTIF-1');
        $this->postCommand('product.deactivate', ['productId' => $this->product], ['productVersion' => 1])->assertStatus(201);

        $this->assertSame(1, $this->watcher()->table('barcode_active_mappings')->where('mapping_id', $mapping)->count());
        $this->postCommand('barcode.add', ['productId' => $this->product, 'token' => 'AKTIF-2'], [])->assertStatus(201);
        $this->postCommand('barcode.generate', ['productId' => $this->product], [])->assertStatus(201);
        $this->postCommand('barcode.retire', ['mappingId' => $mapping, 'reasonClass' => 'OTHER'], [])->assertStatus(201);

        // A barcode changes no field of its product, whose version stays.
        $this->assertSame(2, $this->watcher()->table('products')->where('id', $this->product)->value('version'));
    }

    public function test_a_cashier_is_refused_every_command_before_it_runs(): void
    {
        // BR-CAT-22: never an alias, also not from an unknown scanned token.
        $mapping = TokoContoh::barcode($this->product, 'AKTIF-1');
        $this->as('Rina');
        $before = $this->facts();

        foreach ([
            ['barcode.add', ['productId' => $this->product, 'token' => 'BARU-1']],
            ['barcode.generate', ['productId' => $this->product]],
            ['barcode.retire', ['mappingId' => $mapping, 'reasonClass' => 'OTHER']],
            ['product.create', ['sku' => 'X', 'barcode' => ['token' => 'BARU-1']]],
        ] as [$operation, $payload]) {
            $this->postCommand($operation, $payload, [])->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
            $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', $operation)->where('actor_user_id', $this->people['Rina']['id'])->count(), $operation);
        }

        $this->assertSame($before, $this->facts());
    }

    public function test_a_status_check_recovers_the_original_result_under_the_viewers_authorization(): void
    {
        // EXECUTION_CONTEXT section 10; RC-50; ADR-007 section 3.
        $staff = TokoContoh::staff('Tono', ['cashier', 'operationsAdmin']);
        $this->people['Tono'] = ['id' => $staff['id'], 'email' => $staff['email'], 'password' => (string) $staff['password']];
        $this->as('Tono');
        $envelope = $this->commandEnvelope('barcode.add', ['productId' => $this->product, 'token' => 'AKTIF-1'], []);
        $mapping = self::number($this->postJson('/commands/barcode.add', $envelope)->assertStatus(201)->json('result.id'));

        $response = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.id', $mapping)->assertJsonPath('result.number', 'AKTIF-1')->assertJsonPath('result.location', '/products/'.$this->product);
        $this->assertJsonChannel($response, 'commands.resolve.barcode', 'combined');

        // The account loses its Operations role: its repeat is refused by the gate.
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
        $version = self::number($this->watcher()->table('users')->where('id', $staff['id'])->value('version'));
        $this->postJson('/commands/user.update', $this->commandEnvelope('user.update', ['userId' => $staff['id'], 'displayName' => 'Tono', 'email' => $staff['email'], 'roles' => ['cashier']], ['userVersion' => $version]))->assertStatus(201);

        unset($this->browsers['Tono']);
        $this->as('Tono');
        $this->postJson('/commands/barcode.add', $envelope)->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');

        // Its status check still concludes, without a result it may no longer see.
        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result', null);
    }

    public function test_a_product_is_created_with_its_barcode_audited(): void
    {
        foreach (['Hendra', 'Agus', 'Dewi'] as $number => $person) {
            $this->as($person);
            $token = sprintf("  00-%d-Ab\r\n", $number);
            $stored = sprintf('00-%d-Ab', $number);
            $envelope = $this->productEnvelope(['sku' => 'BARU-'.$person, 'barcode' => ['token' => $token]]);

            $response = $this->post17($envelope)->assertStatus(201);
            $product = self::number($response->json('result.id'));
            $commandId = self::number($response->json('commandId'));
            $this->assertSame(['product', '/products/'.$product], [$response->json('result.type'), $response->json('result.location')]);

            $row = (array) $this->watcher()->table('barcode_tokens')->where('token', $stored)->sole();
            $this->assertSame(['MANUFACTURER', null], [$row['kind'], $row['symbology']]);
            $attached = (array) $this->watcher()->table('barcode_mappings')->where('product_id', $product)->sole();
            $this->assertSame([$row['id'], 'ORIGINAL', $this->people[$person]['id'], $person, $commandId], [$attached['token_id'], $attached['attach_kind'], $attached['actor_user_id'], $attached['actor_display_name'], $attached['command_id']]);
            $this->assertSame($attached['id'], $this->watcher()->table('barcode_active_mappings')->where('token_id', $row['id'])->where('product_id', $product)->value('mapping_id'));

            // One audit event, the creation's, naming the barcode.
            $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
            $this->assertSame('PRODUCT_CREATE', $event['action']);
            $data = self::dataOf($event['change_data']);
            $this->assertSame([$stored, null, $attached['id']], [$data['barcode'] ?? 'absent', $data['symbology'] ?? null, $data['mappingId'] ?? 'absent']);

            // It reads back through the search that scanning uses (AC-016-04).
            $this->getJson('/data/products/search?context=catalog&mode=exact&q='.rawurlencode($stored))->assertOk()->assertJsonPath('rows.0.id', $product);
        }

        $this->as('Agus');

        // A declared symbology, and a barcode left out or null: no token at all.
        $this->post17($this->productEnvelope(['barcode' => ['token' => '96385074', 'symbology' => 'EAN_8']]))->assertStatus(201);
        $this->assertSame('EAN_8', $this->watcher()->table('barcode_tokens')->where('token', '96385074')->value('symbology'));
        $tokens = $this->watcher()->table('barcode_tokens')->count();
        $this->post17($this->productEnvelope())->assertStatus(201);
        $this->post17($this->productEnvelope(['barcode' => null]))->assertStatus(201);
        $this->assertSame($tokens, $this->watcher()->table('barcode_tokens')->count());
    }

    public function test_a_product_whose_barcode_is_refused_is_not_created(): void
    {
        // AC-016-02: the checks of RC-27 at creation, at the field of its barcode.
        $this->as('Agus');
        TokoContoh::barcode($this->product, 'AKTIF-1');
        TokoContoh::retireBarcode(TokoContoh::barcode($this->product, 'LAMA-1'), 'OTHER');
        $generated = self::text($this->postCommand('barcode.generate', ['productId' => $this->product], [])->assertStatus(201)->json('result.number'));
        $before = $this->facts();
        $path = 'payload.barcode.token';

        $this->assertNotEligible($this->post17($this->productEnvelope(['barcode' => ['token' => 'BK-1']])), 'reserved', $path);
        $this->assertNotEligible($this->post17($this->productEnvelope(['barcode' => ['token' => $generated]])), 'reserved', $path);
        $this->assertNotEligible($this->post17($this->productEnvelope(['barcode' => ['token' => 'LAMA-1']])), 'retired', $path);
        $this->assertNotEligible($this->post17($this->productEnvelope(['barcode' => ['token' => '96385075', 'symbology' => 'EAN_8']])), 'check_digit', $path);

        $duplicate = $this->post17($this->productEnvelope(['barcode' => ['token' => 'AKTIF-1']]))->assertStatus(422)->assertJsonPath('code', 'DUPLICATE_BARCODE');
        $this->assertSame('ux.state.duplicate_code.barcode', self::firstKey($duplicate, $path));

        foreach ([
            [['token' => ''], 'ux.validation.required'],
            [['token' => null], 'ux.validation.required'],
            [['symbology' => 'EAN_8'], 'ux.validation.required'],
            [['token' => "AB\nCD"], 'ux.validation.barcode_token'],
            [['token' => "\u{00A0}ABCD"], 'ux.validation.barcode_token'],
            [['token' => str_repeat('t', 129)], 'ux.validation.barcode_token'],
            [['token' => 7], 'ux.validation.barcode_token'],
        ] as [$barcode, $key]) {
            $this->assertSame($key, self::firstKey($this->post17($this->productEnvelope(['barcode' => $barcode]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED'), $path), (string) json_encode($barcode));
        }

        $this->assertSame('ux.validation.choose_one', self::firstKey($this->post17($this->productEnvelope(['barcode' => ['token' => 'A1', 'symbology' => 'CODE_128']]))->assertStatus(422), 'payload.barcode.symbology'));

        // An empty barcode, a text, a list and an unknown field name no token.
        foreach ([[], 'BARU-1', ['BARU-1'], ['token' => 'BARU-1', 'kind' => 'INTERNAL']] as $barcode) {
            $this->post17($this->productEnvelope(['barcode' => $barcode]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // A taken SKU refuses everything of its barcode too.
        $this->post17($this->productEnvelope(['sku' => 'PEN-01', 'barcode' => ['token' => 'BARU-2']]))->assertStatus(422)->assertJsonPath('code', 'DUPLICATE_SKU');

        $this->assertSame($before, $this->facts());
    }

    public function test_a_failure_after_every_write_step_leaves_no_fact_and_the_receipt_pending(): void
    {
        // TS-21: each command's write steps, as its Action lists them.
        $this->as('Agus');
        $mapping = TokoContoh::barcode($this->product, 'AKTIF-1');
        $number = 0;
        $token = static function () use (&$number): string {
            $number++;

            return 'BARU-'.$number;
        };

        $cases = [
            'barcode.add' => [5, fn (): array => ['productId' => $this->product, 'token' => $token()]],
            'barcode.generate' => [5, fn (): array => ['productId' => $this->product]],
            'barcode.retire' => [4, static fn (): array => ['mappingId' => $mapping, 'reasonClass' => 'OTHER']],
            'product.create with a barcode' => [8, fn (): array => $this->productPayload(['barcode' => ['token' => $token()]])],
        ];

        foreach ($cases as $case => [$steps, $payload]) {
            $operation = explode(' ', $case)[0];

            for ($step = 1; $step <= $steps; $step++) {
                $this->app->instance(CommandPoints::class, new FailurePoints($step));
                $before = $this->factsAndRows();

                $this->postCommand($operation, $payload(), [])->assertStatus(500);

                $this->assertSame($before, $this->factsAndRows(), sprintf('%s after write step %d', $case, $step));
                $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', $operation)->orderByDesc('id')->value('outcome'), $case);
            }

            // The step after the last is no step of the command: it commits.
            $this->app->instance(CommandPoints::class, new FailurePoints($steps + 1));
            $committed = $this->postCommand($operation, $payload(), [])->assertStatus(201);
            $this->assertSame(1, $this->watcher()->table('command_results')->where('command_id', $committed->json('commandId'))->count(), $case);
            $this->app->instance(CommandPoints::class, new FailurePoints);
        }
    }

    public function test_an_envelope_is_refused_as_every_command_refuses_one(): void
    {
        // The envelope's refusals, and the receipt that records them (G-25).
        $this->as('Agus');
        $envelope = $this->commandEnvelope('barcode.add', ['productId' => $this->product, 'token' => 'AKTIF-1'], []);
        $before = $this->facts();

        foreach ([
            ['version' => 2],
            ['expected' => ['productVersion' => 1.5]],
            ['payload' => ['productId' => $this->product, 'token' => 'AKTIF-1', 'warna' => 'biru']],
            ['tambahan' => 'x'],
        ] as $changes) {
            $this->postJson('/commands/barcode.add', [...$envelope, 'idempotencyKey' => (string) Str::uuid(), ...$changes])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($before, $this->facts());

        // Another payload under the same key is held, never run.
        $this->postJson('/commands/barcode.add', $envelope)->assertStatus(201);
        $this->postJson('/commands/barcode.add', [...$envelope, 'payload' => ['productId' => $this->product, 'token' => 'LAIN-1']])->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        $this->assertSame(0, $this->watcher()->table('barcode_tokens')->where('token', 'LAIN-1')->count());
        $this->assertSame(['REJECTED', 'REJECTED', 'REJECTED', 'REJECTED', 'COMMITTED', 'HELD'], $this->outcomesOf($envelope));

        // An earlier epoch is refused before any fact.
        $stale = $this->commandEnvelope('barcode.generate', ['productId' => $this->product], []);
        $stale['recoveryEpoch'] = str_repeat('ab', 32);
        $tokens = $this->watcher()->table('barcode_tokens')->count();
        $this->postJson('/commands/barcode.generate', $stale)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->assertSame($tokens, $this->watcher()->table('barcode_tokens')->count());
    }

    /**
     * A refusal BARCODE_NOT_ELIGIBLE with its reason, as a safe parameter and at the
     * field, and the receipt that records it (COPY_DECK section 13.2).
     *
     * @param  TestResponse<Response>  $response
     */
    private function assertNotEligible(TestResponse $response, string $reason, string $path): void
    {
        $response->assertStatus(422)
            ->assertJsonPath('code', 'BARCODE_NOT_ELIGIBLE')
            ->assertJsonPath('messageKey', 'ux.state.barcode_ineligible')
            ->assertJsonPath('params.reason', $reason)
            ->assertJsonPath('outcome', 'REJECTED');
        $this->assertSame('ux.state.barcode_ineligible.'.$reason, self::firstKey($response, $path));
    }

    /**
     * An envelope of RC-17.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function productEnvelope(array $payload = []): array
    {
        return $this->commandEnvelope('product.create', $this->productPayload($payload), []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function productPayload(array $payload = []): array
    {
        return [
            'sku' => 'BRG-'.Str::random(8),
            'name' => 'Pulpen Hitam',
            'categoryId' => $this->category,
            'brand' => null,
            'baseUnit' => 'pcs',
            'quantityPrecision' => 0,
            'sellingPrice' => '7500',
            'minimumStock' => '0',
            ...$payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function commandEnvelope(string $operation, array $payload, array $expected): array
    {
        return [
            'operation' => $operation,
            'version' => 1,
            'sourceIntentId' => (string) Str::uuid(),
            'idempotencyKey' => (string) Str::uuid(),
            'recoveryEpoch' => $this->epoch,
            'expected' => $expected === [] ? (object) [] : $expected,
            'payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function post17(array $envelope): TestResponse
    {
        return $this->postJson('/commands/product.create', $envelope);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return TestResponse<Response>
     */
    private function postCommand(string $operation, array $payload, array $expected): TestResponse
    {
        return $this->postJson('/commands/'.$operation, $this->commandEnvelope($operation, $payload, $expected));
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
