<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The table of TASK-019 (AC-019-03; SCHEMA sections 5.6 and 10; the migration
 * 2026_11_02_000001_create_csv_product_imports_table; EXECUTION_CONTEXT section 10, a
 * table; suites DB01 and KEEP01): the constraint family, written directly by the runtime
 * role; the privilege family; and the retention family, a fact of class F that is only
 * ever added. It has no deferred key.
 */
final class CsvImportTableTest extends DatabaseTestCase
{
    private int $owner = 0;

    private int $command = 0;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $product = TokoContoh::product('PEN-01', ['categoryId' => TokoContoh::category()]);
        $command = DB::table('products')->where('id', $product)->value('created_command_id');
        $this->command = is_int($command) ? $command : 0;
        $this->owner = User::query()->where('role_super_admin', true)->firstOrFail()->id;
    }

    public function test_each_check_and_key_refuses_its_violation(): void
    {
        foreach ([
            'csv_product_imports_file_sha256_length' => ['file_sha256' => '\\x00'],
            'csv_product_imports_actor_display_name_length' => ['actor_display_name' => ''],
        ] as $constraint => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertImport($c, $values), '23514', $constraint);
        }

        foreach ([0, 5001] as $rows) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertImport($c, ['data_row_count' => $rows, 'products_created' => $rows]), '23514', 'csv_product_imports_data_row_count_range');
        }

        // All rows or none: the record never says fewer (BR-CAT-38).
        foreach ([1, 3] as $created) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertImport($c, ['data_row_count' => 2, 'products_created' => $created]), '23514', 'csv_product_imports_all_rows');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertImport($c, ['actor_display_name' => str_repeat('n', 101)]), '23514', 'csv_product_imports_actor_display_name_length');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertImport($c, ['command_id' => 999999]), '23503', 'csv_product_imports_command_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertImport($c, ['actor_user_id' => 999999]), '23503', 'csv_product_imports_actor_user_fk');

        // SCH-13: each column, null.
        foreach (['command_id', 'file_sha256', 'data_row_count', 'products_created', 'committed_at', 'actor_user_id', 'actor_display_name'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertImport($c, [$column => null]), '23502');
        }

        // One record per command.
        $this->insertImport($this->runtime());
        $this->assertWriteRefused(fn (Connection $c) => $this->insertImport($c), '23505', 'csv_product_imports_command_unique');
    }

    public function test_the_limits_of_a_record_are_exact(): void
    {
        $this->insertImport($this->runtime(), ['data_row_count' => 5000, 'products_created' => 5000]);

        $this->assertSame(5000, $this->observer()->table('csv_product_imports')->value('products_created'));
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10_and_a_record_stays(): void
    {
        $this->insertImport($this->runtime());
        $runtime = $this->runtime();

        $this->assertDenied($runtime, fn (Connection $c) => $c->table('csv_product_imports')->update(['products_created' => 1]));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('csv_product_imports')->delete());
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('csv_product_imports')->truncate());
        $this->assertDenied($runtime, fn (Connection $c) => $c->scalar("select pg_catalog.nextval('csv_product_imports_id_seq')"));

        $this->assertDenied($this->backup(), fn (Connection $c) => $c->table('csv_product_imports')->delete());
        $this->assertDenied($this->backup(), fn (Connection $c) => $c->table('csv_product_imports')->update(['products_created' => 1]));
        $this->assertSame(1, $this->backup()->table('csv_product_imports')->count());
        $this->assertSame(1, $this->observer()->table('csv_product_imports')->count());
    }

    /**
     * A record written directly.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertImport(Connection $c, array $values = []): int
    {
        return $c->table('csv_product_imports')->insertGetId([
            'command_id' => $this->command,
            'file_sha256' => '\\x'.str_repeat('ab', 32),
            'data_row_count' => 1,
            'products_created' => 1,
            'committed_at' => now(),
            'actor_user_id' => $this->owner,
            'actor_display_name' => 'Hendra',
            ...$values,
        ]);
    }
}
