<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable()->after('expires_at');
            $table->timestamp('reactivated_at')->nullable()->after('revoked_at');

            // Notification flags — stamped when each warning is sent so we don't double-send
            $table->timestamp('expiry_warning_sent_at')->nullable()->after('reactivated_at');
            $table->timestamp('grace_warning_sent_at')->nullable()->after('expiry_warning_sent_at');
            $table->timestamp('expired_notification_sent_at')->nullable()->after('grace_warning_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn([
                'revoked_at',
                'reactivated_at',
                'expiry_warning_sent_at',
                'grace_warning_sent_at',
                'expired_notification_sent_at',
            ]);
        });
    }
};
