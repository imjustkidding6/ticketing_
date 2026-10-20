<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table) {
            // The two gates that decided, up to P9, which halves of the declared detail set
            // (App\Support\TicketTechnicalFields) a ticket is asked for. They lived on
            // `departments`; the workspace owner's instruction is that the questions belong
            // to the CATEGORY, so they live here and only here. Two sources of truth for one
            // question is the drift this programme has avoided everywhere else.
            //
            // `asks_impact` DEFAULT TRUE and `is_technical` DEFAULT FALSE, matching the
            // defaults P8/P9 settled on one level up: breadth of impact and the existence of
            // a workaround are what the priority rubric separates `critical` from `high` on,
            // so a newly created category keeps asking them and opts OUT; technical detail
            // (environment, repro steps, error text) is the narrower ask and opts IN.
            //
            // NO BACKFILL, AND NONE IS POSSIBLE. The seeded `is_technical` flags on the
            // SOFTWARE and HARDWARE default departments are NOT copied onto anything: no
            // seeder creates categories at all, so there is nothing to copy them onto, and
            // a data statement in a migration is this team's hard stop regardless. Those
            // two flags are therefore LOST — after this deploy the detail fields appear only
            // where someone has created a category and configured it, which is the accepted
            // cost of moving the gate down a level.
            $table->boolean('is_technical')->default(false)->after('is_active');
            $table->boolean('asks_impact')->default(true)->after('is_technical');
        });
    }

    /**
     * Deliberately empty, matching the siblings 2026_10_08_000001, 2026_10_09_000001 and
     * 2026_10_20_000001: additive, so the rollback is "redeploy the previous image and
     * ignore the unused columns" rather than dropping real configuration under incident
     * pressure.
     */
    public function down(): void {}
};
