<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Negative sample of the migration review: a class outside RELEASE_PROCESS section 5,
 * and a lock impact and verification with no value.
 *
 * Class: harmless. Expected duration: a second. Lock impact:
 * Verification (REL-20):
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_partial', function (Blueprint $table): void {
            $table->id();
        });
    }
};
