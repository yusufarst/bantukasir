<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-006: the tables of the Audit module (SCHEMA section 5.4) —
 * `audit_events` (class F) and `security_log_entries` (class L) — with their
 * checks, indexes and grants (SCHEMA section 10, database/privileges.php).
 *
 * - audit_events: the actor columns of SCHEMA section 3.4 with the frozen
 *   display name of section 3.5; command_id, product_id and cash_session_id
 *   are nullable and have no key yet: TASK-008, TASK-015 and TASK-027 add the
 *   keys to command_identities, products and cash_sessions when they create
 *   those tables (EXECUTION_CONTEXT EXC-44), and no code writes the columns
 *   before then. Insert-only for the runtime role (SEC-LOG-04).
 * - security_log_entries: the security log, written after the business
 *   transaction (SEC-LOG-03). Its retention of 365 days from occurred_at
 *   (SEC-LOG-05, D-088) is held by the database (OPEN_ITEMS T-23): row
 *   security is enabled on the table, with a permissive policy for reading,
 *   one for adding, and one for removing that admits only an entry older
 *   than 365 days — 8,760 hours, so that no session time zone can shorten it.
 *   The runtime role holds DELETE, and a removal of a younger entry matches no
 *   row and removes nothing. The policies name no role (PUBLIC), so they apply
 *   to the runtime and backup roles alike; the migration role owns the table
 *   and is not subject to them (row security is not forced). SCHEMA SCH-06
 *   and sections 5.4 and 10 record the mechanism and its effect on the backup
 *   dump (TASK-013).
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under
 * a second. Lock impact: none on an existing table. Verification (REL-20):
 * db:check-privileges passes, the row security and policies included;
 * tests/Feature/Audit/AuditTablesTest.php proves the constraint, privilege
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
            CREATE TABLE audit_events (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                occurred_at timestamptz NOT NULL,
                category text NOT NULL CONSTRAINT audit_events_category_allowed CHECK (category IN ('BUSINESS', 'SECURITY')),
                action text NOT NULL CONSTRAINT audit_events_action_format CHECK (action ~ '^[A-Z][A-Z0-9_]{0,63}$'),
                actor_kind text NOT NULL CONSTRAINT audit_events_actor_kind_allowed CHECK (actor_kind IN ('USER', 'OPERATOR_PROCEDURE', 'SCHEDULED_COMMAND')),
                actor_user_id bigint NULL CONSTRAINT audit_events_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_process text NULL CONSTRAINT audit_events_actor_process_format CHECK (actor_process ~ '^[a-z][a-z0-9_]{0,63}$'),
                actor_display_name text NOT NULL CONSTRAINT audit_events_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                operator_note text NULL CONSTRAINT audit_events_operator_note_length CHECK (char_length(operator_note) BETWEEN 1 AND 1000),
                command_id bigint NULL,
                subject_type text NULL CONSTRAINT audit_events_subject_type_format CHECK (subject_type ~ '^[a-z][a-z0-9_]{0,63}$'),
                subject_id bigint NULL,
                product_id bigint NULL,
                cash_session_id bigint NULL,
                reference text NULL CONSTRAINT audit_events_reference_length CHECK (char_length(reference) BETWEEN 1 AND 100),
                reason text NULL CONSTRAINT audit_events_reason_length CHECK (char_length(reason) BETWEEN 1 AND 1000),
                change_data jsonb NOT NULL,
                owner_only_data jsonb NULL,
                CONSTRAINT audit_events_actor CHECK (
                    (actor_kind = 'USER' AND actor_user_id IS NOT NULL AND actor_process IS NULL)
                    OR (actor_kind <> 'USER' AND actor_user_id IS NULL AND actor_process IS NOT NULL)
                ),
                CONSTRAINT audit_events_operator_note_actor CHECK (operator_note IS NULL OR actor_kind = 'OPERATOR_PROCEDURE'),
                CONSTRAINT audit_events_subject_pair CHECK ((subject_type IS NULL) = (subject_id IS NULL))
            )
            SQL);

        $database->statement('CREATE INDEX audit_events_occurred_at_id_index ON audit_events (occurred_at, id)');
        $database->statement('CREATE INDEX audit_events_actor_user_occurred_at_id_index ON audit_events (actor_user_id, occurred_at, id)');
        $database->statement('CREATE INDEX audit_events_product_occurred_at_id_index ON audit_events (product_id, occurred_at, id)');
        $database->statement('CREATE INDEX audit_events_cash_session_occurred_at_id_index ON audit_events (cash_session_id, occurred_at, id)');
        $database->statement('CREATE INDEX audit_events_reference_index ON audit_events (reference)');
        $database->statement('CREATE INDEX audit_events_command_id_index ON audit_events (command_id)');

        $database->statement(<<<'SQL'
            CREATE TABLE security_log_entries (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                occurred_at timestamptz NOT NULL,
                event_type text NOT NULL CONSTRAINT security_log_entries_event_type_allowed CHECK (event_type IN (
                    'SIGN_IN_SUCCEEDED', 'SIGN_IN_FAILED', 'THROTTLED', 'LINK_FAILED', 'PASSWORD_CONFIRMATION_FAILED',
                    'CSRF_FAILED', 'AUTHORIZATION_REFUSED', 'SESSION_REVOKED', 'OPERATOR_PROCEDURE_RUN'
                )),
                actor_kind text NOT NULL CONSTRAINT security_log_entries_actor_kind_allowed CHECK (actor_kind IN ('USER', 'ANONYMOUS', 'OPERATOR_PROCEDURE', 'SCHEDULED_COMMAND')),
                actor_user_id bigint NULL CONSTRAINT security_log_entries_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_process text NULL CONSTRAINT security_log_entries_actor_process_format CHECK (actor_process ~ '^[a-z][a-z0-9_]{0,63}$'),
                subject_user_id bigint NULL CONSTRAINT security_log_entries_subject_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                request_id text NOT NULL CONSTRAINT security_log_entries_request_id_length CHECK (char_length(request_id) BETWEEN 1 AND 64),
                route_name text NULL CONSTRAINT security_log_entries_route_name_length CHECK (char_length(route_name) BETWEEN 1 AND 200),
                outcome_code text NOT NULL CONSTRAINT security_log_entries_outcome_code_format CHECK (outcome_code ~ '^[A-Z][A-Z0-9_]{0,63}$'),
                client_address inet NULL,
                CONSTRAINT security_log_entries_actor CHECK (
                    (actor_kind = 'USER' AND actor_user_id IS NOT NULL AND actor_process IS NULL)
                    OR (actor_kind = 'ANONYMOUS' AND actor_user_id IS NULL AND actor_process IS NULL)
                    OR (actor_kind IN ('OPERATOR_PROCEDURE', 'SCHEDULED_COMMAND') AND actor_user_id IS NULL AND actor_process IS NOT NULL)
                )
            )
            SQL);

        $database->statement('CREATE INDEX security_log_entries_occurred_at_id_index ON security_log_entries (occurred_at, id)');
        $database->statement('CREATE INDEX security_log_entries_actor_user_id_index ON security_log_entries (actor_user_id)');
        $database->statement('CREATE INDEX security_log_entries_subject_user_id_index ON security_log_entries (subject_user_id)');

        // The retention of SEC-LOG-05 in the database (OPEN_ITEMS T-23).
        $database->statement('ALTER TABLE security_log_entries ENABLE ROW LEVEL SECURITY');
        $database->statement('CREATE POLICY security_log_entries_read ON security_log_entries AS PERMISSIVE FOR SELECT USING (true)');
        $database->statement('CREATE POLICY security_log_entries_add ON security_log_entries AS PERMISSIVE FOR INSERT WITH CHECK (true)');
        $database->statement('CREATE POLICY security_log_entries_retention ON security_log_entries AS PERMISSIVE FOR DELETE USING (occurred_at < now() - interval \'8760 hours\')');

        $database->statement('GRANT SELECT, INSERT ON TABLE audit_events TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT, DELETE ON TABLE security_log_entries TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE audit_events, security_log_entries TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE audit_events_id_seq, security_log_entries_id_seq TO '.Identifier::quote($roles->backup));
    }
};
