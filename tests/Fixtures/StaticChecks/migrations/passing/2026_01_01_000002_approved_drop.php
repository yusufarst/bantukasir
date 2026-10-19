<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Compliant sample of the migration review: a drop with its recorded approval.
// REL-06 approval: D-900
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_items', function (Blueprint $table): void {
            $table->dropIndex(['sample_note']);
            $table->dropColumn('sample_note');
        });
    }
};
