<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-013: backup_runs (SCHEMA section 5.12, class F), one row per run of
 * backup:run (ROUTE_CONTRACTS SC-03; ADR-010), written once by the backup role
 * (SCH-42) and read by the runtime role for the Owner's system status. It holds
 * no secret, no path and no message of an external service. Its one index serves
 * "the newest rows" of OPERATIONS_RUNBOOK section 5 and of the next run.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a
 * second. Lock impact: none on an existing table. Verification (REL-20):
 * db:check-privileges passes; tests/Feature/Operations/Backup/BackupRunsTableTest.php
 * proves the constraint, privilege and retention families.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement(<<<'SQL'
            CREATE TABLE backup_runs (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                backup_id text NOT NULL CONSTRAINT backup_runs_backup_id_format CHECK (backup_id ~ '^[0-9]{8}T[0-9]{6}Z-[A-Za-z0-9]{8}$'),
                reason text NOT NULL CONSTRAINT backup_runs_reason_allowed CHECK (reason IN ('SCHEDULED', 'PRE_RELEASE', 'ON_DEMAND')),
                started_at timestamptz NOT NULL,
                finished_at timestamptz NOT NULL,
                outcome text NOT NULL CONSTRAINT backup_runs_outcome_allowed CHECK (outcome IN ('SUCCEEDED', 'FAILED')),
                failure_code text NULL CONSTRAINT backup_runs_failure_code_allowed CHECK (failure_code IN ('NO_SPACE', 'DUMP_INVALID', 'FILE_MISSING', 'FILE_CORRUPT', 'STORE_REFUSED', 'STORE_AUTH', 'REMOTE_MISMATCH', 'TIMEOUT')),
                snapshot_at timestamptz NULL,
                tiers text NOT NULL CONSTRAINT backup_runs_tiers_allowed CHECK (tiers IN ('6h', '6h,weekly', '6h,monthly', '6h,weekly,monthly')),
                dump_bytes bigint NULL CONSTRAINT backup_runs_dump_bytes_not_negative CHECK (dump_bytes >= 0),
                files_uploaded bigint NULL CONSTRAINT backup_runs_files_uploaded_not_negative CHECK (files_uploaded >= 0),
                uploaded_bytes bigint NULL CONSTRAINT backup_runs_uploaded_bytes_not_negative CHECK (uploaded_bytes >= 0),
                store_bytes bigint NULL CONSTRAINT backup_runs_store_bytes_not_negative CHECK (store_bytes >= 0),
                dump_object_sha256 bytea NULL CONSTRAINT backup_runs_dump_object_sha256_length CHECK (octet_length(dump_object_sha256) = 32),
                manifest_object_sha256 bytea NULL CONSTRAINT backup_runs_manifest_object_sha256_length CHECK (octet_length(manifest_object_sha256) = 32),
                reverified_backup_id text NULL CONSTRAINT backup_runs_reverified_backup_id_format CHECK (reverified_backup_id ~ '^[0-9]{8}T[0-9]{6}Z-[A-Za-z0-9]{8}$'),
                CONSTRAINT backup_runs_backup_id_unique UNIQUE (backup_id),
                CONSTRAINT backup_runs_finished_after_started CHECK (finished_at >= started_at),
                CONSTRAINT backup_runs_failure_code_when_failed CHECK ((outcome = 'FAILED') = (failure_code IS NOT NULL)),
                CONSTRAINT backup_runs_success_complete CHECK (outcome <> 'SUCCEEDED' OR (
                    snapshot_at IS NOT NULL AND dump_bytes IS NOT NULL AND files_uploaded IS NOT NULL AND uploaded_bytes IS NOT NULL
                    AND store_bytes IS NOT NULL AND dump_object_sha256 IS NOT NULL AND manifest_object_sha256 IS NOT NULL
                ))
            )
            SQL);
        $database->statement('CREATE INDEX backup_runs_finished_at_index ON backup_runs (finished_at)');

        $database->statement('GRANT SELECT ON TABLE backup_runs TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT ON TABLE backup_runs TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE backup_runs_id_seq TO '.Identifier::quote($roles->backup));
    }
};
