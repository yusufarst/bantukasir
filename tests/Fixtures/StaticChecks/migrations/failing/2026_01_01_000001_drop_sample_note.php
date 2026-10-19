<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Negative sample of the migration review: drops a column with no approval reference.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_items', function (Blueprint $table): void {
            $table->dropColumn('sample_note');
        });
    }
};
