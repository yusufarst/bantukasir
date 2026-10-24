<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// A test-only migration (TASK-004, MigrationDisciplineTest): it names no
// connection, so it runs on the connection given with --database.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE harness_guarded (id bigint PRIMARY KEY)');
    }
};
