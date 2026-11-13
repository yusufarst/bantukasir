<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-032: the Owner's review notes on a CLOSED cash session, in the Cash module (SCHEMA section 5.10,
 * `cash_session_review_notes`; BR-CASH-19; D-069 (f)), with its checks, keys, indexes and grants (SCHEMA
 * sections 10 and 11, database/privileges.php):
 *
 * - cash_session_review_notes (class F): one row per note, never edited or removed; a correction is a new
 *   note. Its key to cash_session_closes names a session that has its close, so a note exists only for a
 *   CLOSED session; the text of 1 to 2,000 characters, the time it was recorded, the Owner with the name
 *   frozen (SCHEMA section 3.5), and the command, unique. Several notes per session (D-069 (f)).
 *
 * Keys completed (EXC-44): none; no earlier table names this one.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a second, on one new, empty
 * table. Lock impact: the keys to cash_session_closes, users and command_identities take a SHARE ROW
 * EXCLUSIVE lock on those tables, held to the end of the migration's transaction: every close waits that
 * long. Verification (REL-20): db:check-privileges passes; tests/Feature/Cash/ReviewNotesTableTest.php
 * proves the constraint, privilege and retention families as the runtime role, and the indexes.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement(<<<'SQL'
            CREATE TABLE cash_session_review_notes (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                session_id bigint NOT NULL CONSTRAINT cash_session_review_notes_session_fk REFERENCES cash_session_closes (session_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                note text NOT NULL CONSTRAINT cash_session_review_notes_note_length CHECK (char_length(note) BETWEEN 1 AND 2000),
                recorded_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT cash_session_review_notes_actor_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT cash_session_review_notes_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT cash_session_review_notes_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT cash_session_review_notes_command_unique UNIQUE (command_id)
            )
            SQL);

        // SCHEMA section 11: every foreign-key column that a key above does not lead.
        $database->statement('CREATE INDEX cash_session_review_notes_session_index ON cash_session_review_notes (session_id)');
        $database->statement('CREATE INDEX cash_session_review_notes_actor_index ON cash_session_review_notes (actor_user_id)');

        $database->statement('GRANT SELECT, INSERT ON TABLE cash_session_review_notes TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE cash_session_review_notes TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE cash_session_review_notes_id_seq TO '.Identifier::quote($roles->backup));
    }
};
