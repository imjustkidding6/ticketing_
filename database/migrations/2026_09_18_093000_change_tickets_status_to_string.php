<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * @gate-approved: status ENUM -> varchar; Ticket::STATUSES becomes the single enumeration. Approved by product owner 2026-09-18.
 */
return new class extends Migration
{
    /**
     * tickets.status was a MySQL ENUM of the six original statuses, so adding a
     * seventh was a schema change every time. The enum never bought us safety:
     * it could not tell a legal move from an illegal one, and TRANSITIONS —
     * the rule that actually matters — has always lived in PHP and is enforced
     * in one place, TicketService::changeStatus(). Moving to varchar makes
     * Ticket::STATUSES the single enumeration, so a future status is a code
     * change with no migration at all.
     *
     * Widening ENUM -> VARCHAR(32) is value-preserving: every existing status
     * string is at most 11 characters and remains representable. The column
     * stays NOT NULL with the same 'open' default.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('status', 32)->default('open')->change();
        });
    }

    /**
     * Deliberately empty. Reverting to the ENUM would truncate any row holding
     * a status added since — which is the exact data loss the gate exists to
     * prevent. The rollback story is redeploying the previous image; the
     * varchar column accepts every value that image knows how to write.
     */
    public function down(): void {}
};
