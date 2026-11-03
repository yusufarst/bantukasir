<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-024: the stock adjustment (SCHEMA section 5.7; BR-INV-31 to BR-INV-33), with its checks,
 * keys, indexes and grants (SCHEMA sections 10 and 11, database/privileges.php):
 *
 * - adjustments (class F): the Owner's adjustment of stock to a count, with its mandatory
 *   reason, count reference and reference (BR-INV-31), only once it is confirmed.
 * - adjustment_lines (class F): a signed quantity, never zero, each line posting exactly one
 *   ADJUSTMENT whose quantity is the line's.
 *
 * It completes the key of stock_movements to its adjustment line (EXECUTION_CONTEXT EXC-44), with
 * the line's product and signed quantity. A line and its movement reference each other, so the
 * line's deferred key follows both tables. The movement's cost evidence (a positive line) and
 * issue allocation (a negative line) are held by the presence keys of TASK-021 and TASK-023.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a second, on two
 * new, empty tables and one new key of stock_movements, which holds no ADJUSTMENT yet. Lock
 * impact: the keys to products, users, command_identities and stock_movements take a SHARE ROW
 * EXCLUSIVE lock on those tables, held to the end of the migration's transaction, and validating
 * the key of stock_movements scans it once under that lock: every posting waits that long.
 * Verification (REL-20): db:check-privileges passes; tests/Feature/Inventory/AdjustmentTablesTest.php
 * proves the constraint, deferred, privilege and retention families as the runtime role.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement(<<<'SQL'
            CREATE TABLE adjustments (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                reason text NOT NULL CONSTRAINT adjustments_reason_length CHECK (char_length(reason) BETWEEN 1 AND 1000),
                count_reference text NOT NULL CONSTRAINT adjustments_count_reference_length CHECK (char_length(count_reference) BETWEEN 1 AND 100),
                reference text NOT NULL CONSTRAINT adjustments_reference_length CHECK (char_length(reference) BETWEEN 1 AND 100),
                line_count smallint NOT NULL CONSTRAINT adjustments_line_count_range CHECK (line_count BETWEEN 1 AND 200),
                confirmed_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT adjustments_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT adjustments_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT adjustments_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT adjustments_command_unique UNIQUE (command_id)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE adjustment_lines (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                adjustment_id bigint NOT NULL CONSTRAINT adjustment_lines_adjustment_fk REFERENCES adjustments (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                line_no smallint NOT NULL CONSTRAINT adjustment_lines_line_no_range CHECK (line_no BETWEEN 1 AND 200),
                product_id bigint NOT NULL CONSTRAINT adjustment_lines_product_fk REFERENCES products (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                quantity numeric(13,3) NOT NULL CONSTRAINT adjustment_lines_quantity_range CHECK (quantity <> 0 AND abs(quantity) <= 99999999.999),
                CONSTRAINT adjustment_lines_adjustment_line_unique UNIQUE (adjustment_id, line_no),
                CONSTRAINT adjustment_lines_id_product_quantity_unique UNIQUE (id, product_id, quantity)
            )
            SQL);

        // The key that goes both ways: it follows both of its tables, deferred to the commit.
        $database->statement(<<<'SQL'
            ALTER TABLE adjustment_lines ADD CONSTRAINT adjustment_lines_movement_fk
                FOREIGN KEY (id) REFERENCES stock_movements (adjustment_line_id) DEFERRABLE INITIALLY DEFERRED
            SQL);

        // The key of stock_movements that targets the new table (EXC-44).
        $database->statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_adjustment_line_fk
                FOREIGN KEY (adjustment_line_id, product_id, line_quantity)
                REFERENCES adjustment_lines (id, product_id, quantity) ON DELETE RESTRICT ON UPDATE RESTRICT
            SQL);

        // SCHEMA section 11: every foreign-key column that a key above does not lead.
        $database->statement('CREATE INDEX adjustments_actor_user_id_index ON adjustments (actor_user_id)');
        $database->statement('CREATE INDEX adjustment_lines_product_id_index ON adjustment_lines (product_id)');

        $database->statement('GRANT SELECT, INSERT ON TABLE adjustments, adjustment_lines TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE adjustments, adjustment_lines TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE adjustments_id_seq, adjustment_lines_id_seq TO '.Identifier::quote($roles->backup));
    }
};
