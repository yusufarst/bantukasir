<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Negative sample of the migration review: additive, with none of the metadata of REL-20.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_bare', function (Blueprint $table): void {
            $table->id();
        });
    }
};
