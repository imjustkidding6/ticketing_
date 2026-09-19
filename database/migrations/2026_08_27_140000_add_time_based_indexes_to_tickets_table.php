<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The four existing indexes all lead with (tenant_id, status), which serves list
 * filtering well and does nothing for the time-based access patterns: every
 * report date range, and the SLA breach sweep that runs every 15 minutes across
 * all tenants filtering on resolution_due_at / response_due_at.
 *
 * Also adds the two missing foreign keys. sla_policy_id and merged_into_ticket_id
 * were bare unsignedBigInteger columns, so deleting a policy or a mother ticket
 * left tickets pointing at nothing — and a merged child pointing at a missing
 * parent stays flagged is_merged, hidden from every list, forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['tenant_id', 'closed_at'], 'tickets_tenant_closed_idx');
            $table->index(['tenant_id', 'created_at'], 'tickets_tenant_created_idx');
            // The breach sweep runs withoutGlobalScopes across every tenant, so
            // this one leads with the due date rather than tenant_id.
            $table->index(['resolution_due_at', 'sla_breach_notified_at'], 'tickets_breach_sweep_idx');
            $table->index(['response_due_at', 'first_response_at'], 'tickets_response_due_idx');
        });

        // Orphaned rows would block the constraint; clear them first.
        Schema::disableForeignKeyConstraints();

        DB::statement('
            UPDATE tickets t
            LEFT JOIN sla_policies p ON p.id = t.sla_policy_id
            SET t.sla_policy_id = NULL
            WHERE t.sla_policy_id IS NOT NULL AND p.id IS NULL
        ');

        DB::statement('
            UPDATE tickets t
            LEFT JOIN tickets m ON m.id = t.merged_into_ticket_id
            SET t.merged_into_ticket_id = NULL, t.is_merged = 0
            WHERE t.merged_into_ticket_id IS NOT NULL AND m.id IS NULL
        ');

        Schema::enableForeignKeyConstraints();

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('sla_policy_id')->references('id')->on('sla_policies')->nullOnDelete();
            $table->foreign('merged_into_ticket_id')->references('id')->on('tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['sla_policy_id']);
            $table->dropForeign(['merged_into_ticket_id']);
            $table->dropIndex('tickets_tenant_closed_idx');
            $table->dropIndex('tickets_tenant_created_idx');
            $table->dropIndex('tickets_breach_sweep_idx');
            $table->dropIndex('tickets_response_due_idx');
        });
    }
};
