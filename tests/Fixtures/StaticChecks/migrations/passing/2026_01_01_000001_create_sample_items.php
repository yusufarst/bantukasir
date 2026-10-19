<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Compliant sample of the migration review: additive only.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_items', function (Blueprint $table): void {
            $table->id();
            $table->text('sample_note')->nullable();
            $table->index('sample_note');
        });
    }
};
