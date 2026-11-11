<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-030: the close of a cash session and its immutable report, in the Cash module (SCHEMA section
 * 5.10; BR-CASH-12 to BR-CASH-20; G-05, G-17, G-19), with their checks, keys, indexes and grants
 * (SCHEMA sections 10 and 11, database/privileges.php):
 *
 * - cash_session_closes (class F): one per session, its key the session's; the session's status
 *   CLOSED through the composite key to cash_sessions (id, status), ON UPDATE RESTRICT, so that a
 *   CLOSED session can never become OPEN again (BR-CASH-19); the closed time, the kind OWN or
 *   OWNER_ASSISTED, the close actor with the name frozen (SCHEMA section 3.5), a reason that an
 *   Owner-assisted close must have, the cutoff (the activity version counted against), the
 *   physical and expected cash of zero or more and the variance, held by a check to be their
 *   difference (BR-CASH-12), and the command, unique: one close per session (BR-CASH-17). Its
 *   deferred key to cash_session_reports: a close has its report.
 * - cash_session_reports (class F): the CashSessionReportSnapshot of BR-CASH-20, one per session
 *   and numbered by its session's number; the session's number, opening cash, opened time and
 *   responsible user's name tied by a composite key to the session, and the close as it was
 *   recorded tied by a composite key to the close (G-17); the shop identity by reference to the
 *   immutable BusinessProfile revision, with its logo, and the template version; the register's
 *   code and label frozen; the completed Sales, the applied CASH and BANK_TRANSFER separately and
 *   the drawer-funded refunds (populated from TASK-034), with expected cash and the variance held
 *   by checks to be their arithmetic (G-05); the source identities; no cost, margin or profit.
 *
 * EXC-44: cash_sessions.close_presence → cash_session_closes, the deferred presence key that
 * TASK-027 left for this Task: a session is CLOSED exactly when its close exists, so a transaction
 * that writes CLOSED without a close, or a close without its report, cannot commit (BR-CASH-17).
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a second, on two new,
 * empty tables and a key of cash_sessions whose column is null on every row, since no code before
 * this Task writes a CLOSED status. Lock impact: the keys to cash_sessions, users,
 * command_identities, business_profile_revisions and stored_files take a SHARE ROW EXCLUSIVE lock
 * on those tables, held to the end of the migration's transaction, and validating the key added to
 * cash_sessions scans it once under that lock: every session open, checkout and close waits that
 * long. Verification (REL-20): db:check-privileges passes; tests/Feature/Cash/CashCloseTablesTest.php
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
            CREATE TABLE cash_session_closes (
                session_id bigint PRIMARY KEY,
                session_status text NOT NULL CONSTRAINT cash_session_closes_status_closed CHECK (session_status = 'CLOSED'),
                closed_at timestamptz NOT NULL,
                close_kind text NOT NULL CONSTRAINT cash_session_closes_kind_allowed CHECK (close_kind IN ('OWN', 'OWNER_ASSISTED')),
                close_actor_user_id bigint NOT NULL CONSTRAINT cash_session_closes_actor_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                close_actor_display_name text NOT NULL CONSTRAINT cash_session_closes_actor_display_name_length CHECK (char_length(close_actor_display_name) BETWEEN 1 AND 100),
                close_reason text NULL CONSTRAINT cash_session_closes_reason_length CHECK (char_length(close_reason) BETWEEN 1 AND 1000),
                activity_version_at_close bigint NOT NULL CONSTRAINT cash_session_closes_activity_version_range CHECK (activity_version_at_close >= 0),
                physical_cash bigint NOT NULL CONSTRAINT cash_session_closes_physical_cash_range CHECK (physical_cash BETWEEN 0 AND 999999999999),
                expected_cash bigint NOT NULL CONSTRAINT cash_session_closes_expected_cash_range CHECK (expected_cash BETWEEN 0 AND 999999999999),
                variance bigint NOT NULL,
                command_id bigint NOT NULL CONSTRAINT cash_session_closes_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT cash_session_closes_variance_difference CHECK (variance = physical_cash - expected_cash),
                CONSTRAINT cash_session_closes_assisted_reason CHECK (close_kind <> 'OWNER_ASSISTED' OR close_reason IS NOT NULL),
                CONSTRAINT cash_session_closes_session_fk FOREIGN KEY (session_id, session_status)
                    REFERENCES cash_sessions (id, status) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT cash_session_closes_command_unique UNIQUE (command_id),
                CONSTRAINT cash_session_closes_report_target_unique UNIQUE (session_id, closed_at, activity_version_at_close, physical_cash, expected_cash, variance, close_actor_display_name)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE cash_session_reports (
                session_id bigint PRIMARY KEY,
                session_number text NOT NULL,
                opening_cash bigint NOT NULL,
                opened_at timestamptz NOT NULL,
                responsible_display_name text NOT NULL,
                business_profile_revision_id bigint NOT NULL CONSTRAINT cash_session_reports_profile_revision_fk REFERENCES business_profile_revisions (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                logo_file_id bigint NULL CONSTRAINT cash_session_reports_logo_file_fk REFERENCES stored_files (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                template_version text NOT NULL CONSTRAINT cash_session_reports_template_version_format CHECK (template_version ~ '^[a-z0-9._-]{1,64}$'),
                register_code text NOT NULL CONSTRAINT cash_session_reports_register_code_length CHECK (char_length(register_code) BETWEEN 1 AND 32),
                register_label text NOT NULL CONSTRAINT cash_session_reports_register_label_length CHECK (char_length(register_label) BETWEEN 1 AND 100),
                closed_at timestamptz NOT NULL,
                cutoff_activity_version bigint NOT NULL,
                physical_cash bigint NOT NULL,
                expected_cash bigint NOT NULL,
                variance bigint NOT NULL,
                close_actor_display_name text NOT NULL,
                completed_sale_count bigint NOT NULL CONSTRAINT cash_session_reports_sale_count_range CHECK (completed_sale_count >= 0),
                completed_sale_value bigint NOT NULL CONSTRAINT cash_session_reports_sale_value_range CHECK (completed_sale_value >= 0),
                cash_applied bigint NOT NULL CONSTRAINT cash_session_reports_cash_applied_range CHECK (cash_applied >= 0),
                bank_transfer_applied bigint NOT NULL CONSTRAINT cash_session_reports_bank_transfer_applied_range CHECK (bank_transfer_applied >= 0),
                drawer_refund_total bigint NOT NULL CONSTRAINT cash_session_reports_drawer_refund_total_range CHECK (drawer_refund_total >= 0),
                drawer_refunds jsonb NOT NULL CONSTRAINT cash_session_reports_drawer_refunds_array CHECK (jsonb_typeof(drawer_refunds) = 'array'),
                source_ids jsonb NOT NULL CONSTRAINT cash_session_reports_source_ids_object CHECK (jsonb_typeof(source_ids) = 'object'),
                close_reason text NULL CONSTRAINT cash_session_reports_close_reason_length CHECK (char_length(close_reason) BETWEEN 1 AND 1000),
                notes text NULL CONSTRAINT cash_session_reports_notes_length CHECK (char_length(notes) BETWEEN 1 AND 1000),
                CONSTRAINT cash_session_reports_expected_cash_derived CHECK (expected_cash = opening_cash + cash_applied - drawer_refund_total),
                CONSTRAINT cash_session_reports_variance_difference CHECK (variance = physical_cash - expected_cash),
                CONSTRAINT cash_session_reports_session_fk FOREIGN KEY (session_id, session_number, opening_cash, opened_at, responsible_display_name)
                    REFERENCES cash_sessions (id, session_number, opening_cash, opened_at, responsible_display_name) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT cash_session_reports_close_fk FOREIGN KEY (session_id, closed_at, cutoff_activity_version, physical_cash, expected_cash, variance, close_actor_display_name)
                    REFERENCES cash_session_closes (session_id, closed_at, activity_version_at_close, physical_cash, expected_cash, variance, close_actor_display_name) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        // The keys that go both ways: a close has its report, and a CLOSED session its close, each
        // deferred to the commit (BR-CASH-17; EXC-44 for the key of cash_sessions).
        $database->statement(<<<'SQL'
            ALTER TABLE cash_session_closes ADD CONSTRAINT cash_session_closes_report_fk
                FOREIGN KEY (session_id) REFERENCES cash_session_reports (session_id) DEFERRABLE INITIALLY DEFERRED
            SQL);
        $database->statement(<<<'SQL'
            ALTER TABLE cash_sessions ADD CONSTRAINT cash_sessions_close_presence_fk
                FOREIGN KEY (close_presence) REFERENCES cash_session_closes (session_id) DEFERRABLE INITIALLY DEFERRED
            SQL);

        // SCHEMA section 11: every foreign-key column that a key above does not lead.
        $database->statement('CREATE INDEX cash_session_closes_actor_index ON cash_session_closes (close_actor_user_id)');
        $database->statement('CREATE INDEX cash_session_reports_profile_revision_index ON cash_session_reports (business_profile_revision_id)');
        $database->statement('CREATE INDEX cash_session_reports_logo_file_id_index ON cash_session_reports (logo_file_id)');

        $database->statement('GRANT SELECT, INSERT ON TABLE cash_session_closes, cash_session_reports TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE cash_session_closes, cash_session_reports TO '.Identifier::quote($roles->backup));
    }
};
