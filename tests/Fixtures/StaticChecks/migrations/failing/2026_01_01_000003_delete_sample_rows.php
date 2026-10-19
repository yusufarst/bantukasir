<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Negative sample of the migration review: deletes rows through the query builder.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sample_items')->where('id', 1)->delete();
    }
};
