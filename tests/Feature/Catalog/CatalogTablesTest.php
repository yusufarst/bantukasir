<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\UpdateProduct;
use App\Actions\Commands\CommandContext;
use App\Domain\Commands\Envelope;
use App\Models\User;
use App\Support\Time\Clock;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The three tables of TASK-015 (AC-015-06; SCHEMA sections 5.6, 7, 9.4, 10 and 11,
 * SCH-14; the migration 2026_11_01_000001_create_catalog_tables; EXECUTION_CONTEXT
 * section 10, a table; suites DB01, KEEP01 and SEARCH02): the constraint family,
 * written directly by the runtime role, with the case vectors of the SKU; the
 * deferred family — a product has a price revision of its own at every commit;
 * the privilege family; and the retention family, in which a change only adds
 * revisions and leaves every earlier one as it was.
 */
final class CatalogTablesTest extends DatabaseTestCase
{
    private int $owner = 0;

    private int $command = 0;

    private int $category = 0;

    private int $product = 0;

    private int $revision = 0;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->category = TokoContoh::category();
        $this->product = TokoContoh::product('PEN-01', ['categoryId' => $this->category]);

        $row = (array) DB::table('products')->where('id', $this->product)->first(['current_price_revision_id', 'created_command_id']);
        $this->revision = is_int($row['current_price_revision_id']) ? $row['current_price_revision_id'] : 0;
        $this->command = is_int($row['created_command_id']) ? $row['created_command_id'] : 0;
        $owner = User::query()->where('role_super_admin', true)->firstOrFail();
        $this->owner = $owner->id;
    }

    public function test_each_check_of_categories_refuses_its_violation(): void
    {
        foreach (['categories_name_length' => ['name' => ''], 'categories_version_positive' => ['version' => 0]] as $constraint => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertCategory($c, $values), '23514', $constraint);
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertCategory($c, ['name' => str_repeat('k', 101)]), '23514', 'categories_name_length');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertCategory($c, ['name' => null]), '23502');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertCategory($c, ['active' => null]), '23502');

        // Names are not unique (SCHEMA section 5.6).
        $this->insertCategory($this->runtime(), ['name' => 'Sama']);
        $this->insertCategory($this->runtime(), ['name' => 'Sama']);
        $this->assertSame(2, $this->observer()->table('categories')->where('name', 'Sama')->count());
    }

    public function test_each_check_and_key_of_products_refuses_its_violation(): void
    {
        $checks = [
            'products_sku_length' => ['sku' => ''],
            'products_name_length' => ['name' => ''],
            'products_brand_length' => ['brand' => ''],
            'products_base_unit_length' => ['base_unit' => ''],
            'products_quantity_precision_range' => ['quantity_precision' => 4],
            'products_minimum_stock_range' => ['minimum_stock' => '-1'],
            'products_version_positive' => ['version' => 0],
            'products_minimum_stock_precision' => ['minimum_stock' => '1.5', 'quantity_precision' => 0],
        ];

        foreach ($checks as $constraint => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertProduct($c, $values), '23514', $constraint);
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertProduct($c, ['sku' => str_repeat('s', 65)]), '23514', 'products_sku_length');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertProduct($c, ['name' => str_repeat('n', 201)]), '23514', 'products_name_length');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertProduct($c, ['brand' => str_repeat('b', 101)]), '23514', 'products_brand_length');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertProduct($c, ['base_unit' => str_repeat('u', 33)]), '23514', 'products_base_unit_length');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertProduct($c, ['quantity_precision' => -1]), '23514', 'products_quantity_precision_range');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertProduct($c, ['minimum_stock' => '10000000000']), '22003');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertProduct($c, ['minimum_stock' => 'NaN']), '23514', 'products_minimum_stock_range');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertProduct($c, ['category_id' => 999999]), '23503', 'products_category_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertProduct($c, ['photo_file_id' => 999999]), '23503', 'products_photo_file_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertProduct($c, ['created_command_id' => 999999]), '23503', 'products_created_command_fk');

        // SCH-13: each column that a check or key involves, null.
        foreach (['sku', 'name', 'category_id', 'base_unit', 'quantity_precision', 'current_price_revision_id', 'minimum_stock', 'active', 'version', 'created_command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertProduct($c, [$column => null]), '23502', null);
        }

        // The composite target of the movements' key is unique on the key alone,
        // with the row's own unit and precision (BR-CAT-07).
        $this->assertSame(1, $this->observer()->table('pg_constraint')->where('conname', 'products_id_unit_precision_unique')->where('contype', 'u')->count());
    }

    public function test_the_sku_refuses_control_characters_and_outer_whitespace_of_the_database(): void
    {
        // SCH-14: [[:cntrl:]] and [[:space:]] of the database, a tab and a line
        // break being both, and the outer white space of the record of
        // TextSemanticsTest.
        foreach (["AB\u{0001}C", "AB\tC", "AB\nC", "AB\u{007F}C", "AB\u{0085}C", ' ABC', 'ABC ', "\u{2009}ABC", "ABC\u{3000}", "ABC\u{2028}"] as $sku) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertProduct($c, ['sku' => $sku]), '23514', 'products_sku_format');
        }

        // A no-break space is no [[:space:]]; between the letters, a space stays.
        foreach (["\u{00A0}NBSP", 'AB C', "AB\u{00A0}C", "AB\u{2009}D"] as $index => $sku) {
            $this->insertAccepted($sku, 'kept-'.$index);
        }

        $this->assertSame("\u{00A0}NBSP", $this->observer()->table('products')->whereRaw('lower(sku) = lower(?)', ["\u{00A0}NBSP"])->value('sku'));
    }

    public function test_the_sku_is_unique_without_regard_to_letter_case_by_the_vectors_of_sch_14(): void
    {
        // Each pair is the same SKU for lower() of the pinned locale, the only
        // authority (SCH-14; TextSemanticsTest).
        foreach ([['PEN-02', 'pen-02'], ['ÉCLAIR', 'éclair'], ['KÖLN', 'köln'], ['İstanbul', 'istanbul'], ['ẞ', 'ß'], ['ΣΑΠΟΥΝΙ', 'σαπουνι'], ['ǅ', 'ǆ']] as [$first, $second]) {
            $this->insertAccepted($first, 'first-'.$first);
            $this->assertWriteRefused(fn (Connection $c) => $this->insertProduct($c, ['sku' => $second]), '23505', 'products_sku_ci_uniq');
        }

        // These differ for lower(), so both exist: a leading zero is kept, and
        // lower() of the locale folds no letter to two.
        foreach ([['007', '7'], ['STRASSE', 'straße'], ['ﬁ', 'fi'], ['PEN 03', 'PEN-03']] as [$first, $second]) {
            $this->insertAccepted($first, 'first-'.$first);
            $this->insertAccepted($second, 'second-'.$second);
        }

        // The letter case stays as typed.
        $this->assertSame('ÉCLAIR', $this->observer()->table('products')->whereRaw('lower(sku) = lower(?)', ['éclair'])->value('sku'));
    }

    public function test_each_check_and_key_of_the_revisions_refuses_its_violation(): void
    {
        foreach (['selling_price_revisions_amount_range' => ['amount' => -1], 'selling_price_revisions_actor_display_name_length' => ['actor_display_name' => '']] as $constraint => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertRevision($c, $values), '23514', $constraint);
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertRevision($c, ['amount' => '1000000000000']), '23514', 'selling_price_revisions_amount_range');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRevision($c, ['actor_display_name' => str_repeat('a', 101)]), '23514', 'selling_price_revisions_actor_display_name_length');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRevision($c, ['product_id' => 999999]), '23503', 'selling_price_revisions_product_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRevision($c, ['actor_user_id' => 999999]), '23503', 'selling_price_revisions_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRevision($c, ['command_id' => 999999]), '23503', 'selling_price_revisions_command_fk');

        foreach (['product_id', 'amount', 'created_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertRevision($c, [$column => null]), '23502');
        }
    }

    public function test_a_product_commits_only_with_a_revision_of_its_own_in_its_pointer(): void
    {
        // A product whose pointer names nothing cannot commit (the deferred key).
        $products = $this->observer()->table('products')->count();
        $this->assertFailsAtCommit(
            fn (Connection $c) => $this->insertProduct($c, ['sku' => 'TANPA-HARGA']),
            'products_current_price_revision_fk',
            [],
        );
        $this->assertSame($products, $this->observer()->table('products')->count());

        // Nor can its pointer move to another product's revision, or to none.
        foreach ([$this->otherRevision(), 999999] as $revision) {
            $this->assertFailsAtCommit(
                fn (Connection $c) => $c->table('products')->where('id', $this->product)->update(['current_price_revision_id' => $revision]),
                'products_current_price_revision_fk',
                [],
            );
        }

        $this->assertSame($this->revision, $this->observer()->table('products')->where('id', $this->product)->value('current_price_revision_id'));

        // With its first revision and the pointer moved in one transaction, it commits.
        $this->runtime()->transaction(function (Connection $c): void {
            $product = $this->insertProduct($c, ['sku' => 'DENGAN-HARGA']);
            $revision = $this->insertRevision($c, ['product_id' => $product]);
            $c->table('products')->where('id', $product)->update(['current_price_revision_id' => $revision]);
        });
        $this->assertSame(1, $this->observer()->table('products')->where('sku', 'DENGAN-HARGA')->count());
    }

    public function test_an_audit_event_names_only_a_product_that_exists(): void
    {
        // EXC-44: the key that TASK-006 left for this Task.
        $this->assertWriteRefused(fn (Connection $c) => $c->table('audit_events')->insert([
            'occurred_at' => now(), 'category' => 'BUSINESS', 'action' => 'PRODUCT_EDIT', 'actor_kind' => 'USER', 'actor_user_id' => $this->owner,
            'actor_display_name' => 'Hendra', 'product_id' => 999999, 'change_data' => '{}',
        ]), '23503', 'audit_events_product_fk');
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        $runtime = $this->runtime();

        // Class F: added, never changed or removed.
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('selling_price_revisions')->update(['amount' => 1]));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('selling_price_revisions')->delete());

        // Class M: only the listed columns; never removed.
        foreach (['id' => 1, 'created_at' => now()] as $column => $value) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table('categories')->update([$column => $value]));
        }

        foreach (['id' => 1, 'created_command_id' => $this->command, 'created_at' => now()] as $column => $value) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table('products')->update([$column => $value]));
        }

        foreach (['categories', 'products', 'selling_price_revisions'] as $table) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->delete());
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->truncate());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->delete());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->update(['id' => 1]));
            $this->assertSame($runtime->table($table)->count(), $this->backup()->table($table)->count(), $table);
        }

        // The runtime role takes no value from a sequence (SCH-44).
        $this->assertDenied($runtime, fn (Connection $c) => $c->scalar("select pg_catalog.nextval('selling_price_revisions_id_seq')"));
    }

    public function test_a_price_change_only_adds_a_revision_and_leaves_every_earlier_one(): void
    {
        $this->assertRetained(['selling_price_revisions', 'categories'], function (): void {
            foreach (['8000', '8500'] as $version => $price) {
                $this->change(['sellingPrice' => $price], $version + 1);
            }
        });

        $this->assertSame([7500, 8000, 8500], $this->observer()->table('selling_price_revisions')->where('product_id', $this->product)->orderBy('id')->pluck('amount')->all());
        $this->assertSame($this->observer()->table('selling_price_revisions')->where('product_id', $this->product)->max('id'), $this->observer()->table('products')->where('id', $this->product)->value('current_price_revision_id'));
    }

    public function test_the_indexes_of_schema_section_11_exist(): void
    {
        $indexes = $this->observer()->table('pg_indexes')->where('schemaname', 'public')->where('tablename', 'products')->pluck('indexdef', 'indexname')->all();

        foreach ([
            'products_sku_ci_uniq' => 'CREATE UNIQUE INDEX products_sku_ci_uniq ON public.products USING btree (lower(sku))',
            'products_sku_prefix_index' => 'CREATE INDEX products_sku_prefix_index ON public.products USING btree (lower(sku) text_pattern_ops)',
            'products_name_trgm_index' => 'CREATE INDEX products_name_trgm_index ON public.products USING gin (lower(name) gin_trgm_ops)',
            'products_name_id_index' => 'CREATE INDEX products_name_id_index ON public.products USING btree (name, id)',
            'products_category_id_index' => 'CREATE INDEX products_category_id_index ON public.products USING btree (category_id)',
            'products_brand_ci_index' => 'CREATE INDEX products_brand_ci_index ON public.products USING btree (lower(brand))',
            'products_active_index' => 'CREATE INDEX products_active_index ON public.products USING btree (active)',
        ] as $name => $definition) {
            $this->assertSame($definition, $indexes[$name] ?? null, $name);
        }
    }

    /**
     * A product accepted directly, with a revision of its own, so that it commits.
     */
    private function insertAccepted(string $sku, string $name): void
    {
        $this->runtime()->transaction(function (Connection $c) use ($sku, $name): void {
            $product = $this->insertProduct($c, ['sku' => $sku, 'name' => $name]);
            $revision = $this->insertRevision($c, ['product_id' => $product]);
            $c->table('products')->where('id', $product)->update(['current_price_revision_id' => $revision]);
        });
    }

    /**
     * A revision of another product.
     */
    private function otherRevision(): int
    {
        $other = TokoContoh::product('LAIN-01', ['categoryId' => $this->category]);
        $revision = $this->observer()->table('products')->where('id', $other)->value('current_price_revision_id');

        return is_int($revision) ? $revision : 0;
    }

    /**
     * RC-18 through its Action, as the Owner, keeping the other fields.
     *
     * @param  array<string, mixed>  $fields
     */
    private function change(array $fields, int $version): void
    {
        $envelope = new Envelope(UpdateProduct::OPERATION, 1, (string) Str::uuid(), (string) Str::uuid(), TokoContoh::recoveryEpoch(), ['productVersion' => $version], [
            'productId' => $this->product, 'name' => 'Pulpen Hitam', 'categoryId' => $this->category, 'brand' => null, 'baseUnit' => 'pcs',
            'quantityPrecision' => 0, 'sellingPrice' => '7500', 'minimumStock' => '0', ...$fields,
        ]);

        app(UpdateProduct::class)->execute(new CommandContext($this->owner, $envelope, app(Clock::class)->now()));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertCategory(Connection $c, array $values = []): int
    {
        return $c->table('categories')->insertGetId(['name' => 'Kategori', 'active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now(), ...$values]);
    }

    /**
     * A product written directly; its pointer names nothing until a test moves it.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertProduct(Connection $c, array $values = []): int
    {
        return $c->table('products')->insertGetId([
            'sku' => 'SKU-'.Str::random(8), 'name' => 'Barang', 'category_id' => $this->category, 'brand' => null, 'base_unit' => 'pcs', 'quantity_precision' => 0,
            'current_price_revision_id' => 0, 'minimum_stock' => '0', 'active' => true, 'version' => 1, 'created_command_id' => $this->command,
            'created_at' => now(), 'updated_at' => now(), ...$values,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertRevision(Connection $c, array $values = []): int
    {
        return $c->table('selling_price_revisions')->insertGetId([
            'product_id' => $this->product, 'amount' => 7500, 'created_at' => now(), 'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra',
            'command_id' => $this->command, ...$values,
        ]);
    }
}
