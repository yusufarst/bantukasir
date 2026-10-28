<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-008: the tables of the Commands module (SCHEMA section 5.3; ADR-007,
 * ADR-009) with their checks, keys, presence key, indexes and grants (SCHEMA
 * sections 10 and 11, database/privileges.php), and the key of an earlier
 * table that targets them (EXECUTION_CONTEXT EXC-44):
 *
 * - recovery_epoch (class S): the installation's one epoch, at least 128
 *   random bits; inserted by the bootstrap (TASK-010) and replaced only by the
 *   after-restore procedure (TASK-037).
 * - command_identities (class F): the identity of every enveloped command,
 *   the first write of its transaction, and the FENCE of a resolution. The
 *   pair (operation, intent) is unique, and so is (actor, operation, key) for
 *   a COMMAND; the presence key result_presence, a stored generated column
 *   equal to id for a COMMAND, refers to command_results with a deferred key,
 *   so that a COMMAND identity without its result cannot commit (SCHEMA
 *   section 3.7).
 * - command_results (class F): the durable result, references only; its key
 *   to command_identities (id, kind) with identity_kind fixed to COMMAND, so
 *   that a fence never has a result.
 * - command_request_receipts (class E): one row per enveloped request, written
 *   before the command runs and never with the payload; its outcome marked
 *   after the command or settled by a status check (D-089).
 * - stale_intent_reports and intent_reconciliations (class F): the requests
 *   of an earlier epoch and the Owner's reconciliation of each (TASK-037
 *   writes the reconciliations).
 * - audit_events.command_id → command_identities, the key that TASK-006 left
 *   for this Task; no row holds a command_id yet.
 *
 * The keys between command_identities and command_results go both ways, so
 * command_identities is created first and receives its presence key after
 * command_results exists.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under
 * a second. Lock impact: the key added to audit_events takes a SHARE ROW
 * EXCLUSIVE lock on that table while it checks the existing rows, none of
 * which holds a command_id; no other existing table is touched. Verification
 * (REL-20): db:check-privileges passes; tests/Feature/Commands/CommandTablesTest.php
 * proves the constraint, deferred, privilege and retention families as the
 * runtime role.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement(<<<'SQL'
            CREATE TABLE recovery_epoch (
                id smallint PRIMARY KEY CONSTRAINT recovery_epoch_single CHECK (id = 1),
                epoch bytea NOT NULL CONSTRAINT recovery_epoch_epoch_length CHECK (octet_length(epoch) >= 16),
                set_at timestamptz NOT NULL
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE command_identities (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                kind text NOT NULL CONSTRAINT command_identities_kind_allowed CHECK (kind IN ('COMMAND', 'FENCE')),
                operation text NOT NULL CONSTRAINT command_identities_operation_format CHECK (operation ~ '^[a-z][a-z0-9_.]{0,63}$'),
                operation_version smallint NULL,
                source_intent_id uuid NOT NULL,
                idempotency_key uuid NULL,
                actor_kind text NOT NULL CONSTRAINT command_identities_actor_kind_allowed CHECK (actor_kind IN ('USER', 'OPERATOR_PROCEDURE', 'SCHEDULED_COMMAND')),
                actor_user_id bigint NULL CONSTRAINT command_identities_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_process text NULL CONSTRAINT command_identities_actor_process_format CHECK (actor_process ~ '^[a-z][a-z0-9_]{0,63}$'),
                fingerprint bytea NULL,
                recovery_epoch bytea NOT NULL,
                received_at timestamptz NOT NULL,
                recorded_at timestamptz NOT NULL,
                result_presence bigint GENERATED ALWAYS AS (CASE WHEN kind = 'COMMAND' THEN id END) STORED,
                CONSTRAINT command_identities_actor CHECK (
                    (actor_kind = 'USER' AND actor_user_id IS NOT NULL AND actor_process IS NULL)
                    OR (actor_kind <> 'USER' AND actor_user_id IS NULL AND actor_process IS NOT NULL)
                ),
                CONSTRAINT command_identities_kind_shape CHECK (
                    (kind = 'COMMAND' AND operation_version IS NOT NULL AND idempotency_key IS NOT NULL
                                      AND fingerprint IS NOT NULL AND octet_length(fingerprint) = 32)
                    OR (kind = 'FENCE' AND actor_kind = 'USER' AND operation_version IS NULL
                                       AND idempotency_key IS NULL AND fingerprint IS NULL)
                ),
                CONSTRAINT command_identities_intent_unique UNIQUE (operation, source_intent_id),
                CONSTRAINT command_identities_id_kind_unique UNIQUE (id, kind)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE UNIQUE INDEX command_identities_user_key ON command_identities (actor_user_id, operation, idempotency_key)
                WHERE kind = 'COMMAND' AND actor_kind = 'USER'
            SQL);
        $database->statement(<<<'SQL'
            CREATE UNIQUE INDEX command_identities_process_key ON command_identities (actor_process, operation, idempotency_key)
                WHERE kind = 'COMMAND' AND actor_kind <> 'USER'
            SQL);
        $database->statement('CREATE INDEX command_identities_actor_user_id_index ON command_identities (actor_user_id)');

        $database->statement(<<<'SQL'
            CREATE TABLE command_results (
                command_id bigint PRIMARY KEY,
                identity_kind text NOT NULL CONSTRAINT command_results_identity_kind_command CHECK (identity_kind = 'COMMAND'),
                result_type text NOT NULL CONSTRAINT command_results_result_type_format CHECK (result_type ~ '^[a-z][a-z0-9_.]{0,63}$'),
                result_ref bigint NULL,
                result_detail jsonb NOT NULL,
                committed_at timestamptz NOT NULL,
                CONSTRAINT command_results_identity_fk FOREIGN KEY (command_id, identity_kind)
                    REFERENCES command_identities (id, kind) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        $database->statement(<<<'SQL'
            ALTER TABLE command_identities ADD CONSTRAINT command_identities_result_presence_fk
                FOREIGN KEY (result_presence) REFERENCES command_results (command_id) DEFERRABLE INITIALLY DEFERRED
            SQL);
        $database->statement('CREATE INDEX command_identities_result_presence_index ON command_identities (result_presence)');

        $database->statement(<<<'SQL'
            CREATE TABLE command_request_receipts (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                actor_user_id bigint NOT NULL CONSTRAINT command_request_receipts_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                operation text NOT NULL CONSTRAINT command_request_receipts_operation_format CHECK (operation ~ '^[a-z][a-z0-9_.]{0,63}$'),
                source_intent_id uuid NOT NULL,
                idempotency_key uuid NOT NULL,
                recovery_epoch bytea NOT NULL,
                received_at timestamptz NOT NULL,
                outcome text NOT NULL CONSTRAINT command_request_receipts_outcome_allowed CHECK (outcome IN ('PENDING', 'COMMITTED', 'REJECTED', 'HELD', 'NOT_COMMITTED')),
                outcome_at timestamptz NULL,
                held_code text NULL CONSTRAINT command_request_receipts_held_code_allowed CHECK (held_code IN ('IDEMPOTENCY_CONFLICT', 'EPOCH_STALE')),
                settled_by_command_id bigint NULL CONSTRAINT command_request_receipts_settled_by_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                settled_by_user_id bigint NULL CONSTRAINT command_request_receipts_settled_by_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT command_request_receipts_outcome_time CHECK ((outcome = 'PENDING') = (outcome_at IS NULL)),
                CONSTRAINT command_request_receipts_held_has_code CHECK (outcome <> 'HELD' OR held_code IS NOT NULL),
                CONSTRAINT command_request_receipts_settled_pair CHECK ((settled_by_command_id IS NULL) = (settled_by_user_id IS NULL)),
                CONSTRAINT command_request_receipts_settled_outcome CHECK (settled_by_command_id IS NULL OR outcome IN ('COMMITTED', 'NOT_COMMITTED'))
            )
            SQL);

        $database->statement('CREATE INDEX command_request_receipts_actor_outcome ON command_request_receipts (actor_user_id, outcome)');
        $database->statement('CREATE INDEX command_request_receipts_intent ON command_request_receipts (operation, source_intent_id)');
        $database->statement('CREATE INDEX command_request_receipts_settled_by_command_id_index ON command_request_receipts (settled_by_command_id)');
        $database->statement('CREATE INDEX command_request_receipts_settled_by_user_id_index ON command_request_receipts (settled_by_user_id)');

        $database->statement(<<<'SQL'
            CREATE TABLE stale_intent_reports (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                operation text NOT NULL CONSTRAINT stale_intent_reports_operation_format CHECK (operation ~ '^[a-z][a-z0-9_.]{0,63}$'),
                source_intent_id uuid NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT stale_intent_reports_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                reported_epoch bytea NOT NULL,
                first_reported_at timestamptz NOT NULL,
                CONSTRAINT stale_intent_reports_intent_unique UNIQUE (operation, source_intent_id)
            )
            SQL);
        $database->statement('CREATE INDEX stale_intent_reports_actor_user_id_index ON stale_intent_reports (actor_user_id)');

        $database->statement(<<<'SQL'
            CREATE TABLE intent_reconciliations (
                report_id bigint PRIMARY KEY CONSTRAINT intent_reconciliations_report_fk REFERENCES stale_intent_reports (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                outcome text NOT NULL CONSTRAINT intent_reconciliations_outcome_not_committed CHECK (outcome = 'NOT_COMMITTED'),
                fence_command_id bigint NOT NULL CONSTRAINT intent_reconciliations_fence_unique UNIQUE
                    CONSTRAINT intent_reconciliations_fence_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                decided_by_user_id bigint NOT NULL CONSTRAINT intent_reconciliations_decided_by_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                operator_note text NOT NULL CONSTRAINT intent_reconciliations_operator_note_length CHECK (char_length(operator_note) BETWEEN 1 AND 1000),
                recorded_at timestamptz NOT NULL
            )
            SQL);
        $database->statement('CREATE INDEX intent_reconciliations_decided_by_user_id_index ON intent_reconciliations (decided_by_user_id)');

        // EXC-44: the key of audit_events that TASK-006 left for this Task.
        $database->statement(<<<'SQL'
            ALTER TABLE audit_events ADD CONSTRAINT audit_events_command_fk
                FOREIGN KEY (command_id) REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT
            SQL);

        $database->statement('GRANT SELECT, INSERT, UPDATE (epoch, set_at) ON TABLE recovery_epoch TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT ON TABLE command_identities, command_results, stale_intent_reports, intent_reconciliations TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT, UPDATE (outcome, outcome_at, held_code, settled_by_command_id, settled_by_user_id), DELETE ON TABLE command_request_receipts TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE recovery_epoch, command_identities, command_results, command_request_receipts, stale_intent_reports, intent_reconciliations TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE command_identities_id_seq, command_request_receipts_id_seq, stale_intent_reports_id_seq TO '.Identifier::quote($roles->backup));
    }
};
