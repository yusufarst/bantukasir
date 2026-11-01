<?php

use Illuminate\Database\Migrations\Migration;

/*
 * A pending migration marked destructive, with its approval reference. The tests
 * never run it.
 *
 * Class: destructive (RELEASE_PROCESS section 5). Expected duration: none. Lock
 * impact: none. Verification (REL-20): none needed in a test.
 */
// REL-06 approval: D-900
return new class extends Migration
{
    public function up(): void {}
};
