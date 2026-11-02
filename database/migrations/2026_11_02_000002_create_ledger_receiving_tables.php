<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-021: the stock ledger, its receiving documents and the cost that a receipt
 * carries (SCHEMA sections 5.7 and 5.8; ADR-008; D-033, D-069 (a)), with their checks,
 * keys, indexes and grants (SCHEMA sections 10 and 11, database/privileges.php):
 *
 * - stock_movements (class F): every physical change of stock, one immutable row, with
 *   every source column and the three presence keys of SCHEMA section 5.7. Its key to
 *   products (id, base_unit, quantity_precision) makes a product's unit and precision
 *   unchangeable once a movement references them (BR-CAT-07, RACE-05). The keys of the
 *   other source columns and of the issue and return presence keys are added by the
 *   Tasks that create their tables, and those columns are written only from then on
 *   (EXC-44).
 * - stock_balances (class P): the synchronous projection of the movements, never below
 *   zero (BR-INV-03, BR-INV-04).
 * - receiving_documents and receiving_lines (class F): a document exists only once it
 *   is confirmed; there is no draft table (D-069 (a)). A line posts exactly one RECEIPT,
 *   and has no cost column: its cost is the evidence of its movement (D-047).
 * - acquisition_cost_evidence (class F): the amount, or UNKNOWN, which is a status and
 *   never zero; an explicit zero carries its note (BR-COST-03, BR-COST-04).
 * - inventory_valuations (class P): the current moving weighted average of a product.
 * - inventory_posting_blocks and inventory_posting_block_reopenings (class F): a product
 *   is blocked while it has a block without a reopening (G-11).
 *
 * A receiving line and its movement reference each other, and so do a movement and its
 * evidence, so each deferred key follows both of its tables. A transaction that omits one
 * of them cannot commit (BR-INV-08).
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a second, on
 * eight new, empty tables. Lock impact: the keys to products, users and command_identities
 * take a SHARE ROW EXCLUSIVE lock on those three tables, held to the end of the migration's
 * transaction: writes to them, every command's first write among them, wait that long.
 * Verification (REL-20): db:check-privileges passes; tests/Feature/Inventory/LedgerTablesTest.php
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
            CREATE TABLE receiving_documents (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                reference text NULL CONSTRAINT receiving_documents_reference_length CHECK (char_length(reference) BETWEEN 1 AND 100),
                document_date date NULL,
                line_count smallint NOT NULL CONSTRAINT receiving_documents_line_count_range CHECK (line_count BETWEEN 1 AND 500),
                confirmed_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT receiving_documents_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT receiving_documents_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT receiving_documents_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT receiving_documents_command_unique UNIQUE (command_id)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE receiving_lines (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                receiving_document_id bigint NOT NULL CONSTRAINT receiving_lines_document_fk REFERENCES receiving_documents (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                line_no smallint NOT NULL CONSTRAINT receiving_lines_line_no_range CHECK (line_no BETWEEN 1 AND 500),
                product_id bigint NOT NULL CONSTRAINT receiving_lines_product_fk REFERENCES products (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                quantity numeric(13,3) NOT NULL CONSTRAINT receiving_lines_quantity_range CHECK (quantity > 0 AND quantity <= 99999999.999),
                CONSTRAINT receiving_lines_document_line_unique UNIQUE (receiving_document_id, line_no),
                CONSTRAINT receiving_lines_id_product_quantity_unique UNIQUE (id, product_id, quantity)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE stock_movements (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                product_id bigint NOT NULL,
                base_unit text NOT NULL,
                quantity_precision smallint NOT NULL,
                product_sequence bigint NOT NULL CONSTRAINT stock_movements_product_sequence_positive CHECK (product_sequence >= 1),
                movement_type text NOT NULL CONSTRAINT stock_movements_movement_type_allowed CHECK (movement_type IN ('OPENING', 'RECEIPT', 'SALE_ISSUE', 'MANUAL_ISSUE', 'ADJUSTMENT', 'REVERSAL', 'RETURN_RECEIPT')),
                quantity numeric(13,3) NOT NULL,
                opening_line_id bigint NULL,
                receiving_line_id bigint NULL,
                sale_line_id bigint NULL,
                manual_issue_line_id bigint NULL,
                adjustment_line_id bigint NULL,
                reversal_id bigint NULL,
                return_line_id bigint NULL,
                line_quantity numeric(13,3) NULL,
                return_condition text NULL,
                opening_replacement boolean NULL,
                cost_evidence_presence bigint GENERATED ALWAYS AS (CASE WHEN movement_type IN ('OPENING', 'RECEIPT') OR (movement_type = 'ADJUSTMENT' AND quantity > 0) THEN id END) STORED,
                issue_allocation_presence bigint GENERATED ALWAYS AS (CASE WHEN movement_type IN ('SALE_ISSUE', 'MANUAL_ISSUE') OR (movement_type = 'ADJUSTMENT' AND quantity < 0) THEN id END) STORED,
                return_allocation_presence bigint GENERATED ALWAYS AS (CASE WHEN movement_type = 'RETURN_RECEIPT' THEN id END) STORED,
                recorded_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT stock_movements_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                command_id bigint NOT NULL CONSTRAINT stock_movements_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT stock_movements_quantity_range CHECK (quantity <> 0 AND abs(quantity) <= 99999999.999),
                CONSTRAINT stock_movements_quantity_precision_check CHECK (quantity = round(quantity, quantity_precision)),
                CONSTRAINT stock_movements_sign CHECK (
                    (movement_type IN ('OPENING', 'RECEIPT', 'RETURN_RECEIPT') AND quantity > 0)
                    OR (movement_type IN ('SALE_ISSUE', 'MANUAL_ISSUE') AND quantity < 0)
                    OR movement_type IN ('ADJUSTMENT', 'REVERSAL')
                ),
                CONSTRAINT stock_movements_one_source CHECK (
                    num_nonnulls(opening_line_id, receiving_line_id, sale_line_id, manual_issue_line_id, adjustment_line_id, reversal_id, return_line_id) = 1
                    AND (movement_type = 'OPENING') = (opening_line_id IS NOT NULL)
                    AND (movement_type = 'RECEIPT') = (receiving_line_id IS NOT NULL)
                    AND (movement_type = 'SALE_ISSUE') = (sale_line_id IS NOT NULL)
                    AND (movement_type = 'MANUAL_ISSUE') = (manual_issue_line_id IS NOT NULL)
                    AND (movement_type = 'ADJUSTMENT') = (adjustment_line_id IS NOT NULL)
                    AND (movement_type = 'REVERSAL') = (reversal_id IS NOT NULL)
                    AND (movement_type = 'RETURN_RECEIPT') = (return_line_id IS NOT NULL)
                ),
                CONSTRAINT stock_movements_return_condition CHECK (
                    (movement_type = 'RETURN_RECEIPT') = (return_condition IS NOT NULL)
                    AND (return_condition IS NULL OR return_condition = 'SALEABLE')
                ),
                CONSTRAINT stock_movements_opening_replacement_set CHECK ((movement_type = 'OPENING') = (opening_replacement IS NOT NULL)),
                CONSTRAINT stock_movements_opening_first CHECK (movement_type <> 'OPENING' OR product_sequence = 1 OR opening_replacement),
                CONSTRAINT stock_movements_line_quantity CHECK (
                    (movement_type = 'REVERSAL') = (line_quantity IS NULL)
                    AND (line_quantity IS NULL OR line_quantity = CASE WHEN movement_type IN ('SALE_ISSUE', 'MANUAL_ISSUE') THEN -quantity ELSE quantity END)
                ),
                CONSTRAINT stock_movements_product_sequence_unique UNIQUE (product_id, product_sequence),
                CONSTRAINT stock_movements_opening_line_unique UNIQUE (opening_line_id),
                CONSTRAINT stock_movements_receiving_line_unique UNIQUE (receiving_line_id),
                CONSTRAINT stock_movements_sale_line_unique UNIQUE (sale_line_id),
                CONSTRAINT stock_movements_manual_issue_line_unique UNIQUE (manual_issue_line_id),
                CONSTRAINT stock_movements_adjustment_line_unique UNIQUE (adjustment_line_id),
                CONSTRAINT stock_movements_reversal_unique UNIQUE (reversal_id),
                CONSTRAINT stock_movements_return_line_unique UNIQUE (return_line_id),
                CONSTRAINT stock_movements_id_product_type_quantity_unique UNIQUE (id, product_id, movement_type, quantity),
                CONSTRAINT stock_movements_id_product_type_quantity_sequence_unique UNIQUE (id, product_id, movement_type, quantity, product_sequence),
                CONSTRAINT stock_movements_id_product_quantity_sequence_unique UNIQUE (id, product_id, quantity, product_sequence),
                CONSTRAINT stock_movements_id_sale_line_unique UNIQUE (id, sale_line_id),
                CONSTRAINT stock_movements_id_return_line_unique UNIQUE (id, return_line_id),
                CONSTRAINT stock_movements_id_opening_line_unique UNIQUE (id, opening_line_id),
                CONSTRAINT stock_movements_product_unit_fk FOREIGN KEY (product_id, base_unit, quantity_precision)
                    REFERENCES products (id, base_unit, quantity_precision) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT stock_movements_receiving_line_fk FOREIGN KEY (receiving_line_id, product_id, line_quantity)
                    REFERENCES receiving_lines (id, product_id, quantity) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE acquisition_cost_evidence (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                movement_id bigint NOT NULL,
                product_id bigint NOT NULL,
                movement_type text NOT NULL CONSTRAINT acquisition_cost_evidence_movement_type_allowed CHECK (movement_type IN ('OPENING', 'RECEIPT', 'ADJUSTMENT')),
                quantity numeric(13,3) NOT NULL CONSTRAINT acquisition_cost_evidence_quantity_range CHECK (quantity > 0 AND quantity <= 99999999.999),
                cost_status text NOT NULL CONSTRAINT acquisition_cost_evidence_cost_status_allowed CHECK (cost_status IN ('KNOWN', 'UNKNOWN')),
                total_amount numeric(18,6) NULL CONSTRAINT acquisition_cost_evidence_total_amount_range CHECK (total_amount BETWEEN 0 AND 999999999999.999999),
                zero_cost_note text NULL CONSTRAINT acquisition_cost_evidence_zero_cost_note_length CHECK (char_length(zero_cost_note) BETWEEN 1 AND 1000),
                reference text NULL CONSTRAINT acquisition_cost_evidence_reference_length CHECK (char_length(reference) BETWEEN 1 AND 100),
                recorded_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT acquisition_cost_evidence_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT acquisition_cost_evidence_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT acquisition_cost_evidence_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT acquisition_cost_evidence_status_amount CHECK ((cost_status = 'KNOWN') = (total_amount IS NOT NULL)),
                CONSTRAINT acquisition_cost_evidence_zero_has_note CHECK (total_amount IS DISTINCT FROM 0 OR zero_cost_note IS NOT NULL),
                CONSTRAINT acquisition_cost_evidence_movement_unique UNIQUE (movement_id),
                CONSTRAINT acquisition_cost_evidence_id_product_unique UNIQUE (id, product_id),
                CONSTRAINT acquisition_cost_evidence_id_product_movement_unique UNIQUE (id, product_id, movement_id),
                CONSTRAINT acquisition_cost_evidence_movement_fk FOREIGN KEY (movement_id, product_id, movement_type, quantity)
                    REFERENCES stock_movements (id, product_id, movement_type, quantity) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        // The keys that go both ways: each follows both of its tables, deferred to the commit.
        $database->statement(<<<'SQL'
            ALTER TABLE receiving_lines ADD CONSTRAINT receiving_lines_movement_fk
                FOREIGN KEY (id) REFERENCES stock_movements (receiving_line_id) DEFERRABLE INITIALLY DEFERRED
            SQL);
        $database->statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_cost_evidence_presence_fk
                FOREIGN KEY (cost_evidence_presence) REFERENCES acquisition_cost_evidence (movement_id) DEFERRABLE INITIALLY DEFERRED
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE stock_balances (
                product_id bigint PRIMARY KEY CONSTRAINT stock_balances_product_fk REFERENCES products (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                on_hand numeric(13,3) NOT NULL CONSTRAINT stock_balances_on_hand_range CHECK (on_hand BETWEEN 0 AND 9999999999.999),
                last_sequence bigint NOT NULL CONSTRAINT stock_balances_last_sequence_positive CHECK (last_sequence >= 1),
                updated_at timestamptz NOT NULL
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE inventory_valuations (
                product_id bigint PRIMARY KEY CONSTRAINT inventory_valuations_product_fk REFERENCES products (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                value_status text NOT NULL CONSTRAINT inventory_valuations_value_status_allowed CHECK (value_status IN ('KNOWN', 'UNKNOWN')),
                value numeric(21,6) NULL CONSTRAINT inventory_valuations_value_range CHECK (value BETWEEN 0 AND 999999999999999.999999),
                publication_no bigint NOT NULL CONSTRAINT inventory_valuations_publication_no_positive CHECK (publication_no >= 1),
                last_sequence bigint NOT NULL CONSTRAINT inventory_valuations_last_sequence_positive CHECK (last_sequence >= 1),
                published_at timestamptz NOT NULL,
                CONSTRAINT inventory_valuations_status_value CHECK ((value_status = 'KNOWN') = (value IS NOT NULL))
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE inventory_posting_blocks (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                product_id bigint NOT NULL CONSTRAINT inventory_posting_blocks_product_fk REFERENCES products (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                blocked_at timestamptz NOT NULL,
                actor_kind text NOT NULL CONSTRAINT inventory_posting_blocks_actor_kind_allowed CHECK (actor_kind IN ('USER', 'OPERATOR_PROCEDURE', 'SCHEDULED_COMMAND')),
                actor_user_id bigint NULL CONSTRAINT inventory_posting_blocks_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_process text NULL CONSTRAINT inventory_posting_blocks_actor_process_format CHECK (actor_process ~ '^[a-z][a-z0-9_]{0,63}$'),
                detail_code text NOT NULL CONSTRAINT inventory_posting_blocks_detail_code_format CHECK (detail_code ~ '^[A-Z][A-Z0-9_]{0,63}$'),
                command_id bigint NOT NULL CONSTRAINT inventory_posting_blocks_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT inventory_posting_blocks_actor CHECK (
                    (actor_kind = 'USER' AND actor_user_id IS NOT NULL AND actor_process IS NULL)
                    OR (actor_kind <> 'USER' AND actor_user_id IS NULL AND actor_process IS NOT NULL)
                )
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE inventory_posting_block_reopenings (
                block_id bigint PRIMARY KEY CONSTRAINT inventory_posting_block_reopenings_block_fk REFERENCES inventory_posting_blocks (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                reopened_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT inventory_posting_block_reopenings_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT inventory_posting_block_reopenings_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                reason text NULL CONSTRAINT inventory_posting_block_reopenings_reason_length CHECK (char_length(reason) BETWEEN 1 AND 1000),
                command_id bigint NOT NULL CONSTRAINT inventory_posting_block_reopenings_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        // SCHEMA section 11: every foreign-key column that a key above does not lead.
        $database->statement('CREATE INDEX receiving_documents_actor_user_id_index ON receiving_documents (actor_user_id)');
        $database->statement('CREATE INDEX receiving_lines_product_id_index ON receiving_lines (product_id)');
        $database->statement('CREATE INDEX stock_movements_actor_user_id_index ON stock_movements (actor_user_id)');
        $database->statement('CREATE INDEX stock_movements_command_id_index ON stock_movements (command_id)');
        $database->statement('CREATE INDEX stock_movements_cost_evidence_presence_index ON stock_movements (cost_evidence_presence)');
        $database->statement('CREATE INDEX acquisition_cost_evidence_product_id_index ON acquisition_cost_evidence (product_id)');
        $database->statement('CREATE INDEX acquisition_cost_evidence_actor_user_id_index ON acquisition_cost_evidence (actor_user_id)');
        $database->statement('CREATE INDEX acquisition_cost_evidence_command_id_index ON acquisition_cost_evidence (command_id)');
        $database->statement('CREATE INDEX inventory_posting_blocks_product_id_index ON inventory_posting_blocks (product_id)');
        $database->statement('CREATE INDEX inventory_posting_blocks_actor_user_id_index ON inventory_posting_blocks (actor_user_id)');
        $database->statement('CREATE INDEX inventory_posting_blocks_command_id_index ON inventory_posting_blocks (command_id)');
        $database->statement('CREATE INDEX inventory_posting_block_reopenings_actor_user_id_index ON inventory_posting_block_reopenings (actor_user_id)');
        $database->statement('CREATE INDEX inventory_posting_block_reopenings_command_id_index ON inventory_posting_block_reopenings (command_id)');

        $database->statement('GRANT SELECT, INSERT ON TABLE stock_movements, receiving_documents, receiving_lines, acquisition_cost_evidence, inventory_posting_blocks, inventory_posting_block_reopenings TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT, UPDATE (on_hand, last_sequence, updated_at) ON TABLE stock_balances TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT, UPDATE (value_status, value, publication_no, last_sequence, published_at) ON TABLE inventory_valuations TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE stock_movements, stock_balances, receiving_documents, receiving_lines, acquisition_cost_evidence, inventory_valuations, inventory_posting_blocks, inventory_posting_block_reopenings TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE stock_movements_id_seq, receiving_documents_id_seq, receiving_lines_id_seq, acquisition_cost_evidence_id_seq, inventory_posting_blocks_id_seq TO '.Identifier::quote($roles->backup));
    }
};
