<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Negative sample of the migration review: truncates a table in SQL.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('TRUNCATE TABLE sample_items');
    }
};
