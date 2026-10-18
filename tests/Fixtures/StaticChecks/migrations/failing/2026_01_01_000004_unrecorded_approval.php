<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Negative sample of the migration review: names an approval that the decisions do not record.
// REL-06 approval: D-901
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE sample_items SET sample_note = NULL');
    }
};
