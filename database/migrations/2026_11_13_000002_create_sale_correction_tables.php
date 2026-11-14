<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-033: the Owner's correction of a Sale — a refund, a return or both — and the cost that a
 * saleable return restores (SCHEMA sections 5.7 to 5.9; ADR-008 sections 1 and 3; D-035, D-061;
 * BR-RFD-01 to BR-RFD-17, BR-COST-17 to BR-COST-19), with their checks, keys, indexes and grants
 * (SCHEMA sections 10 and 11, database/privileges.php):
 *
 * - sale_corrections (class F): one per command, linked to its Sale, dated by its posting date
 *   (BR-COST-36), with its mandatory reason and the frozen name of its actor.
 * - refunds and refund_lines (class F): at most one refund per correction, its amount above zero,
 *   its payout method and funding source with exactly one source (D-045), a drawer-funded refund
 *   naming its session, and the reference of its payout; each line names a line of the same Sale.
 * - return_lines (class F): the quantity returned of a line of the same Sale and its product,
 *   SALEABLE or NOT_SALEABLE; by its presence key a saleable line posts exactly one RETURN_RECEIPT
 *   and a not-saleable one none (BR-INV-39).
 * - return_cost_allocations (class F): the cost that a saleable return line restored at commit,
 *   linked to the SALE_ISSUE allocation of the same line and product (D-061), never changed.
 * - return_cost_allocation_projections (class P): its current value, inserted equal to the fact and
 *   afterwards written only by a replay publication (ADR-008 section 4.3).
 *
 * EXC-44: the keys of stock_movements that target the new tables, which TASK-021 left for this
 * Task: its return line, with the line's condition, product and quantity, so that a RETURN_RECEIPT
 * moves exactly a saleable line's product and quantity, and its return presence key, deferred, so
 * that a RETURN_RECEIPT without its return allocation cannot commit (BR-INV-08). The running totals
 * of BR-RFD-03 are TASK-028's tables, which this migration leaves as they are.
 *
 * A saleable return line and its movement reference each other, and so do a movement and its return
 * allocation, and an allocation and its projection, so each deferred key follows both of its tables.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a second, on six new,
 * empty tables and two new keys of stock_movements, which holds no RETURN_RECEIPT yet. Lock impact:
 * the keys to sales, sale_lines, cash_sessions, users, command_identities, issue_cost_allocations and
 * stock_movements take a SHARE ROW EXCLUSIVE lock on those tables, held to the end of the
 * migration's transaction, and validating the two keys of stock_movements scans it once under that
 * lock: every posting and every Sale waits that long. Verification (REL-20): db:check-privileges
 * passes; tests/Feature/Sales/CorrectionTablesTest.php proves the constraint, deferred, privilege and
 * retention families as the runtime role.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement(<<<'SQL'
            CREATE TABLE sale_corrections (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                sale_id bigint NOT NULL CONSTRAINT sale_corrections_sale_fk REFERENCES sales (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                business_date date NOT NULL,
                reason text NOT NULL CONSTRAINT sale_corrections_reason_length CHECK (char_length(reason) BETWEEN 1 AND 1000),
                recorded_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT sale_corrections_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT sale_corrections_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT sale_corrections_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT sale_corrections_command_unique UNIQUE (command_id),
                CONSTRAINT sale_corrections_id_sale_unique UNIQUE (id, sale_id)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE refunds (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                correction_id bigint NOT NULL,
                sale_id bigint NOT NULL,
                amount bigint NOT NULL CONSTRAINT refunds_amount_range CHECK (amount BETWEEN 1 AND 999999999999),
                payout_method text NOT NULL,
                funding_source text NULL,
                cash_session_id bigint NULL CONSTRAINT refunds_cash_session_fk REFERENCES cash_sessions (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                payout_reference text NOT NULL CONSTRAINT refunds_payout_reference_length CHECK (char_length(payout_reference) BETWEEN 1 AND 100),
                CONSTRAINT refunds_payout_funding CHECK (
                    (payout_method = 'CASH' AND funding_source IS NOT NULL AND funding_source IN ('DRAWER', 'OWNER_FUNDS'))
                    OR (payout_method = 'BANK_TRANSFER' AND funding_source IS NULL)
                ),
                CONSTRAINT refunds_one_source CHECK ((funding_source IS NOT DISTINCT FROM 'DRAWER') = (cash_session_id IS NOT NULL)),
                CONSTRAINT refunds_correction_unique UNIQUE (correction_id),
                CONSTRAINT refunds_id_sale_unique UNIQUE (id, sale_id),
                CONSTRAINT refunds_correction_fk FOREIGN KEY (correction_id, sale_id)
                    REFERENCES sale_corrections (id, sale_id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE refund_lines (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                refund_id bigint NOT NULL,
                sale_id bigint NOT NULL,
                sale_line_id bigint NOT NULL,
                amount bigint NOT NULL CONSTRAINT refund_lines_amount_range CHECK (amount BETWEEN 1 AND 999999999999),
                CONSTRAINT refund_lines_refund_line_unique UNIQUE (refund_id, sale_line_id),
                CONSTRAINT refund_lines_refund_fk FOREIGN KEY (refund_id, sale_id)
                    REFERENCES refunds (id, sale_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT refund_lines_sale_line_fk FOREIGN KEY (sale_line_id, sale_id)
                    REFERENCES sale_lines (id, sale_id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE return_lines (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                correction_id bigint NOT NULL,
                sale_id bigint NOT NULL,
                sale_line_id bigint NOT NULL,
                product_id bigint NOT NULL,
                quantity numeric(13,3) NOT NULL CONSTRAINT return_lines_quantity_range CHECK (quantity > 0 AND quantity <= 99999999.999),
                condition text NOT NULL CONSTRAINT return_lines_condition_allowed CHECK (condition IN ('SALEABLE', 'NOT_SALEABLE')),
                movement_presence bigint GENERATED ALWAYS AS (CASE WHEN condition = 'SALEABLE' THEN id END) STORED,
                CONSTRAINT return_lines_correction_line_condition_unique UNIQUE (correction_id, sale_line_id, condition),
                CONSTRAINT return_lines_id_condition_product_quantity_unique UNIQUE (id, condition, product_id, quantity),
                CONSTRAINT return_lines_id_sale_line_condition_quantity_unique UNIQUE (id, sale_line_id, condition, quantity),
                CONSTRAINT return_lines_correction_fk FOREIGN KEY (correction_id, sale_id)
                    REFERENCES sale_corrections (id, sale_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT return_lines_sale_line_fk FOREIGN KEY (sale_line_id, sale_id, product_id)
                    REFERENCES sale_lines (id, sale_id, product_id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE return_cost_allocations (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                return_line_id bigint NOT NULL,
                movement_id bigint NOT NULL,
                product_id bigint NOT NULL,
                sale_line_id bigint NOT NULL,
                return_condition text NOT NULL CONSTRAINT return_cost_allocations_saleable CHECK (return_condition = 'SALEABLE'),
                issue_allocation_id bigint NOT NULL,
                returned_quantity numeric(13,3) NOT NULL CONSTRAINT return_cost_allocations_returned_quantity_range CHECK (returned_quantity > 0 AND returned_quantity <= 99999999.999),
                product_sequence bigint NOT NULL,
                at_commit_status text NOT NULL CONSTRAINT return_cost_allocations_at_commit_status_allowed CHECK (at_commit_status IN ('KNOWN', 'UNKNOWN')),
                at_commit_amount numeric(21,6) NULL CONSTRAINT return_cost_allocations_at_commit_amount_range CHECK (at_commit_amount BETWEEN 0 AND 999999999999999.999999),
                based_on_publication_no bigint NOT NULL CONSTRAINT return_cost_allocations_based_on_publication_positive CHECK (based_on_publication_no >= 1),
                recorded_at timestamptz NOT NULL,
                CONSTRAINT return_cost_allocations_status_amount CHECK ((at_commit_status = 'KNOWN') = (at_commit_amount IS NOT NULL)),
                CONSTRAINT return_cost_allocations_return_line_unique UNIQUE (return_line_id),
                CONSTRAINT return_cost_allocations_movement_unique UNIQUE (movement_id),
                CONSTRAINT return_cost_allocations_movement_line_fk FOREIGN KEY (movement_id, return_line_id)
                    REFERENCES stock_movements (id, return_line_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT return_cost_allocations_movement_fk FOREIGN KEY (movement_id, product_id, returned_quantity, product_sequence)
                    REFERENCES stock_movements (id, product_id, quantity, product_sequence) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT return_cost_allocations_return_line_fk FOREIGN KEY (return_line_id, sale_line_id, return_condition, returned_quantity)
                    REFERENCES return_lines (id, sale_line_id, condition, quantity) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT return_cost_allocations_issue_allocation_fk FOREIGN KEY (issue_allocation_id, sale_line_id, product_id)
                    REFERENCES issue_cost_allocations (id, sale_line_id, product_id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE return_cost_allocation_projections (
                allocation_id bigint PRIMARY KEY CONSTRAINT return_cost_allocation_projections_allocation_fk REFERENCES return_cost_allocations (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                current_status text NOT NULL CONSTRAINT return_cost_allocation_projections_current_status_allowed CHECK (current_status IN ('KNOWN', 'UNKNOWN')),
                current_amount numeric(21,6) NULL CONSTRAINT return_cost_allocation_projections_current_amount_range CHECK (current_amount BETWEEN 0 AND 999999999999999.999999),
                publication_no bigint NOT NULL CONSTRAINT return_cost_allocation_projections_publication_no_positive CHECK (publication_no >= 1),
                updated_at timestamptz NOT NULL,
                CONSTRAINT return_cost_allocation_projections_status_amount CHECK ((current_status = 'KNOWN') = (current_amount IS NOT NULL))
            )
            SQL);

        // The keys that go both ways: each follows both of its tables, deferred to the commit.
        $database->statement(<<<'SQL'
            ALTER TABLE return_lines ADD CONSTRAINT return_lines_movement_fk
                FOREIGN KEY (movement_presence) REFERENCES stock_movements (return_line_id) DEFERRABLE INITIALLY DEFERRED
            SQL);
        $database->statement(<<<'SQL'
            ALTER TABLE return_cost_allocations ADD CONSTRAINT return_cost_allocations_projection_fk
                FOREIGN KEY (id) REFERENCES return_cost_allocation_projections (allocation_id) DEFERRABLE INITIALLY DEFERRED
            SQL);

        // EXC-44: the keys of stock_movements that target the new tables.
        $database->statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_return_line_fk
                FOREIGN KEY (return_line_id, return_condition, product_id, line_quantity)
                REFERENCES return_lines (id, condition, product_id, quantity) ON DELETE RESTRICT ON UPDATE RESTRICT
            SQL);
        $database->statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_return_allocation_presence_fk
                FOREIGN KEY (return_allocation_presence) REFERENCES return_cost_allocations (movement_id) DEFERRABLE INITIALLY DEFERRED
            SQL);

        // SCHEMA section 11: the corrections of a Sale and by posting date, the drawer-funded refunds of a
        // session for its expected cash (TASK-034), the returns linked to an issue allocation for the
        // replay, and every foreign-key column that a key above does not lead. A refund names a session
        // only when it is drawer-funded (refunds_one_source), so the partial index holds every one.
        $database->statement('CREATE INDEX sale_corrections_sale_id_index ON sale_corrections (sale_id)');
        $database->statement('CREATE INDEX sale_corrections_business_date_index ON sale_corrections (business_date)');
        $database->statement('CREATE INDEX sale_corrections_actor_user_id_index ON sale_corrections (actor_user_id)');
        $database->statement('CREATE INDEX refunds_sale_id_index ON refunds (sale_id)');
        $database->statement("CREATE INDEX refunds_drawer_session_index ON refunds (cash_session_id) WHERE funding_source = 'DRAWER'");
        $database->statement('CREATE INDEX refund_lines_sale_line_id_index ON refund_lines (sale_line_id)');
        $database->statement('CREATE INDEX return_lines_sale_line_id_index ON return_lines (sale_line_id)');
        $database->statement('CREATE INDEX return_lines_movement_presence_index ON return_lines (movement_presence)');
        $database->statement('CREATE INDEX return_cost_allocations_issue_allocation_id_index ON return_cost_allocations (issue_allocation_id)');
        $database->statement('CREATE INDEX stock_movements_return_allocation_presence_index ON stock_movements (return_allocation_presence)');

        $database->statement('GRANT SELECT, INSERT ON TABLE sale_corrections, refunds, refund_lines, return_lines, return_cost_allocations TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT, UPDATE (current_status, current_amount, publication_no, updated_at) ON TABLE return_cost_allocation_projections TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE sale_corrections, refunds, refund_lines, return_lines, return_cost_allocations, return_cost_allocation_projections TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE sale_corrections_id_seq, refunds_id_seq, refund_lines_id_seq, return_lines_id_seq, return_cost_allocations_id_seq TO '.Identifier::quote($roles->backup));
    }
};
