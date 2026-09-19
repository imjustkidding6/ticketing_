<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Informational only. Pending time is already inside
            // total_hold_time_minutes; this records how much of it was spent
            // waiting on the customer. It must never enter an SLA calculation
            // or the hold would be subtracted twice.
            $table->unsignedInteger('total_pending_time_minutes')->default(0)->after('total_hold_time_minutes');
            $table->timestamp('pending_since')->nullable()->after('total_pending_time_minutes');
        });
    }

    /**
     * Deliberately empty: this migration is additive, so the rollback story is
     * "redeploy the previous image and ignore the unused columns" rather than
     * dropping data under incident pressure.
     */
    public function down(): void {}
};
