<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// A test-only migration (TASK-004, MigrationDisciplineTest): it runs on the
// migration connection, while the migrations table is written through the
// connection given with --database.
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        DB::connection($this->connection)->statement('CREATE TABLE harness_guarded_too (id bigint PRIMARY KEY)');
    }
};
