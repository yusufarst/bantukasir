<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * A pending migration of the release preflight's and the migration job's tests. It
 * changes nothing, so that the privilege check still passes after it.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: none. Lock impact:
 * none. Verification (REL-20): its row in the migrations table.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        DB::connection($this->connection)->statement('select 1');
    }
};
