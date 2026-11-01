<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Negative sample of the migration review: marked destructive, with a statement the
 * patterns do not name and no approval reference.
 *
 * Class: destructive (RELEASE_PROCESS section 5). Expected duration: a minute. Lock
 * impact: the sample table. Verification (REL-20): the row counts.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SELECT sample_purge()');
    }
};
