<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-036: reconciliation_runs (SCHEMA section 5.12, class F), one row per run of
 * reconciliation:run (ROUTE_CONTRACTS SC-01; ENVIRONMENTS section 13), written once by the
 * runtime role at the end of its run and read for the Owner's system status (TASK-039). It holds
 * no business fact, no secret and no path. Its one index serves "the newest row" of
 * OPERATIONS_RUNBOOK section 5.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a second. Lock
 * impact: none on an existing table. Verification (REL-20): db:check-privileges passes;
 * tests/Feature/Operations/Reconciliation/ReconciliationRunsTableTest.php proves the constraint,
 * privilege and retention families.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement(<<<'SQL'
            CREATE TABLE reconciliation_runs (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                started_at timestamptz NOT NULL,
                finished_at timestamptz NOT NULL,
                outcome text NOT NULL CONSTRAINT reconciliation_runs_outcome_allowed CHECK (outcome IN ('CLEAN', 'MISMATCH', 'FAILED')),
                products_checked integer NOT NULL CONSTRAINT reconciliation_runs_products_checked_not_negative CHECK (products_checked >= 0),
                blocks_written integer NOT NULL CONSTRAINT reconciliation_runs_blocks_written_not_negative CHECK (blocks_written >= 0),
                other_differences integer NOT NULL CONSTRAINT reconciliation_runs_other_differences_not_negative CHECK (other_differences >= 0),
                scope text NOT NULL CONSTRAINT reconciliation_runs_scope_allowed CHECK (scope IN ('ALL', 'PRODUCT')),
                run_trigger text NOT NULL CONSTRAINT reconciliation_runs_run_trigger_allowed CHECK (run_trigger IN ('SCHEDULE', 'OPERATOR')),
                CONSTRAINT reconciliation_runs_finished_after_started CHECK (finished_at >= started_at)
            )
            SQL);
        $database->statement('CREATE INDEX reconciliation_runs_finished_at_index ON reconciliation_runs (finished_at)');

        $database->statement('GRANT SELECT, INSERT ON TABLE reconciliation_runs TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE reconciliation_runs TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE reconciliation_runs_id_seq TO '.Identifier::quote($roles->backup));
    }
};
