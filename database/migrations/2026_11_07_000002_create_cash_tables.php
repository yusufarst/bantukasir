<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-027: the cash registers and the cash sessions of the Cash module (SCHEMA section 5.10;
 * section 6, SCH-20 to SCH-28; BR-CASH-01 to BR-CASH-10), with their checks, keys, indexes and
 * grants (SCHEMA sections 10 and 11, database/privileges.php):
 *
 * - cash_registers (class M): the code unique without regard to letter case through lower() in the
 *   unique index, the only authority for it (SCH-14); the label 1 to 100 and not unique; the active
 *   flag and the expected version. A change is allowed only while no session is OPEN on the
 *   register, which the Action holds under the register lock (G-19; RACE-26).
 * - cash_sessions (class FS): the immutable number `YYYYMMDD-n`, held by a check to be exactly the
 *   business date of opening and its counter value (SCH-22), both unique (SCH-24); the register,
 *   the responsible user with the name frozen (SCHEMA section 3.5), the opening cash of zero or more
 *   (BR-CASH-04), the opening time and command; the state columns status, activity_version and
 *   updated_at, the only ones the runtime role may update. close_presence is the presence key of
 *   SCHEMA section 3.7, equal to the id exactly when the session is CLOSED: its deferred key to
 *   cash_session_closes is TASK-030's, which creates that table, and no code of this Task writes a
 *   CLOSED status (EXECUTION_CONTEXT EXC-44). The composite unique keys are the targets of the
 *   Sales, the receipt snapshots, the closes and the reports of later Tasks. At most one OPEN
 *   session per register and per user is held by two partial unique indexes (BR-CASH-06; RACE-07).
 * - cash_session_number_seq: one installation-wide counter, CACHE 1 and NO CYCLE, so that numbers
 *   follow allocation order and never wrap (SCH-20, SCH-23); the runtime role has USAGE only, never
 *   UPDATE, so it can take a value but never move the sequence (SCH-27, SCH-44).
 *
 * EXC-44: audit_events.cash_session_id → cash_sessions, the key that TASK-006 left for this Task
 * (the migration 2026_10_26_000002_create_audit_tables); its column has its index since then.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a second, on two new,
 * empty tables, a new sequence and a key whose column is null on every row. Lock impact: the keys
 * to users and command_identities and the key added to audit_events take a SHARE ROW EXCLUSIVE
 * lock on those three tables, held to the end of the migration's transaction, and validating the
 * key of audit_events scans it once under that lock: every command, whose first write is its
 * identity and whose audit event follows, waits that long. Verification (REL-20):
 * db:check-privileges passes; tests/Feature/Cash/CashTablesTest.php proves the constraint,
 * privilege, retention and numbering families as the runtime role.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement(<<<'SQL'
            CREATE TABLE cash_registers (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                code text NOT NULL CONSTRAINT cash_registers_code_length CHECK (char_length(code) BETWEEN 1 AND 32),
                label text NOT NULL CONSTRAINT cash_registers_label_length CHECK (char_length(label) BETWEEN 1 AND 100),
                active boolean NOT NULL,
                version integer NOT NULL CONSTRAINT cash_registers_version_positive CHECK (version >= 1),
                created_at timestamptz NOT NULL,
                updated_at timestamptz NOT NULL
            )
            SQL);

        $database->statement('CREATE UNIQUE INDEX cash_registers_code_ci_uniq ON cash_registers (lower(code))');

        $database->statement('CREATE SEQUENCE cash_session_number_seq AS bigint START 1 INCREMENT 1 NO CYCLE CACHE 1');

        $database->statement(<<<'SQL'
            CREATE TABLE cash_sessions (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                session_number text NOT NULL CONSTRAINT cash_sessions_number_format CHECK (session_number ~ '^[0-9]{8}-[0-9]{6,}$'),
                session_number_seq bigint NOT NULL CONSTRAINT cash_sessions_number_seq_positive CHECK (session_number_seq >= 1),
                opened_business_date date NOT NULL,
                register_id bigint NOT NULL CONSTRAINT cash_sessions_register_fk REFERENCES cash_registers (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                responsible_user_id bigint NOT NULL CONSTRAINT cash_sessions_responsible_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                responsible_display_name text NOT NULL CONSTRAINT cash_sessions_responsible_display_name_length CHECK (char_length(responsible_display_name) BETWEEN 1 AND 100),
                opening_cash bigint NOT NULL CONSTRAINT cash_sessions_opening_cash_range CHECK (opening_cash BETWEEN 0 AND 999999999999),
                opened_at timestamptz NOT NULL,
                open_command_id bigint NOT NULL CONSTRAINT cash_sessions_open_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                status text NOT NULL CONSTRAINT cash_sessions_status_allowed CHECK (status IN ('OPEN', 'CLOSED')),
                activity_version bigint NOT NULL CONSTRAINT cash_sessions_activity_version_range CHECK (activity_version >= 0),
                close_presence bigint GENERATED ALWAYS AS (CASE WHEN status = 'CLOSED' THEN id END) STORED,
                updated_at timestamptz NOT NULL,
                CONSTRAINT cash_sessions_number_matches CHECK (session_number = (EXTRACT(YEAR FROM opened_business_date) * 10000
                    + EXTRACT(MONTH FROM opened_business_date) * 100
                    + EXTRACT(DAY FROM opened_business_date))::integer::text
                    || '-' || lpad(session_number_seq::text, greatest(6, char_length(session_number_seq::text)), '0')),
                CONSTRAINT cash_sessions_number_unique UNIQUE (session_number),
                CONSTRAINT cash_sessions_number_seq_unique UNIQUE (session_number_seq),
                CONSTRAINT cash_sessions_open_command_unique UNIQUE (open_command_id),
                CONSTRAINT cash_sessions_id_responsible_unique UNIQUE (id, responsible_user_id),
                CONSTRAINT cash_sessions_id_status_unique UNIQUE (id, status),
                CONSTRAINT cash_sessions_id_number_unique UNIQUE (id, session_number),
                CONSTRAINT cash_sessions_report_target_unique UNIQUE (id, session_number, opening_cash, opened_at, responsible_display_name)
            )
            SQL);

        // BR-CASH-06, RACE-07: at most one OPEN session per register and per user.
        $database->statement("CREATE UNIQUE INDEX cash_sessions_one_open_per_register ON cash_sessions (register_id) WHERE status = 'OPEN'");
        $database->statement("CREATE UNIQUE INDEX cash_sessions_one_open_per_user ON cash_sessions (responsible_user_id) WHERE status = 'OPEN'");

        // SCHEMA section 11: the sessions' order and the user's sessions, and the register's key,
        // which the partial index covers only for the OPEN sessions.
        $database->statement('CREATE INDEX cash_sessions_opened_at_id_index ON cash_sessions (opened_at, id)');
        $database->statement('CREATE INDEX cash_sessions_responsible_opened_at_id_index ON cash_sessions (responsible_user_id, opened_at, id)');
        $database->statement('CREATE INDEX cash_sessions_register_id_index ON cash_sessions (register_id)');

        // EXC-44: the key of audit_events that TASK-006 left for this Task.
        $database->statement(<<<'SQL'
            ALTER TABLE audit_events ADD CONSTRAINT audit_events_cash_session_fk
                FOREIGN KEY (cash_session_id) REFERENCES cash_sessions (id) ON DELETE RESTRICT ON UPDATE RESTRICT
            SQL);

        $database->statement('GRANT SELECT, INSERT, UPDATE (code, label, active, version, updated_at) ON TABLE cash_registers TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT, UPDATE (status, activity_version, updated_at) ON TABLE cash_sessions TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT USAGE ON SEQUENCE cash_session_number_seq TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE cash_registers, cash_sessions TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE cash_registers_id_seq, cash_sessions_id_seq, cash_session_number_seq TO '.Identifier::quote($roles->backup));
    }
};
