<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The four tables of TASK-016 (AC-016-05; SCHEMA sections 5.6, 7, 9.4, 10 and 11; the
 * migration 2026_11_01_000002_create_barcode_tables; EXECUTION_CONTEXT section 10, a
 * table; suites DB01, KEEP01 and SEARCH01; G-09): the constraint family, written
 * directly by the runtime role; the deferred family, for tables whose keys are all
 * immediate, so that each order of a command's rows is proven as it is written; the
 * privilege family; and the retention family, in which a retirement only adds a fact
 * and removes nothing but the active row.
 */
final class BarcodeTablesTest extends DatabaseTestCase
{
    private int $owner = 0;

    private int $command = 0;

    private int $product = 0;

    private int $other = 0;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $category = TokoContoh::category();
        $this->product = TokoContoh::product('PEN-01', ['categoryId' => $category]);
        $this->other = TokoContoh::product('PEN-02', ['categoryId' => $category]);

        $command = DB::table('products')->where('id', $this->product)->value('created_command_id');
        $this->command = is_int($command) ? $command : 0;
        $this->owner = User::query()->where('role_super_admin', true)->firstOrFail()->id;
    }

    public function test_each_check_and_key_of_the_tokens_refuses_its_violation(): void
    {
        // BR-CAT-17: 1 to 128 printable ASCII characters without an outer space.
        foreach (['', ' A', 'A ', "A\nB", "A\tB", "A\u{007F}B", 'É', "A\u{00A0}B", str_repeat('a', 129)] as $token) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertToken($c, ['token' => $token]), '23514', 'barcode_tokens_token_format');
        }

        // BR-CAT-18: the namespace, and an internal code's exact form.
        foreach (['BK-', 'BK-123', self::internal()] as $token) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertToken($c, ['token' => $token]), '23514', 'barcode_tokens_kind_namespace');
        }

        // One character short and one too many, lowercase, and the four letters that
        // Crockford's alphabet leaves out.
        $stem = substr(self::internal(), 0, -1);

        foreach ([$stem, self::internal().'A', strtolower(self::internal()), $stem.'I', $stem.'L', $stem.'O', $stem.'U', 'AB-123'] as $token) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertToken($c, ['token' => $token, 'kind' => 'INTERNAL']), '23514', 'barcode_tokens_kind_namespace');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertToken($c, ['kind' => 'LAIN']), '23514', 'barcode_tokens_kind_namespace');

        // A symbology's digits, and only for a manufacturer token.
        foreach ([['EAN_13', '123456789012'], ['EAN_8', '1234567'], ['UPC_A', '1234567890123'], ['UPC_E', '123456789'], ['EAN_13', '123456789012A'], ['CODE_128', '12345678']] as [$symbology, $token]) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertToken($c, ['token' => $token, 'symbology' => $symbology]), '23514', 'barcode_tokens_symbology_shape');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertToken($c, ['token' => self::internal(), 'kind' => 'INTERNAL', 'symbology' => 'EAN_8']), '23514', 'barcode_tokens_symbology_shape');

        foreach (['token', 'kind', 'created_at'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertToken($c, [$column => null]), '23502');
        }

        // What each allows, with its form kept exactly.
        foreach (['007', '7', 'bk-1', 'aB c.D', 'ab c.d', '!"#$%&', '~', str_repeat('a', 128)] as $token) {
            $this->insertToken($this->runtime(), ['token' => $token]);
            $this->assertSame(1, $this->observer()->table('barcode_tokens')->where('token', $token)->count(), $token);
        }

        $this->insertToken($this->runtime(), ['token' => self::internal(), 'kind' => 'INTERNAL']);

        // Unique, compared exactly: another case is another token.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertToken($c, ['token' => '007']), '23505', 'barcode_tokens_token_unique');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertToken($c, ['token' => self::internal(), 'kind' => 'INTERNAL']), '23505', 'barcode_tokens_token_unique');
    }

    public function test_each_check_and_key_of_the_mappings_refuses_its_violation(): void
    {
        $token = $this->insertToken($this->runtime());

        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, ['token_kind' => 'INTERNAL']), '23503', 'barcode_mappings_token_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, 999999), '23503', 'barcode_mappings_token_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, ['product_id' => 999999]), '23503', 'barcode_mappings_product_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, ['actor_user_id' => 999999]), '23503', 'barcode_mappings_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, ['command_id' => 999999]), '23503', 'barcode_mappings_command_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, ['actor_display_name' => '']), '23514', 'barcode_mappings_actor_display_name_length');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, ['actor_display_name' => str_repeat('a', 101)]), '23514', 'barcode_mappings_actor_display_name_length');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, ['reason' => '']), '23514', 'barcode_mappings_reason_length');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, ['reason' => str_repeat('a', 1001)]), '23514', 'barcode_mappings_reason_length');

        // An attachment's kind and its correction's columns go together.
        foreach ([
            ['attach_kind' => 'LAIN'],
            ['corrects_mapping_id' => 1],
            ['corrected_reason_class' => 'MISASSIGNMENT'],
            ['attach_kind' => 'MISASSIGNMENT_CORRECTION'],
        ] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, $values), '23514', 'barcode_mappings_attach_kind_shape');
        }

        foreach (['token_id', 'token_kind', 'product_id', 'attach_kind', 'attached_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, [$column => null]), '23502');
        }

        // BR-CAT-19: one ordinary attachment per token, ever, on the same product too.
        $this->insertMapping($this->runtime(), $token);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, ['product_id' => $this->other]), '23505', 'barcode_mappings_one_original');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token), '23505', 'barcode_mappings_one_original');
    }

    public function test_a_correction_needs_an_earlier_mapping_of_its_token_retired_as_a_misassignment_and_corrects_it_once(): void
    {
        // SCHEMA section 5.6, the keys that TASK-020's correction relies on: nothing
        // here writes them through an Action, because this Task has no correction.
        $token = $this->insertToken($this->runtime());
        $mapping = $this->insertMapping($this->runtime(), $token);
        $correction = ['attach_kind' => 'MISASSIGNMENT_CORRECTION', 'corrects_mapping_id' => $mapping, 'corrected_reason_class' => 'MISASSIGNMENT', 'reason' => 'Salah pasang', 'product_id' => $this->other];

        // Not retired yet.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, $correction), '23503', 'barcode_mappings_corrected_retirement_fk');

        // A reason is mandatory, and the class is exactly a misassignment.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, [...$correction, 'reason' => null]), '23514', 'barcode_mappings_attach_kind_shape');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, [...$correction, 'corrected_reason_class' => 'OTHER']), '23514', 'barcode_mappings_attach_kind_shape');

        // Another token's mapping.
        $otherToken = $this->insertToken($this->runtime());
        $this->insertRetirement($this->runtime(), $mapping, ['reason_class' => 'MISASSIGNMENT']);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $otherToken, $correction), '23503', 'barcode_mappings_corrects_token_fk');

        // An internal code can never be corrected (BR-CAT-19).
        $internal = $this->insertToken($this->runtime(), ['token' => self::internal(), 'kind' => 'INTERNAL']);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $internal, [...$correction, 'token_kind' => 'INTERNAL']), '23514', 'barcode_mappings_attach_kind_shape');

        // A retirement for another reason can never be corrected (D-052).
        $retiredOtherwise = $this->insertToken($this->runtime());
        $otherMapping = $this->insertMapping($this->runtime(), $retiredOtherwise);
        $this->insertRetirement($this->runtime(), $otherMapping, ['reason_class' => 'OTHER']);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $retiredOtherwise, [...$correction, 'corrects_mapping_id' => $otherMapping]), '23503', 'barcode_mappings_corrected_retirement_fk');

        // Once.
        $this->insertMapping($this->runtime(), $token, $correction);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMapping($c, $token, $correction), '23505', 'barcode_mappings_corrects_unique');
    }

    public function test_each_check_and_key_of_the_retirements_refuses_its_violation(): void
    {
        $mapping = $this->insertMapping($this->runtime(), $this->insertToken($this->runtime()));

        $this->assertWriteRefused(fn (Connection $c) => $this->insertRetirement($c, 999999), '23503', 'barcode_mapping_retirements_mapping_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRetirement($c, $mapping, ['reason_class' => 'LAIN']), '23514', 'barcode_mapping_retirements_reason_class');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRetirement($c, $mapping, ['actor_user_id' => 999999]), '23503', 'barcode_mapping_retirements_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRetirement($c, $mapping, ['command_id' => 999999]), '23503', 'barcode_mapping_retirements_command_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRetirement($c, $mapping, ['actor_display_name' => '']), '23514', 'barcode_mapping_retirements_actor_display_name_length');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRetirement($c, $mapping, ['actor_display_name' => str_repeat('a', 101)]), '23514', 'barcode_mapping_retirements_actor_display_name_length');

        foreach (['reason_class', 'retired_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertRetirement($c, $mapping, [$column => null]), '23502');
        }

        // One retirement per mapping, whatever its reason.
        $this->insertRetirement($this->runtime(), $mapping);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRetirement($c, $mapping, ['reason_class' => 'MISASSIGNMENT']), '23505', 'barcode_mapping_retirements_pkey');
    }

    public function test_a_token_is_active_on_at_most_one_product_and_only_by_a_mapping_of_its_own(): void
    {
        $token = $this->insertToken($this->runtime());
        $mapping = $this->insertMapping($this->runtime(), $token);
        $otherToken = $this->insertToken($this->runtime());
        $otherMapping = $this->insertMapping($this->runtime(), $otherToken, ['product_id' => $this->other]);

        // The composite key: the mapping's own token and its own product.
        $this->assertWriteRefused(fn (Connection $c) => $c->table('barcode_active_mappings')->insert(['token_id' => $token, 'mapping_id' => $mapping, 'product_id' => $this->other]), '23503', 'barcode_active_mappings_mapping_fk');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('barcode_active_mappings')->insert(['token_id' => $otherToken, 'mapping_id' => $mapping, 'product_id' => $this->product]), '23503', 'barcode_active_mappings_mapping_fk');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('barcode_active_mappings')->insert(['token_id' => $token, 'mapping_id' => 999999, 'product_id' => $this->product]), '23503', 'barcode_active_mappings_mapping_fk');

        // These break the composite key too, so that either key may answer first.
        $this->assertWriteRefused(fn (Connection $c) => $c->table('barcode_active_mappings')->insert(['token_id' => 999999, 'mapping_id' => $mapping, 'product_id' => $this->product]), '23503');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('barcode_active_mappings')->insert(['token_id' => $token, 'mapping_id' => $mapping, 'product_id' => 999999]), '23503');
        $this->assertSame(2, $this->observer()->table('pg_constraint')->whereIn('conname', ['barcode_active_mappings_token_fk', 'barcode_active_mappings_product_fk'])->where('contype', 'f')->count());

        foreach (['token_id', 'mapping_id', 'product_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $c->table('barcode_active_mappings')->insert([...['token_id' => $token, 'mapping_id' => $mapping, 'product_id' => $this->product], $column => null]), '23502');
        }

        $this->runtime()->table('barcode_active_mappings')->insert(['token_id' => $token, 'mapping_id' => $mapping, 'product_id' => $this->product]);

        // The primary key of BR-CAT-16, whichever mapping names the token; the mapping
        // is unique too.
        $this->assertWriteRefused(fn (Connection $c) => $c->table('barcode_active_mappings')->insert(['token_id' => $token, 'mapping_id' => $mapping, 'product_id' => $this->product]), '23505', 'barcode_active_mappings_pkey');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('barcode_active_mappings')->insert(['token_id' => $otherToken, 'mapping_id' => $mapping, 'product_id' => $this->product]), '23505', 'barcode_active_mappings_mapping_unique');

        $this->runtime()->table('barcode_active_mappings')->insert(['token_id' => $otherToken, 'mapping_id' => $otherMapping, 'product_id' => $this->other]);
    }

    public function test_every_key_of_the_four_tables_is_immediate_so_a_command_writes_them_in_order(): void
    {
        // The deferred family: SCHEMA section 5.6 defers none of these keys, unlike a
        // product's price pointer; a command that left out a row, or wrote a row
        // before what it names, is refused at that statement, before any commit.
        $deferrable = $this->observer()->table('pg_constraint', 'c')
            ->join('pg_class as r', 'r.oid', '=', 'c.conrelid')
            ->whereIn('r.relname', ['barcode_tokens', 'barcode_mappings', 'barcode_mapping_retirements', 'barcode_active_mappings'])
            ->where('c.condeferrable', true)
            ->count();
        $this->assertSame(0, $deferrable);

        // Within a transaction, each row after what it names: a retirement before its
        // mapping and an active row before its mapping are refused on the spot.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRetirement($c, 999999), '23503', 'barcode_mapping_retirements_mapping_fk');
        $token = $this->insertToken($this->runtime());
        $this->assertWriteRefused(fn (Connection $c) => $c->table('barcode_active_mappings')->insert(['token_id' => $token, 'mapping_id' => 999999, 'product_id' => $this->product]), '23503', 'barcode_active_mappings_mapping_fk');

        // In SCH-32's order, one transaction commits them all.
        $this->runtime()->transaction(function (Connection $c): void {
            $token = $this->insertToken($c);
            $mapping = $this->insertMapping($c, $token);
            $c->table('barcode_active_mappings')->insert(['token_id' => $token, 'mapping_id' => $mapping, 'product_id' => $this->product]);
        });
        $this->assertSame(1, $this->observer()->table('barcode_active_mappings')->count());
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        $token = $this->insertToken($this->runtime());
        $mapping = $this->insertMapping($this->runtime(), $token);
        $this->insertRetirement($this->runtime(), $mapping);
        $this->runtime()->table('barcode_active_mappings')->insert(['token_id' => $token, 'mapping_id' => $mapping, 'product_id' => $this->product]);
        $runtime = $this->runtime();

        // Class F: added, never changed or removed. A row's columns name each table.
        foreach (['barcode_tokens' => 'kind', 'barcode_mappings' => 'reason', 'barcode_mapping_retirements' => 'reason_class'] as $table => $column) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->update([$column => 'OTHER']));
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->delete());
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->truncate());
        }

        // Class P: added and removed with its facts, never changed.
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('barcode_active_mappings')->update(['product_id' => $this->other]));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('barcode_active_mappings')->truncate());

        foreach (['barcode_tokens', 'barcode_mappings', 'barcode_mapping_retirements', 'barcode_active_mappings'] as $table) {
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->delete());
            $this->assertSame($runtime->table($table)->count(), $this->backup()->table($table)->count(), $table);
        }

        // The runtime role takes no value from a sequence (SCH-44).
        $this->assertDenied($runtime, fn (Connection $c) => $c->scalar("select pg_catalog.nextval('barcode_tokens_id_seq')"));
        $this->assertDenied($runtime, fn (Connection $c) => $c->scalar("select pg_catalog.nextval('barcode_mappings_id_seq')"));
    }

    public function test_a_retirement_only_adds_its_fact_and_removes_nothing_but_the_active_row(): void
    {
        // KEEP01; BR-CAT-52; G-09. Through the Actions, as the Owner.
        $mapping = TokoContoh::barcode($this->product, '8991234567891', 'EAN_13');
        $kept = TokoContoh::barcode($this->product, 'ALIAS-1');

        $this->assertRetained(['barcode_tokens', 'barcode_mappings'], static function () use ($mapping): void {
            TokoContoh::retireBarcode($mapping, 'MISASSIGNMENT');
        });
        $this->assertRetained(['barcode_mapping_retirements'], static function () use ($kept): void {
            TokoContoh::retireBarcode($kept, 'OTHER');
        }, 'mapping_id');

        // G-09: a mapping with a retirement has no active row.
        $observer = $this->observer();
        $this->assertSame(0, $observer->table('barcode_active_mappings')->count());
        $this->assertSame(2, $observer->table('barcode_mappings')->count());
        $this->assertSame(['MISASSIGNMENT', 'OTHER'], $observer->table('barcode_mapping_retirements')->orderBy('mapping_id')->pluck('reason_class')->all());
    }

    public function test_the_foreign_keys_have_their_indexes_and_the_one_original_index_is_partial(): void
    {
        $indexes = $this->observer()->table('pg_indexes')->where('schemaname', 'public')->whereIn('tablename', ['barcode_tokens', 'barcode_mappings', 'barcode_mapping_retirements', 'barcode_active_mappings'])->pluck('indexdef', 'indexname')->all();

        foreach ([
            'barcode_mappings_one_original' => "CREATE UNIQUE INDEX barcode_mappings_one_original ON public.barcode_mappings USING btree (token_id) WHERE (attach_kind = 'ORIGINAL'::text)",
            'barcode_tokens_token_unique' => 'CREATE UNIQUE INDEX barcode_tokens_token_unique ON public.barcode_tokens USING btree (token)',
            'barcode_mappings_token_id_index' => 'CREATE INDEX barcode_mappings_token_id_index ON public.barcode_mappings USING btree (token_id)',
            'barcode_mappings_product_id_index' => 'CREATE INDEX barcode_mappings_product_id_index ON public.barcode_mappings USING btree (product_id)',
            'barcode_mappings_actor_user_id_index' => 'CREATE INDEX barcode_mappings_actor_user_id_index ON public.barcode_mappings USING btree (actor_user_id)',
            'barcode_mappings_command_id_index' => 'CREATE INDEX barcode_mappings_command_id_index ON public.barcode_mappings USING btree (command_id)',
            'barcode_mapping_retirements_actor_user_id_index' => 'CREATE INDEX barcode_mapping_retirements_actor_user_id_index ON public.barcode_mapping_retirements USING btree (actor_user_id)',
            'barcode_mapping_retirements_command_id_index' => 'CREATE INDEX barcode_mapping_retirements_command_id_index ON public.barcode_mapping_retirements USING btree (command_id)',
            'barcode_active_mappings_product_id_index' => 'CREATE INDEX barcode_active_mappings_product_id_index ON public.barcode_active_mappings USING btree (product_id)',
        ] as $name => $definition) {
            $this->assertSame($definition, $indexes[$name] ?? null, $name);
        }
    }

    /** An internal code's exact form, built here so that no code stands in the source as a literal (EXC-63). */
    private static function internal(): string
    {
        return 'BK-P-'.str_repeat('A', 16);
    }

    /**
     * A token written directly.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertToken(Connection $c, array $values = []): int
    {
        return $c->table('barcode_tokens')->insertGetId(['token' => 'T-'.Str::random(10), 'kind' => 'MANUFACTURER', 'symbology' => null, 'created_at' => now(), ...$values]);
    }

    /**
     * A mapping written directly.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertMapping(Connection $c, int $token, array $values = []): int
    {
        return $c->table('barcode_mappings')->insertGetId([
            'token_id' => $token, 'token_kind' => 'MANUFACTURER', 'product_id' => $this->product, 'attach_kind' => 'ORIGINAL', 'attached_at' => now(),
            'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra', 'command_id' => $this->command, ...$values,
        ]);
    }

    /**
     * A retirement written directly.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertRetirement(Connection $c, int $mapping, array $values = []): void
    {
        $c->table('barcode_mapping_retirements')->insert([
            'mapping_id' => $mapping, 'reason_class' => 'OTHER', 'retired_at' => now(), 'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra',
            'command_id' => $this->command, ...$values,
        ]);
    }
}
