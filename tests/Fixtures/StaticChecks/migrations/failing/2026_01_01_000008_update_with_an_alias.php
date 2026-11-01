<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Negative sample of the migration review: rows rewritten under an alias, with its
 * metadata and no approval reference.
 *
 * Class: expand (RELEASE_PROCESS section 5). Expected duration: a minute. Lock impact:
 * the sample table. Verification (REL-20): the row counts.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE sample_items AS s SET note = 1');
    }
};
