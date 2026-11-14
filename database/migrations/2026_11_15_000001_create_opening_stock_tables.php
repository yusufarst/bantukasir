<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-035: the Owner's onboarding entries and the restore hold of onboarding (SCHEMA section 5.7;
 * BR-INV-23 to BR-INV-30, BR-INV-47, BR-INV-48; D-071, D-072, D-087), with their checks, keys,
 * indexes and grants (SCHEMA sections 10 and 11, database/privileges.php):
 *
 * - opening_stock_entries (class F): one confirmed opening stock command of at most 100 rows
 *   (BR-INV-25), or the one row of a replacement (RC-48).
 * - opening_stock_lines (class F): a positive row, each posting exactly one OPENING whose product and
 *   quantity are the line's; its count reference is mandatory (BR-INV-25). A replacement line names
 *   the reversed line it replaces and that line's OPENING, which its reversal cancelled, with the
 *   Owner's reason; a line is replaced at most once, and a product has at most one original line
 *   (D-087; G-22, G-24).
 * - onboarding_zero_counts (class F): a product counted zero, on the checklist, with no movement
 *   (BR-INV-27).
 * - onboarding_zero_count_withdrawals (class F): the Owner's withdrawal of a zero count, at most once
 *   per zero count; the zero count stays (D-087).
 * - onboarding_restore_holds and onboarding_restore_reconciliations (class F): the hold of D-072,
 *   which the after-restore procedure sets and the restore reconciliation decides (TASK-037); a hold
 *   without its reconciliation refuses every onboarding and opening command (G-26).
 *
 * It completes the key of stock_movements to its opening line (EXECUTION_CONTEXT EXC-44), with the
 * line's product, quantity and whether it is a replacement, and the replacement keys of a line to the
 * reversed line, its OPENING and that OPENING's reversal. A line and its movement reference each
 * other, so the line's deferred key follows both tables.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a second, on six new,
 * empty tables and one new key of stock_movements, which holds no OPENING yet. Lock impact: the keys
 * to products, users, command_identities, stock_movements and reversals take a SHARE ROW EXCLUSIVE
 * lock on those tables, held to the end of the migration's transaction, and validating the key of
 * stock_movements scans it once under that lock: every posting and every reversal waits that long.
 * Verification (REL-20): db:check-privileges passes; tests/Feature/Inventory/OnboardingTablesTest.php
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
            CREATE TABLE opening_stock_entries (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                row_count smallint NOT NULL CONSTRAINT opening_stock_entries_row_count_range CHECK (row_count BETWEEN 1 AND 100),
                confirmed_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT opening_stock_entries_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT opening_stock_entries_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT opening_stock_entries_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT opening_stock_entries_command_unique UNIQUE (command_id)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE opening_stock_lines (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                opening_stock_entry_id bigint NOT NULL CONSTRAINT opening_stock_lines_entry_fk REFERENCES opening_stock_entries (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                row_no smallint NOT NULL CONSTRAINT opening_stock_lines_row_no_range CHECK (row_no BETWEEN 1 AND 100),
                product_id bigint NOT NULL CONSTRAINT opening_stock_lines_product_fk REFERENCES products (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                quantity numeric(13,3) NOT NULL CONSTRAINT opening_stock_lines_quantity_range CHECK (quantity > 0 AND quantity <= 99999999.999),
                count_reference text NOT NULL CONSTRAINT opening_stock_lines_count_reference_length CHECK (char_length(count_reference) BETWEEN 1 AND 100),
                replaces_line_id bigint NULL,
                replaces_movement_id bigint NULL,
                replacement_reason text NULL CONSTRAINT opening_stock_lines_replacement_reason_length CHECK (char_length(replacement_reason) BETWEEN 1 AND 1000),
                is_replacement boolean GENERATED ALWAYS AS (replaces_line_id IS NOT NULL) STORED,
                CONSTRAINT opening_stock_lines_replacement_complete CHECK (
                    (replaces_line_id IS NULL) = (replaces_movement_id IS NULL)
                    AND (replaces_line_id IS NULL) = (replacement_reason IS NULL)
                ),
                CONSTRAINT opening_stock_lines_entry_row_unique UNIQUE (opening_stock_entry_id, row_no),
                CONSTRAINT opening_stock_lines_movement_target_unique UNIQUE (id, product_id, quantity, is_replacement),
                CONSTRAINT opening_stock_lines_id_product_unique UNIQUE (id, product_id),
                CONSTRAINT opening_stock_lines_replaces_line_unique UNIQUE (replaces_line_id),
                CONSTRAINT opening_stock_lines_replaces_line_fk FOREIGN KEY (replaces_line_id, product_id)
                    REFERENCES opening_stock_lines (id, product_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT opening_stock_lines_replaces_movement_fk FOREIGN KEY (replaces_movement_id, replaces_line_id)
                    REFERENCES stock_movements (id, opening_line_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT opening_stock_lines_replaces_reversal_fk FOREIGN KEY (replaces_movement_id)
                    REFERENCES reversals (original_movement_id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        // One original line per product (BR-INV-26; G-22).
        $database->statement('CREATE UNIQUE INDEX opening_lines_one_original ON opening_stock_lines (product_id) WHERE replaces_line_id IS NULL');

        $database->statement(<<<'SQL'
            CREATE TABLE onboarding_zero_counts (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                product_id bigint NOT NULL CONSTRAINT onboarding_zero_counts_product_fk REFERENCES products (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                count_reference text NULL CONSTRAINT onboarding_zero_counts_count_reference_length CHECK (char_length(count_reference) BETWEEN 1 AND 100),
                recorded_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT onboarding_zero_counts_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT onboarding_zero_counts_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT onboarding_zero_counts_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT onboarding_zero_counts_id_product_unique UNIQUE (id, product_id)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE onboarding_zero_count_withdrawals (
                zero_count_id bigint PRIMARY KEY,
                product_id bigint NOT NULL,
                reason text NOT NULL CONSTRAINT onboarding_zero_count_withdrawals_reason_length CHECK (char_length(reason) BETWEEN 1 AND 1000),
                recorded_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT onboarding_zero_count_withdrawals_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT onboarding_zero_count_withdrawals_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT onboarding_zero_count_withdrawals_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT onboarding_zero_count_withdrawals_command_unique UNIQUE (command_id),
                CONSTRAINT onboarding_zero_count_withdrawals_zero_count_fk FOREIGN KEY (zero_count_id, product_id)
                    REFERENCES onboarding_zero_counts (id, product_id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE onboarding_restore_holds (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                restored_state text NOT NULL CONSTRAINT onboarding_restore_holds_restored_state_allowed CHECK (restored_state IN ('NOT_STARTED', 'ACTIVE')),
                recovery_epoch bytea NOT NULL CONSTRAINT onboarding_restore_holds_recovery_epoch_length CHECK (octet_length(recovery_epoch) >= 16),
                set_at timestamptz NOT NULL
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE onboarding_restore_reconciliations (
                hold_id bigint PRIMARY KEY CONSTRAINT onboarding_restore_reconciliations_hold_fk REFERENCES onboarding_restore_holds (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                outcome text NOT NULL CONSTRAINT onboarding_restore_reconciliations_outcome_allowed CHECK (outcome IN ('RELEASED_BEFORE_RESTORE', 'NEVER_RELEASED')),
                decided_by_user_id bigint NOT NULL CONSTRAINT onboarding_restore_reconciliations_decided_by_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                operator_note text NOT NULL CONSTRAINT onboarding_restore_reconciliations_operator_note_length CHECK (char_length(operator_note) BETWEEN 1 AND 1000),
                recorded_at timestamptz NOT NULL
            )
            SQL);

        // The key that goes both ways: it follows both of its tables, deferred to the commit.
        $database->statement(<<<'SQL'
            ALTER TABLE opening_stock_lines ADD CONSTRAINT opening_stock_lines_movement_fk
                FOREIGN KEY (id) REFERENCES stock_movements (opening_line_id) DEFERRABLE INITIALLY DEFERRED
            SQL);

        // The key of stock_movements that targets the new table (EXC-44): the OPENING moves exactly its
        // line's product and quantity, and is a replacement exactly when its line is one (D-087).
        $database->statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_opening_line_fk
                FOREIGN KEY (opening_line_id, product_id, line_quantity, opening_replacement)
                REFERENCES opening_stock_lines (id, product_id, quantity, is_replacement) ON DELETE RESTRICT ON UPDATE RESTRICT
            SQL);

        // SCHEMA section 11: every foreign-key column that a key above does not lead.
        $database->statement('CREATE INDEX opening_stock_entries_actor_user_id_index ON opening_stock_entries (actor_user_id)');
        $database->statement('CREATE INDEX opening_stock_lines_product_id_index ON opening_stock_lines (product_id)');
        $database->statement('CREATE INDEX opening_stock_lines_replaces_movement_id_index ON opening_stock_lines (replaces_movement_id)');
        $database->statement('CREATE INDEX onboarding_zero_counts_product_id_index ON onboarding_zero_counts (product_id)');
        $database->statement('CREATE INDEX onboarding_zero_counts_actor_user_id_index ON onboarding_zero_counts (actor_user_id)');
        $database->statement('CREATE INDEX onboarding_zero_counts_command_id_index ON onboarding_zero_counts (command_id)');
        $database->statement('CREATE INDEX onboarding_zero_count_withdrawals_actor_user_id_index ON onboarding_zero_count_withdrawals (actor_user_id)');
        $database->statement('CREATE INDEX onboarding_restore_reconciliations_decided_by_user_id_index ON onboarding_restore_reconciliations (decided_by_user_id)');

        $database->statement('GRANT SELECT, INSERT ON TABLE opening_stock_entries, opening_stock_lines, onboarding_zero_counts, onboarding_zero_count_withdrawals, onboarding_restore_holds, onboarding_restore_reconciliations TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE opening_stock_entries, opening_stock_lines, onboarding_zero_counts, onboarding_zero_count_withdrawals, onboarding_restore_holds, onboarding_restore_reconciliations TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE opening_stock_entries_id_seq, opening_stock_lines_id_seq, onboarding_zero_counts_id_seq, onboarding_restore_holds_id_seq TO '.Identifier::quote($roles->backup));
    }
};
