<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-019: csv_product_imports (class F; SCHEMA section 5.6; D-037, D-054), the record
 * of a committed import, with its checks, keys, index and grants (SCHEMA section 10,
 * database/privileges.php): one per command; the checksum of the confirmed file; every
 * row of the file created, or no record at all (BR-CAT-38). The products carry the
 * command in products.created_command_id, and the row summary is in the audit event. The
 * uploaded file itself is never in the database.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a second, on
 * one new, empty table. Lock impact: the keys to users and command_identities take a
 * SHARE ROW EXCLUSIVE lock on those two tables, held to the end of the migration's
 * transaction: writes to them, every command's first write among them, wait that long.
 * Verification (REL-20): db:check-privileges passes;
 * tests/Feature/Catalog/CsvImportTableTest.php proves the constraint, deferred, privilege
 * and retention families as the runtime role.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement(<<<'SQL'
            CREATE TABLE csv_product_imports (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                command_id bigint NOT NULL CONSTRAINT csv_product_imports_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                file_sha256 bytea NOT NULL CONSTRAINT csv_product_imports_file_sha256_length CHECK (octet_length(file_sha256) = 32),
                data_row_count integer NOT NULL CONSTRAINT csv_product_imports_data_row_count_range CHECK (data_row_count BETWEEN 1 AND 5000),
                products_created integer NOT NULL,
                committed_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT csv_product_imports_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT csv_product_imports_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                CONSTRAINT csv_product_imports_command_unique UNIQUE (command_id),
                CONSTRAINT csv_product_imports_all_rows CHECK (products_created = data_row_count)
            )
            SQL);

        $database->statement('CREATE INDEX csv_product_imports_actor_user_id_index ON csv_product_imports (actor_user_id)');

        $database->statement('GRANT SELECT, INSERT ON TABLE csv_product_imports TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE csv_product_imports TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE csv_product_imports_id_seq TO '.Identifier::quote($roles->backup));
    }
};
