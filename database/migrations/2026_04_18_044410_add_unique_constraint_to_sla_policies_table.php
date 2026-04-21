<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->unique(['tenant_id', 'client_tier', 'priority'], 'sla_policies_tenant_tier_priority_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropUnique('sla_policies_tenant_tier_priority_unique');
        });
    }
};
