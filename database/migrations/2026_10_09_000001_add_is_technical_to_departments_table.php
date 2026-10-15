<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Does a ticket in this department get asked for TECHNICAL details?
            //
            // "Provision a laptop for a new starter" has no environment, no reproduction
            // steps and no error text, and no amount of form design makes it have them —
            // requiring Environment on an HR payroll query teaches agents to pick any value
            // to clear the form, which is junk that looks like data. So applicability is a
            // property of the department, and App\Support\TicketTechnicalFields decides per
            // field which group it belongs to ('all' vs 'technical').
            //
            // DEFAULT FALSE, deliberately: the safe reading of an unanswered question is
            // "this department is not technical", which asks for less rather than
            // fabricating a requirement. The universal pair (who is affected, is there a
            // workaround) is NOT gated on this — an HR fault hitting everyone is still more
            // urgent than one hitting one person.
            //
            // NO BACKFILL IN THIS MIGRATION. Existing workspaces are brought up to date by
            // DepartmentSeeder (which matches on the default department CODE, not a name,
            // and only ever sets the flag true so an admin's own choice survives a re-seed)
            // and by the toggle on the department form. A data statement in a migration is
            // this team's hard stop, and a name match is the weakest possible key for one.
            $table->boolean('is_technical')->default(false)->after('is_default');
        });
    }

    /**
     * Deliberately empty, matching the sibling 2026_10_08_000001: additive, so the rollback
     * is "redeploy the previous image and ignore the unused column".
     */
    public function down(): void {}
};
