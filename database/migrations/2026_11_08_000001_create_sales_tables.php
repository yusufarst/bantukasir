<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-028: the Sale and its records, in the Sales module (SCHEMA sections 5.9 and 6, SCH-20 to
 * SCH-28; BR-SALE-01 to BR-SALE-09, BR-PAY-01 to BR-PAY-07, BR-DOC-01; ADR-008 sections 1 and 2;
 * D-036, D-062, D-067, D-077), with their checks, keys, indexes and grants (SCHEMA sections 10 and
 * 11, database/privileges.php):
 *
 * - sales (class F): one per command, its number held by a check to be exactly its business date
 *   and its counter (SCH-22), both unique (SCH-24); a Sale belongs to an OPEN session of its actor,
 *   whose key ties the actor to the session's responsible user (BR-SALE-02; G-04 holds OPEN under
 *   the session lock); its total, its line count and the frozen name of its cashier (SCHEMA
 *   section 3.5). It has no status: no order, booking or pending Sale exists (BR-SALE-01).
 * - sale_lines (class F): one line per product (D-067), each priced by a real revision of its own
 *   product at that revision's amount (BR-SALE-05), its total rounded half-up once (BR-SALE-06).
 * - payments (class F): exactly one per Sale, CASH or BANK_TRANSFER, its applied amount the Sale
 *   total (BR-PAY-01 to BR-PAY-03). No provider, fee or pending column exists (BR-PAY-07).
 * - receipt_snapshots (class F): one per Sale, never changed, its number, total, session, cashier,
 *   method and cash amounts tied to their records by keys; the shop identity by reference to the
 *   immutable BusinessProfile revision, with its logo, and the template version (BR-DOC-01, G-17).
 * - sale_correction_totals and sale_line_correction_totals (class P): the running totals of
 *   BR-RFD-03, with their bounds as checks; the first is written with every Sale, as the guard
 *   row of its later corrections (SCHEMA section 7), the second with a line's first refund or
 *   return (TASK-033).
 * - sale_number_seq: one installation-wide counter, CACHE 1 and NO CYCLE (SCH-20, SCH-23); the
 *   runtime role has USAGE only (SCH-27, SCH-44).
 *
 * A Sale and its Payment, receipt and running total, and a line and its SALE_ISSUE, reference
 * each other, so each deferred key follows both of its tables: a transaction that omits one of
 * them cannot commit (BR-SALE-17).
 *
 * EXC-44: the keys of earlier tables that target sale_lines, which TASK-021 and TASK-023 left for
 * this Task: stock_movements (sale_line_id, product_id, line_quantity), so that a SALE_ISSUE moves
 * exactly its line's product and quantity, and issue_cost_allocations (sale_line_id,
 * issued_quantity), so that a Sale's allocation issues its line's quantity.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a second, on six new,
 * empty tables, a new sequence and two new keys of stock_movements and issue_cost_allocations,
 * which hold no SALE_ISSUE yet. Lock impact: the keys to cash_sessions, command_identities,
 * selling_price_revisions, business_profile_revisions and stored_files, and the two keys added to
 * stock_movements and issue_cost_allocations, take a SHARE ROW EXCLUSIVE lock on those tables, held
 * to the end of the migration's transaction, and validating the two added keys scans
 * stock_movements and issue_cost_allocations once under that lock: every posting and every
 * session open waits that long. Verification (REL-20): db:check-privileges passes;
 * tests/Feature/Sales/SalesTablesTest.php proves the constraint, deferred, privilege, retention
 * and numbering families as the runtime role.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement('CREATE SEQUENCE sale_number_seq AS bigint START 1 INCREMENT 1 NO CYCLE CACHE 1');

        $database->statement(<<<'SQL'
            CREATE TABLE sales (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                sale_number text NOT NULL CONSTRAINT sales_number_format CHECK (sale_number ~ '^[0-9]{8}-[0-9]{6,}$'),
                sale_number_seq bigint NOT NULL CONSTRAINT sales_number_seq_positive CHECK (sale_number_seq >= 1),
                business_date date NOT NULL,
                committed_at timestamptz NOT NULL,
                cash_session_id bigint NOT NULL,
                actor_user_id bigint NOT NULL,
                actor_display_name text NOT NULL CONSTRAINT sales_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                total_amount bigint NOT NULL CONSTRAINT sales_total_amount_range CHECK (total_amount BETWEEN 0 AND 999999999999),
                line_count smallint NOT NULL CONSTRAINT sales_line_count_range CHECK (line_count BETWEEN 1 AND 200),
                customer_name text NULL CONSTRAINT sales_customer_name_length CHECK (char_length(customer_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT sales_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT sales_number_matches CHECK (sale_number = (EXTRACT(YEAR FROM business_date) * 10000
                    + EXTRACT(MONTH FROM business_date) * 100
                    + EXTRACT(DAY FROM business_date))::integer::text
                    || '-' || lpad(sale_number_seq::text, greatest(6, char_length(sale_number_seq::text)), '0')),
                CONSTRAINT sales_cash_session_actor_fk FOREIGN KEY (cash_session_id, actor_user_id)
                    REFERENCES cash_sessions (id, responsible_user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT sales_number_unique UNIQUE (sale_number),
                CONSTRAINT sales_number_seq_unique UNIQUE (sale_number_seq),
                CONSTRAINT sales_command_unique UNIQUE (command_id),
                CONSTRAINT sales_id_total_unique UNIQUE (id, total_amount),
                CONSTRAINT sales_receipt_target_unique UNIQUE (id, sale_number, total_amount, cash_session_id, actor_display_name)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE sale_lines (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                sale_id bigint NOT NULL CONSTRAINT sale_lines_sale_fk REFERENCES sales (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                line_no smallint NOT NULL CONSTRAINT sale_lines_line_no_range CHECK (line_no BETWEEN 1 AND 200),
                product_id bigint NOT NULL,
                selling_price_revision_id bigint NOT NULL,
                unit_price bigint NOT NULL CONSTRAINT sale_lines_unit_price_range CHECK (unit_price BETWEEN 0 AND 999999999999),
                quantity numeric(13,3) NOT NULL CONSTRAINT sale_lines_quantity_range CHECK (quantity > 0 AND quantity <= 99999999.999),
                line_total bigint NOT NULL CONSTRAINT sale_lines_line_total_range CHECK (line_total BETWEEN 0 AND 999999999999),
                CONSTRAINT sale_lines_line_total_rounded CHECK (line_total = round(quantity * unit_price)),
                CONSTRAINT sale_lines_price_revision_fk FOREIGN KEY (selling_price_revision_id, product_id, unit_price)
                    REFERENCES selling_price_revisions (id, product_id, amount) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT sale_lines_sale_line_unique UNIQUE (sale_id, line_no),
                CONSTRAINT sale_lines_sale_product_unique UNIQUE (sale_id, product_id),
                CONSTRAINT sale_lines_id_quantity_unique UNIQUE (id, quantity),
                CONSTRAINT sale_lines_id_total_quantity_unique UNIQUE (id, line_total, quantity),
                CONSTRAINT sale_lines_id_sale_unique UNIQUE (id, sale_id),
                CONSTRAINT sale_lines_id_sale_product_unique UNIQUE (id, sale_id, product_id),
                CONSTRAINT sale_lines_id_product_quantity_unique UNIQUE (id, product_id, quantity)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE payments (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                sale_id bigint NOT NULL,
                method text NOT NULL CONSTRAINT payments_method_allowed CHECK (method IN ('CASH', 'BANK_TRANSFER')),
                applied_amount bigint NOT NULL CONSTRAINT payments_applied_amount_range CHECK (applied_amount BETWEEN 0 AND 999999999999),
                tendered_amount bigint NULL CONSTRAINT payments_tendered_amount_range CHECK (tendered_amount BETWEEN 0 AND 999999999999),
                change_amount bigint NULL CONSTRAINT payments_change_amount_range CHECK (change_amount BETWEEN 0 AND 999999999999),
                reference text NULL CONSTRAINT payments_reference_length CHECK (char_length(reference) BETWEEN 1 AND 100),
                CONSTRAINT payments_method_amounts CHECK (
                    (method = 'CASH' AND tendered_amount IS NOT NULL AND tendered_amount >= applied_amount
                        AND change_amount IS NOT NULL AND change_amount = tendered_amount - applied_amount
                        AND reference IS NULL)
                    OR (method = 'BANK_TRANSFER' AND tendered_amount IS NULL AND change_amount IS NULL)
                ),
                CONSTRAINT payments_sale_total_fk FOREIGN KEY (sale_id, applied_amount)
                    REFERENCES sales (id, total_amount) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT payments_sale_unique UNIQUE (sale_id),
                CONSTRAINT payments_sale_method_unique UNIQUE (sale_id, method),
                CONSTRAINT payments_sale_method_amounts_unique UNIQUE (sale_id, method, tendered_amount, change_amount)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE receipt_snapshots (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                sale_id bigint NOT NULL,
                sale_number text NOT NULL,
                total_amount bigint NOT NULL,
                cash_session_id bigint NOT NULL,
                cashier_display_name text NOT NULL CONSTRAINT receipt_snapshots_cashier_display_name_length CHECK (char_length(cashier_display_name) BETWEEN 1 AND 100),
                business_profile_revision_id bigint NOT NULL CONSTRAINT receipt_snapshots_profile_revision_fk REFERENCES business_profile_revisions (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                logo_file_id bigint NULL CONSTRAINT receipt_snapshots_logo_file_fk REFERENCES stored_files (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                template_version text NOT NULL CONSTRAINT receipt_snapshots_template_version_format CHECK (template_version ~ '^[a-z0-9._-]{1,64}$'),
                issued_at timestamptz NOT NULL,
                cash_session_number text NOT NULL,
                payment_method text NOT NULL,
                tendered_amount bigint NULL,
                change_amount bigint NULL,
                customer_name text NULL CONSTRAINT receipt_snapshots_customer_name_length CHECK (char_length(customer_name) BETWEEN 1 AND 100),
                lines jsonb NOT NULL CONSTRAINT receipt_snapshots_lines_array CHECK (jsonb_typeof(lines) = 'array'),
                CONSTRAINT receipt_snapshots_cash_amounts CHECK (
                    ((payment_method = 'CASH') = (tendered_amount IS NOT NULL))
                    AND ((tendered_amount IS NULL) = (change_amount IS NULL))
                ),
                CONSTRAINT receipt_snapshots_sale_fk FOREIGN KEY (sale_id, sale_number, total_amount, cash_session_id, cashier_display_name)
                    REFERENCES sales (id, sale_number, total_amount, cash_session_id, actor_display_name) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT receipt_snapshots_session_number_fk FOREIGN KEY (cash_session_id, cash_session_number)
                    REFERENCES cash_sessions (id, session_number) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT receipt_snapshots_payment_fk FOREIGN KEY (sale_id, payment_method)
                    REFERENCES payments (sale_id, method) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT receipt_snapshots_cash_payment_fk FOREIGN KEY (sale_id, payment_method, tendered_amount, change_amount)
                    REFERENCES payments (sale_id, method, tendered_amount, change_amount) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT receipt_snapshots_sale_unique UNIQUE (sale_id)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE sale_correction_totals (
                sale_id bigint PRIMARY KEY,
                sale_total bigint NOT NULL,
                refunded_amount bigint NOT NULL,
                updated_at timestamptz NOT NULL,
                CONSTRAINT sale_correction_totals_refunded_range CHECK (refunded_amount BETWEEN 0 AND sale_total),
                CONSTRAINT sale_correction_totals_sale_fk FOREIGN KEY (sale_id, sale_total)
                    REFERENCES sales (id, total_amount) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE sale_line_correction_totals (
                sale_line_id bigint PRIMARY KEY,
                line_total bigint NOT NULL,
                issued_quantity numeric(13,3) NOT NULL,
                refunded_amount bigint NOT NULL,
                returned_quantity numeric(13,3) NOT NULL,
                updated_at timestamptz NOT NULL,
                CONSTRAINT sale_line_correction_totals_refunded_range CHECK (refunded_amount BETWEEN 0 AND line_total),
                CONSTRAINT sale_line_correction_totals_returned_range CHECK (returned_quantity BETWEEN 0 AND issued_quantity),
                CONSTRAINT sale_line_correction_totals_line_fk FOREIGN KEY (sale_line_id, line_total, issued_quantity)
                    REFERENCES sale_lines (id, line_total, quantity) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        // The keys that go both ways: each follows both of its tables, deferred to the commit.
        $database->statement(<<<'SQL'
            ALTER TABLE sales ADD CONSTRAINT sales_payment_fk
                FOREIGN KEY (id) REFERENCES payments (sale_id) DEFERRABLE INITIALLY DEFERRED
            SQL);
        $database->statement(<<<'SQL'
            ALTER TABLE sales ADD CONSTRAINT sales_receipt_fk
                FOREIGN KEY (id) REFERENCES receipt_snapshots (sale_id) DEFERRABLE INITIALLY DEFERRED
            SQL);
        $database->statement(<<<'SQL'
            ALTER TABLE sales ADD CONSTRAINT sales_correction_totals_fk
                FOREIGN KEY (id) REFERENCES sale_correction_totals (sale_id) DEFERRABLE INITIALLY DEFERRED
            SQL);
        $database->statement(<<<'SQL'
            ALTER TABLE sale_lines ADD CONSTRAINT sale_lines_movement_fk
                FOREIGN KEY (id) REFERENCES stock_movements (sale_line_id) DEFERRABLE INITIALLY DEFERRED
            SQL);

        // EXC-44: the keys of stock_movements and issue_cost_allocations that target sale_lines.
        $database->statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_sale_line_fk
                FOREIGN KEY (sale_line_id, product_id, line_quantity)
                REFERENCES sale_lines (id, product_id, quantity) ON DELETE RESTRICT ON UPDATE RESTRICT
            SQL);
        $database->statement(<<<'SQL'
            ALTER TABLE issue_cost_allocations ADD CONSTRAINT issue_cost_allocations_sale_line_fk
                FOREIGN KEY (sale_line_id, issued_quantity)
                REFERENCES sale_lines (id, quantity) ON DELETE RESTRICT ON UPDATE RESTRICT
            SQL);

        // SCHEMA section 11: the Sales' orders and filters, the lines of a Sale, and every
        // foreign-key column that a key above does not lead.
        $database->statement('CREATE INDEX sales_committed_at_id_index ON sales (committed_at, id)');
        $database->statement('CREATE INDEX sales_cash_session_id_index ON sales (cash_session_id)');
        $database->statement('CREATE INDEX sales_actor_committed_at_id_index ON sales (actor_user_id, committed_at, id)');
        $database->statement('CREATE INDEX sales_business_date_index ON sales (business_date)');
        $database->statement('CREATE INDEX sale_lines_sale_id_index ON sale_lines (sale_id)');
        $database->statement('CREATE INDEX sale_lines_price_revision_index ON sale_lines (selling_price_revision_id)');
        $database->statement('CREATE INDEX receipt_snapshots_profile_revision_index ON receipt_snapshots (business_profile_revision_id)');
        $database->statement('CREATE INDEX receipt_snapshots_logo_file_id_index ON receipt_snapshots (logo_file_id)');
        $database->statement('CREATE INDEX receipt_snapshots_cash_session_id_index ON receipt_snapshots (cash_session_id)');
        $database->statement('CREATE INDEX issue_cost_allocations_sale_line_id_index ON issue_cost_allocations (sale_line_id)');

        $database->statement('GRANT SELECT, INSERT ON TABLE sales, sale_lines, payments, receipt_snapshots TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT, UPDATE (refunded_amount, updated_at) ON TABLE sale_correction_totals TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT, UPDATE (refunded_amount, returned_quantity, updated_at) ON TABLE sale_line_correction_totals TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT USAGE ON SEQUENCE sale_number_seq TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE sales, sale_lines, payments, receipt_snapshots, sale_correction_totals, sale_line_correction_totals TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE sales_id_seq, sale_lines_id_seq, payments_id_seq, receipt_snapshots_id_seq, sale_number_seq TO '.Identifier::quote($roles->backup));
    }
};
