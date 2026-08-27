<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ticket_histories had no tenant_id and no global scope, so it could only be
 * scoped through its parent ticket. Anything querying it directly — the
 * dashboard's activity feed filters by user_id alone — returned rows from every
 * workspace a user belongs to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_histories', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();
        });

        // Backfill from the parent ticket, which is the only source of truth for
        // rows written before this column existed.
        DB::statement('
            UPDATE ticket_histories h
            JOIN tickets t ON t.id = h.ticket_id
            SET h.tenant_id = t.tenant_id
            WHERE h.tenant_id IS NULL
        ');

        Schema::table('ticket_histories', function (Blueprint $table) {
            $table->index(['tenant_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_histories', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'user_id', 'created_at']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
