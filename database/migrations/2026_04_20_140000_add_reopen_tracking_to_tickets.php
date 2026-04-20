<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('first_closed_at')->nullable()->after('closed_at');
            $table->timestamp('last_reopened_at')->nullable()->after('first_closed_at');
            $table->string('last_reopen_reason', 500)->nullable()->after('last_reopened_at');
        });

        // Backfill: tickets already closed at least once get first_closed_at = closed_at.
        \Illuminate\Support\Facades\DB::statement('UPDATE tickets SET first_closed_at = closed_at WHERE closed_at IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['first_closed_at', 'last_reopened_at', 'last_reopen_reason']);
        });
    }
};
