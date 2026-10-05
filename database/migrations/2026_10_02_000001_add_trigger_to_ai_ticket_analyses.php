<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which trigger produced an analysis, and the idempotency key for the automatic one.
 *
 * Purely additive: no column is dropped or renamed and there is no backfill step,
 * because `default('manual')` IS the backfill and it is semantically exact — every row
 * that exists today was written by an engineer pressing Analyze.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_ticket_analyses', function (Blueprint $table) {
            // varchar, not an enum: AiTicketAnalysis::TRIGGERS is the only enumeration,
            // so a third intake (inbound email) stays a code change with no migration.
            $table->string('trigger')->default('manual')->after('requested_by');

            // An idempotency key, not a relationship: written only on the auto path and
            // left NULL for every manual analysis. MySQL allows unlimited NULLs in a
            // unique index, so manual runs stay unlimited while a second auto row for one
            // ticket raises a QueryException in the DATABASE rather than in application
            // logic that can be bypassed. No FK — `ticket_id` is already the constrained
            // one and a second cascade to the same table buys nothing.
            $table->unsignedBigInteger('auto_for_ticket_id')->nullable()->after('ticket_id');
            $table->unique('auto_for_ticket_id', 'ai_analyses_auto_for_ticket_unique');

            // Serves both the per-trigger pilot query and the daily cap count.
            $table->index(['tenant_id', 'trigger', 'created_at'], 'ai_analyses_tenant_trigger_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_ticket_analyses', function (Blueprint $table) {
            $table->dropIndex('ai_analyses_tenant_trigger_created_idx');
            $table->dropUnique('ai_analyses_auto_for_ticket_unique');
            $table->dropColumn(['trigger', 'auto_for_ticket_id']);
        });
    }
};
