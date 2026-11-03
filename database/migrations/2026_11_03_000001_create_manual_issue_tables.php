<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-023: the manual issue and the issue-cost allocation (SCHEMA sections 5.7 and 5.8;
 * ADR-008 sections 1 and 2; D-060; BR-INV-18 to BR-INV-22), with their checks, keys, indexes
 * and grants (SCHEMA sections 10 and 11, database/privileges.php):
 *
 * - manual_issues and manual_issue_lines (class F): an issue exists only once it is confirmed,
 *   with its mandatory reason of BR-INV-19; a line posts exactly one MANUAL_ISSUE.
 * - issue_cost_allocations (class F): the allocation fact of an issue movement, KNOWN with its
 *   amount or UNKNOWN with none, as it was computed at commit, never changed (G-13).
 * - issue_cost_allocation_projections (class P): the current value of each allocation, inserted
 *   equal to its fact (G-14), afterwards written only by a replay publication (TASK-025).
 *
 * It completes two keys of stock_movements (EXECUTION_CONTEXT EXC-44): its manual issue line,
 * with the line's product and quantity, and its issue presence key, deferred, so that an issue
 * movement without its allocation cannot commit (BR-INV-08). The allocation's key to its
 * movement's SaleLine (`sale_line_id` → stock_movements (id, sale_line_id)) is created here; its
 * key to sale_lines is TASK-028's, and until then no application code writes the column.
 *
 * A line and its movement reference each other, and so do an allocation and its projection,
 * and a movement and its allocation, so each deferred key follows both of its tables.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a second, on four
 * new, empty tables and two new keys of stock_movements, which holds no MANUAL_ISSUE and no issue
 * presence yet. Lock impact: the keys to products, users, command_identities and stock_movements
 * take a SHARE ROW EXCLUSIVE lock on those tables, held to the end of the migration's
 * transaction, and validating the two keys of stock_movements scans it once under that lock:
 * every posting waits that long. Verification (REL-20): db:check-privileges passes;
 * tests/Feature/Inventory/IssueTablesTest.php proves the constraint, deferred, privilege and
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
            CREATE TABLE manual_issues (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                reason_code text NOT NULL CONSTRAINT manual_issues_reason_code_allowed CHECK (reason_code IN ('DAMAGED', 'INTERNAL_USE', 'LOST', 'SAMPLE', 'OTHER')),
                note text NULL CONSTRAINT manual_issues_note_length CHECK (char_length(note) BETWEEN 1 AND 1000),
                line_count smallint NOT NULL CONSTRAINT manual_issues_line_count_range CHECK (line_count BETWEEN 1 AND 200),
                confirmed_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT manual_issues_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT manual_issues_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT manual_issues_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT manual_issues_command_unique UNIQUE (command_id)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE manual_issue_lines (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                manual_issue_id bigint NOT NULL CONSTRAINT manual_issue_lines_issue_fk REFERENCES manual_issues (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                line_no smallint NOT NULL CONSTRAINT manual_issue_lines_line_no_range CHECK (line_no BETWEEN 1 AND 200),
                product_id bigint NOT NULL CONSTRAINT manual_issue_lines_product_fk REFERENCES products (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                quantity numeric(13,3) NOT NULL CONSTRAINT manual_issue_lines_quantity_range CHECK (quantity > 0 AND quantity <= 99999999.999),
                CONSTRAINT manual_issue_lines_issue_line_unique UNIQUE (manual_issue_id, line_no),
                CONSTRAINT manual_issue_lines_id_product_quantity_unique UNIQUE (id, product_id, quantity)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE issue_cost_allocations (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                movement_id bigint NOT NULL,
                product_id bigint NOT NULL,
                issue_type text NOT NULL CONSTRAINT issue_cost_allocations_issue_type_allowed CHECK (issue_type IN ('SALE_ISSUE', 'MANUAL_ISSUE', 'ADJUSTMENT')),
                movement_quantity numeric(13,3) NOT NULL CONSTRAINT issue_cost_allocations_movement_quantity_range CHECK (movement_quantity < 0 AND movement_quantity >= -99999999.999),
                product_sequence bigint NOT NULL,
                issued_quantity numeric(13,3) NOT NULL,
                sale_line_id bigint NULL,
                at_commit_status text NOT NULL CONSTRAINT issue_cost_allocations_at_commit_status_allowed CHECK (at_commit_status IN ('KNOWN', 'UNKNOWN')),
                at_commit_amount numeric(21,6) NULL CONSTRAINT issue_cost_allocations_at_commit_amount_range CHECK (at_commit_amount BETWEEN 0 AND 999999999999999.999999),
                based_on_publication_no bigint NOT NULL CONSTRAINT issue_cost_allocations_based_on_publication_positive CHECK (based_on_publication_no >= 1),
                recorded_at timestamptz NOT NULL,
                CONSTRAINT issue_cost_allocations_issued_quantity CHECK (issued_quantity = -movement_quantity),
                CONSTRAINT issue_cost_allocations_sale_line CHECK ((issue_type = 'SALE_ISSUE') = (sale_line_id IS NOT NULL)),
                CONSTRAINT issue_cost_allocations_status_amount CHECK ((at_commit_status = 'KNOWN') = (at_commit_amount IS NOT NULL)),
                CONSTRAINT issue_cost_allocations_movement_unique UNIQUE (movement_id),
                CONSTRAINT issue_cost_allocations_id_type_unique UNIQUE (id, issue_type),
                CONSTRAINT issue_cost_allocations_id_sale_line_product_unique UNIQUE (id, sale_line_id, product_id),
                CONSTRAINT issue_cost_allocations_movement_fk FOREIGN KEY (movement_id, product_id, issue_type, movement_quantity, product_sequence)
                    REFERENCES stock_movements (id, product_id, movement_type, quantity, product_sequence) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT issue_cost_allocations_movement_sale_line_fk FOREIGN KEY (movement_id, sale_line_id)
                    REFERENCES stock_movements (id, sale_line_id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE issue_cost_allocation_projections (
                allocation_id bigint PRIMARY KEY,
                issue_type text NOT NULL,
                current_status text NOT NULL CONSTRAINT issue_cost_allocation_projections_current_status_allowed CHECK (current_status IN ('KNOWN', 'UNKNOWN', 'CANCELLED')),
                current_amount numeric(21,6) NULL CONSTRAINT issue_cost_allocation_projections_current_amount_range CHECK (current_amount BETWEEN 0 AND 999999999999999.999999),
                publication_no bigint NOT NULL CONSTRAINT issue_cost_allocation_projections_publication_no_positive CHECK (publication_no >= 1),
                updated_at timestamptz NOT NULL,
                CONSTRAINT issue_cost_allocation_projections_cancelled_manual CHECK (current_status <> 'CANCELLED' OR issue_type = 'MANUAL_ISSUE'),
                CONSTRAINT issue_cost_allocation_projections_status_amount CHECK ((current_status = 'KNOWN') = (current_amount IS NOT NULL)),
                CONSTRAINT issue_cost_allocation_projections_allocation_fk FOREIGN KEY (allocation_id, issue_type)
                    REFERENCES issue_cost_allocations (id, issue_type) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        // The keys that go both ways: each follows both of its tables, deferred to the commit.
        $database->statement(<<<'SQL'
            ALTER TABLE manual_issue_lines ADD CONSTRAINT manual_issue_lines_movement_fk
                FOREIGN KEY (id) REFERENCES stock_movements (manual_issue_line_id) DEFERRABLE INITIALLY DEFERRED
            SQL);
        $database->statement(<<<'SQL'
            ALTER TABLE issue_cost_allocations ADD CONSTRAINT issue_cost_allocations_projection_fk
                FOREIGN KEY (id) REFERENCES issue_cost_allocation_projections (allocation_id) DEFERRABLE INITIALLY DEFERRED
            SQL);

        // The keys of stock_movements that target the new tables (EXC-44).
        $database->statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_manual_issue_line_fk
                FOREIGN KEY (manual_issue_line_id, product_id, line_quantity)
                REFERENCES manual_issue_lines (id, product_id, quantity) ON DELETE RESTRICT ON UPDATE RESTRICT
            SQL);
        $database->statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_issue_allocation_presence_fk
                FOREIGN KEY (issue_allocation_presence) REFERENCES issue_cost_allocations (movement_id) DEFERRABLE INITIALLY DEFERRED
            SQL);

        // SCHEMA section 11: every foreign-key column that a key above does not lead.
        $database->statement('CREATE INDEX manual_issues_actor_user_id_index ON manual_issues (actor_user_id)');
        $database->statement('CREATE INDEX manual_issue_lines_product_id_index ON manual_issue_lines (product_id)');
        $database->statement('CREATE INDEX stock_movements_issue_allocation_presence_index ON stock_movements (issue_allocation_presence)');

        $database->statement('GRANT SELECT, INSERT ON TABLE manual_issues, manual_issue_lines, issue_cost_allocations TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT, UPDATE (current_status, current_amount, publication_no, updated_at) ON TABLE issue_cost_allocation_projections TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE manual_issues, manual_issue_lines, issue_cost_allocations, issue_cost_allocation_projections TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE manual_issues_id_seq, manual_issue_lines_id_seq, issue_cost_allocations_id_seq TO '.Identifier::quote($roles->backup));
    }
};
