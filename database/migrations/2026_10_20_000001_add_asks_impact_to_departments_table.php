<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Does a ticket in this department get asked the IMPACT half of the declared
            // detail set (who is affected, is there a workaround)?
            //
            // A second flag rather than a reading of `is_technical`: a department can
            // handle technical work and still not want the impact questions, and a
            // non-technical department that owns outages very much does want them.
            //
            // DEFAULT TRUE — the OPPOSITE of the sibling `is_technical`, and the whole
            // reason this migration needs no backfill: every existing department keeps
            // asking exactly what it asks today, and a workspace opts OUT per department.
            // Defaulting false would, on the deploy itself, silently strip the priority
            // rubric's two decisive inputs (breadth of impact, workaround existence) from
            // every department in every workspace — after which PRIORITY_UNCERTAINTY
            // resolves scope downward and `critical` stops being reachable. The safe
            // reading of an unanswered question here is "keep asking", not "ask less",
            // which is why the default points the other way from `is_technical`'s.
            //
            // NO DATA STATEMENT. The default IS the migration of existing rows.
            $table->boolean('asks_impact')->default(true)->after('is_technical');
        });
    }

    /**
     * Deliberately empty, matching the siblings 2026_10_08_000001 and 2026_10_09_000001:
     * additive, so the rollback is "redeploy the previous image and ignore the unused
     * column" rather than dropping a column of real configuration under incident pressure.
     */
    public function down(): void {}
};
