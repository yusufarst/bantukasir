<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * A pending migration that fails, for the migration job's test.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: none. Lock impact:
 * none. Verification (REL-20): it never commits.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        DB::connection($this->connection)->statement('select harness_no_such_function()');
    }
};
