<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * merge_metadata was declared in the Ticket model's $fillable and $casts but no
 * migration ever created it, so writes to it failed. It records what a merge
 * moved and the status it overwrote, which is what makes unmerge reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->json('merge_metadata')->nullable()->after('merged_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('merge_metadata');
        });
    }
};
