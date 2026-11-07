<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-026: the Owner's reversal of one movement (SCHEMA section 5.7, `reversals`; BR-INV-34 to
 * BR-INV-37; ADR-008 section 4.3), with its checks, keys, indexes and grants (SCHEMA sections 10 and
 * 11, database/privileges.php):
 *
 * - reversals (class F): the full, exact inverse of one OPENING, RECEIPT or MANUAL_ISSUE, at most once
 *   per source (the unique original_movement_id; BR-INV-35, RACE-10), with the Owner's reason. Its key
 *   to the source movement copies the source's product, type and quantity, so the inverse is the
 *   source's negated quantity and the Owner never enters a free one (BR-INV-34). A reversal and its
 *   REVERSAL movement reference each other, so the reversal's deferred key follows both tables.
 *
 * It completes two keys that target the new table (EXECUTION_CONTEXT EXC-44): the REVERSAL movement's,
 * with its product and quantity, so that the movement is exactly the reversal's inverse; and the mark
 * clearance's of TASK-025, so that a clearance SOURCE_REVERSED names the reversal of the mark's own
 * source movement (SCHEMA section 5.8).
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a second, on one new,
 * empty table and two new keys whose columns hold no value yet (no REVERSAL movement and no clearance
 * SOURCE_REVERSED exists). Lock impact: the keys to users, command_identities, stock_movements and
 * cost_incomplete_mark_clearances take a SHARE ROW EXCLUSIVE lock on those tables, held to the end of
 * the migration's transaction, and validating the keys of stock_movements and of the clearances scans
 * each once under that lock: every posting and every cost correction waits that long.
 * Verification (REL-20): db:check-privileges passes; tests/Feature/Inventory/ReversalTablesTest.php
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
            CREATE TABLE reversals (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                original_movement_id bigint NOT NULL,
                product_id bigint NOT NULL,
                original_type text NOT NULL CONSTRAINT reversals_original_type_allowed CHECK (original_type IN ('OPENING', 'RECEIPT', 'MANUAL_ISSUE')),
                original_quantity numeric(13,3) NOT NULL,
                inverse_quantity numeric(13,3) NOT NULL,
                reason text NOT NULL CONSTRAINT reversals_reason_length CHECK (char_length(reason) BETWEEN 1 AND 1000),
                recorded_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT reversals_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT reversals_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT reversals_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT reversals_inverse_exact CHECK (inverse_quantity = -original_quantity),
                CONSTRAINT reversals_original_movement_unique UNIQUE (original_movement_id),
                CONSTRAINT reversals_command_unique UNIQUE (command_id),
                CONSTRAINT reversals_id_product_inverse_unique UNIQUE (id, product_id, inverse_quantity),
                CONSTRAINT reversals_id_original_movement_unique UNIQUE (id, original_movement_id),
                CONSTRAINT reversals_original_fk FOREIGN KEY (original_movement_id, product_id, original_type, original_quantity)
                    REFERENCES stock_movements (id, product_id, movement_type, quantity) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        // The key that goes both ways: it follows both of its tables, deferred to the commit.
        $database->statement(<<<'SQL'
            ALTER TABLE reversals ADD CONSTRAINT reversals_movement_fk
                FOREIGN KEY (id) REFERENCES stock_movements (reversal_id) DEFERRABLE INITIALLY DEFERRED
            SQL);

        // The keys of earlier tables that target the new one (EXC-44).
        $database->statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_reversal_fk
                FOREIGN KEY (reversal_id, product_id, quantity)
                REFERENCES reversals (id, product_id, inverse_quantity) ON DELETE RESTRICT ON UPDATE RESTRICT
            SQL);

        $database->statement(<<<'SQL'
            ALTER TABLE cost_incomplete_mark_clearances ADD CONSTRAINT cost_incomplete_mark_clearances_reversal_fk
                FOREIGN KEY (clearing_reversal_id, evidence_movement_id)
                REFERENCES reversals (id, original_movement_id) ON DELETE RESTRICT ON UPDATE RESTRICT
            SQL);

        // SCHEMA section 11: every foreign-key column that a key above does not lead, and the index of
        // clearing_reversal_id that TASK-025 left to this key.
        $database->statement('CREATE INDEX reversals_actor_user_id_index ON reversals (actor_user_id)');
        $database->statement('CREATE INDEX reversals_product_id_index ON reversals (product_id)');
        $database->statement('CREATE INDEX cost_incomplete_mark_clearances_reversal_index ON cost_incomplete_mark_clearances (clearing_reversal_id)');

        $database->statement('GRANT SELECT, INSERT ON TABLE reversals TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE reversals TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE reversals_id_seq TO '.Identifier::quote($roles->backup));
    }
};
