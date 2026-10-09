<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // The declared technical detail set (App\Support\TicketTechnicalFields).
            //
            // VARCHAR, NOT ENUM, and for the same reason tickets.status stopped being one
            // (2026-09-18): the enumeration lives in ONE place in PHP and every entry
            // point validates against it, so adding an option is a code change with no
            // migration. A MySQL ENUM here would make the declared set two sources of
            // truth that can disagree.
            //
            // NULLABLE with NO DEFAULT, deliberately: every existing row reads NULL,
            // which the "information needed" list renders as "not captured". A default of
            // 'production' or 'one_person' would invent a reported fact for 100% of
            // historical tickets and feed it to the priority rubric as though a human had
            // stated it. Nothing is backfilled.
            //
            // No index: nothing in this package filters, sorts or groups by any of these
            // columns — the three selects are read off an already-loaded ticket, and
            // validation is `in:` against a PHP array rather than a database lookup.
            // `tickets` is the hottest write table in the application, so an index that
            // serves no query is pure write cost; an index is additive and belongs to the
            // package that writes the report, with that report's real access pattern.
            $table->string('environment', 20)->nullable()->after('incident_date');
            $table->string('impact_scope', 20)->nullable()->after('environment');
            $table->string('workaround', 20)->nullable()->after('impact_scope');
            $table->text('steps_to_reproduce')->nullable()->after('workaround');
            $table->text('error_text')->nullable()->after('steps_to_reproduce');
        });
    }

    /**
     * Deliberately empty, matching 2026_09_18_090000's rollback story: this migration is
     * additive, so the rollback is "redeploy the previous image and ignore the unused
     * columns" rather than dropping five columns of real reported data under incident
     * pressure.
     */
    public function down(): void {}
};
