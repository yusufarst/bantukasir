<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-025: the Owner's cost correction and completion, and the mark that reporting is incomplete
 * after a refused one (SCHEMA section 5.8; ADR-008 section 4.3; D-034, D-063; BR-COST-20 to
 * BR-COST-27; G-15, G-21), with their checks, keys, indexes and grants (SCHEMA sections 10 and 11,
 * database/privileges.php):
 *
 * - cost_corrections (class F): a new value of one acquisition cost evidence, with the effective
 *   value before it — the original, or the previous correction's — and its next number, the reason
 *   and the Owner who made it. A correction always records a new amount: UNKNOWN is a status,
 *   never a value, so no correction makes a cost unknown, and an explicit zero carries its evidence
 *   or reason (BR-COST-03, BR-COST-04).
 * - cost_incomplete_marks (class F): the cost source whose correction was refused REPLAY_REJECTED,
 *   once per refused intent, with the cause and how many corrections the refused attempt saw.
 * - cost_incomplete_mark_clearances (class F): what cleared a mark — a later correction of the same
 *   source, a reversal of its movement or the cost recovery procedure — with the actor of the
 *   transaction that wrote it. Its key to reversals (id, original_movement_id) is TASK-026's, which
 *   creates that table; until then no application code writes clearing_reversal_id (EXC-44).
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a second, on three new,
 * empty tables. Lock impact: the keys to users, command_identities and acquisition_cost_evidence take
 * a SHARE ROW EXCLUSIVE lock on those tables, held to the end of the migration's transaction: every
 * command's first write, and every posting that adds cost evidence, waits that long.
 * Verification (REL-20): db:check-privileges passes; tests/Feature/Costing/CostCorrectionTablesTest.php
 * proves the constraint, privilege and retention families as the runtime role.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement(<<<'SQL'
            CREATE TABLE cost_corrections (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                evidence_id bigint NOT NULL,
                product_id bigint NOT NULL,
                correction_no integer NOT NULL CONSTRAINT cost_corrections_correction_no_positive CHECK (correction_no >= 1),
                old_status text NOT NULL CONSTRAINT cost_corrections_old_status_allowed CHECK (old_status IN ('KNOWN', 'UNKNOWN')),
                old_amount numeric(18,6) NULL CONSTRAINT cost_corrections_old_amount_range CHECK (old_amount BETWEEN 0 AND 999999999999.999999),
                new_amount numeric(18,6) NOT NULL CONSTRAINT cost_corrections_new_amount_range CHECK (new_amount BETWEEN 0 AND 999999999999.999999),
                zero_cost_note text NULL CONSTRAINT cost_corrections_zero_cost_note_length CHECK (char_length(zero_cost_note) BETWEEN 1 AND 1000),
                reason text NOT NULL CONSTRAINT cost_corrections_reason_length CHECK (char_length(reason) BETWEEN 1 AND 1000),
                recorded_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT cost_corrections_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT cost_corrections_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT cost_corrections_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT cost_corrections_old_status_amount CHECK ((old_status = 'KNOWN') = (old_amount IS NOT NULL)),
                CONSTRAINT cost_corrections_zero_has_note CHECK (new_amount <> 0 OR zero_cost_note IS NOT NULL),
                CONSTRAINT cost_corrections_evidence_correction_unique UNIQUE (evidence_id, correction_no),
                CONSTRAINT cost_corrections_command_unique UNIQUE (command_id),
                CONSTRAINT cost_corrections_id_evidence_unique UNIQUE (id, evidence_id),
                CONSTRAINT cost_corrections_evidence_fk FOREIGN KEY (evidence_id, product_id)
                    REFERENCES acquisition_cost_evidence (id, product_id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE cost_incomplete_marks (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                evidence_id bigint NOT NULL,
                product_id bigint NOT NULL,
                evidence_movement_id bigint NOT NULL,
                rejected_operation text NOT NULL CONSTRAINT cost_incomplete_marks_rejected_operation_format CHECK (rejected_operation ~ '^[a-z][a-z0-9_.]{0,63}$'),
                rejected_source_intent_id uuid NOT NULL,
                rejection_code text NOT NULL CONSTRAINT cost_incomplete_marks_rejection_code_allowed CHECK (rejection_code IN ('MOVEMENT_CAP', 'TIME_BUDGET', 'UNSAFE_HISTORY')),
                corrections_seen integer NOT NULL CONSTRAINT cost_incomplete_marks_corrections_seen_range CHECK (corrections_seen >= 0),
                recorded_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT cost_incomplete_marks_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT cost_incomplete_marks_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                CONSTRAINT cost_incomplete_marks_intent_unique UNIQUE (rejected_operation, rejected_source_intent_id),
                CONSTRAINT cost_incomplete_marks_id_evidence_movement_unique UNIQUE (id, evidence_id, evidence_movement_id),
                CONSTRAINT cost_incomplete_marks_evidence_fk FOREIGN KEY (evidence_id, product_id, evidence_movement_id)
                    REFERENCES acquisition_cost_evidence (id, product_id, movement_id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE cost_incomplete_mark_clearances (
                mark_id bigint PRIMARY KEY,
                evidence_id bigint NOT NULL,
                evidence_movement_id bigint NOT NULL,
                clearing_kind text NOT NULL CONSTRAINT cost_incomplete_mark_clearances_clearing_kind_allowed CHECK (clearing_kind IN ('LATER_CORRECTION', 'SOURCE_REVERSED', 'RECOVERY_PROCEDURE')),
                clearing_correction_id bigint NULL,
                clearing_reversal_id bigint NULL,
                cleared_at timestamptz NOT NULL,
                actor_kind text NOT NULL CONSTRAINT cost_incomplete_mark_clearances_actor_kind_allowed CHECK (actor_kind IN ('USER', 'OPERATOR_PROCEDURE', 'SCHEDULED_COMMAND')),
                actor_user_id bigint NULL CONSTRAINT cost_incomplete_mark_clearances_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_process text NULL CONSTRAINT cost_incomplete_mark_clearances_actor_process_format CHECK (actor_process ~ '^[a-z][a-z0-9_]{0,63}$'),
                actor_display_name text NOT NULL CONSTRAINT cost_incomplete_mark_clearances_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NULL CONSTRAINT cost_incomplete_mark_clearances_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT cost_incomplete_mark_clearances_actor CHECK (
                    (actor_kind = 'USER' AND actor_user_id IS NOT NULL AND actor_process IS NULL)
                    OR (actor_kind <> 'USER' AND actor_user_id IS NULL AND actor_process IS NOT NULL)
                ),
                CONSTRAINT cost_incomplete_mark_clearances_cause CHECK (
                    (clearing_kind = 'LATER_CORRECTION') = (clearing_correction_id IS NOT NULL)
                    AND (clearing_kind = 'SOURCE_REVERSED') = (clearing_reversal_id IS NOT NULL)
                ),
                CONSTRAINT cost_incomplete_mark_clearances_mark_fk FOREIGN KEY (mark_id, evidence_id, evidence_movement_id)
                    REFERENCES cost_incomplete_marks (id, evidence_id, evidence_movement_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT cost_incomplete_mark_clearances_correction_fk FOREIGN KEY (clearing_correction_id, evidence_id)
                    REFERENCES cost_corrections (id, evidence_id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        // SCHEMA section 11: the marks by product and by evidence, and every foreign-key column that
        // a key above does not lead. The index of clearing_reversal_id comes with its key (TASK-026).
        $database->statement('CREATE INDEX cost_corrections_product_id_index ON cost_corrections (product_id)');
        $database->statement('CREATE INDEX cost_corrections_actor_user_id_index ON cost_corrections (actor_user_id)');
        $database->statement('CREATE INDEX cost_incomplete_marks_product_id_index ON cost_incomplete_marks (product_id)');
        $database->statement('CREATE INDEX cost_incomplete_marks_evidence_id_index ON cost_incomplete_marks (evidence_id)');
        $database->statement('CREATE INDEX cost_incomplete_marks_actor_user_id_index ON cost_incomplete_marks (actor_user_id)');
        $database->statement('CREATE INDEX cost_incomplete_mark_clearances_correction_index ON cost_incomplete_mark_clearances (clearing_correction_id)');
        $database->statement('CREATE INDEX cost_incomplete_mark_clearances_actor_user_id_index ON cost_incomplete_mark_clearances (actor_user_id)');
        $database->statement('CREATE INDEX cost_incomplete_mark_clearances_command_id_index ON cost_incomplete_mark_clearances (command_id)');

        $database->statement('GRANT SELECT, INSERT ON TABLE cost_corrections, cost_incomplete_marks, cost_incomplete_mark_clearances TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE cost_corrections, cost_incomplete_marks, cost_incomplete_mark_clearances TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE cost_corrections_id_seq, cost_incomplete_marks_id_seq TO '.Identifier::quote($roles->backup));
    }
};
