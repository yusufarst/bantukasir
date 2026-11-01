<?php

use Illuminate\Database\Migrations\Migration;

/*
 * A pending migration marked destructive, without an approval reference. The tests
 * never run it.
 *
 * Class: destructive (RELEASE_PROCESS section 5). Expected duration: none. Lock
 * impact: none. Verification (REL-20): none needed in a test.
 */
return new class extends Migration
{
    public function up(): void {}
};
