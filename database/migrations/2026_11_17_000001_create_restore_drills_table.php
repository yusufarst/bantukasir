<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-037: restore_drills (SCHEMA section 5.12, class F), one row per production restore drill,
 * written once by the operator procedure OP-08 `restore:record-drill` (SECURITY_MODEL SEC-OP-08;
 * BACKUP_RECOVERY RB-02 step 10) as the runtime role and read for the Owner's system status (TASK-039).
 * It holds no business fact, no secret and no path. Its actor is always the procedure (SCHEMA section
 * 3.4). Its index serves "the newest drill" of OPERATIONS_RUNBOOK section 5.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a second. Lock impact:
 * none on an existing table. Verification (REL-20): db:check-privileges passes;
 * tests/Feature/Operations/Restore/RestoreDrillsTest.php proves the constraint, privilege and
 * retention families.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement(<<<'SQL'
            CREATE TABLE restore_drills (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                kind text NOT NULL CONSTRAINT restore_drills_kind_allowed CHECK (kind IN ('ROUTINE', 'FULL')),
                backup_id text NOT NULL CONSTRAINT restore_drills_backup_id_format CHECK (backup_id ~ '^[0-9]{8}T[0-9]{6}Z-[A-Za-z0-9]{8}$'),
                manifest_sha256 bytea NOT NULL CONSTRAINT restore_drills_manifest_sha256_length CHECK (octet_length(manifest_sha256) = 32),
                started_at timestamptz NOT NULL,
                finished_at timestamptz NOT NULL,
                result text NOT NULL CONSTRAINT restore_drills_result_allowed CHECK (result IN ('PASS', 'FAIL')),
                restore_seconds integer NOT NULL CONSTRAINT restore_drills_restore_seconds_not_negative CHECK (restore_seconds >= 0),
                operator_note text NOT NULL CONSTRAINT restore_drills_operator_note_length CHECK (char_length(operator_note) BETWEEN 1 AND 1000),
                recorded_at timestamptz NOT NULL,
                actor_kind text NOT NULL,
                actor_user_id bigint NULL CONSTRAINT restore_drills_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_process text NULL,
                CONSTRAINT restore_drills_finished_after_started CHECK (finished_at >= started_at),
                CONSTRAINT restore_drills_actor CHECK (actor_kind = 'OPERATOR_PROCEDURE' AND actor_user_id IS NULL AND actor_process = 'restore_drill_record')
            )
            SQL);
        $database->statement('CREATE INDEX restore_drills_finished_at_index ON restore_drills (finished_at)');
        $database->statement('CREATE INDEX restore_drills_actor_user_id_index ON restore_drills (actor_user_id)');

        $database->statement('GRANT SELECT, INSERT ON TABLE restore_drills TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE restore_drills TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE restore_drills_id_seq TO '.Identifier::quote($roles->backup));
    }
};
