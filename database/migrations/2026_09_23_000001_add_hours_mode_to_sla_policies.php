<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two modes, not one: response and resolution each carry their own clock, so
     * "respond within 2 calendar hours, resolve within 16 business hours" is
     * expressible on a single policy row.
     *
     * Additive and non-retroactive. The column default is what makes it safe — every
     * existing row reads 'calendar', which is byte-for-byte today's arithmetic, and
     * stored due dates are never recomputed, so no historical figure moves.
     */
    public function up(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            // varchar, not enum — same reasoning as tickets.status: SlaPolicy::HOURS_MODES
            // is the only enumeration and every entry point validates against it, so
            // adding a mode stays a code change with no migration.
            $table->string('response_hours_mode', 16)->default('calendar')->after('response_time_hours');
            $table->string('resolution_hours_mode', 16)->default('calendar')->after('resolution_time_hours');
        });
    }

    public function down(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropColumn(['response_hours_mode', 'resolution_hours_mode']);
        });
    }
};
