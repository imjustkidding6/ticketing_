<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->unsignedInteger('duration_days')->default(365)->after('grace_days');
            $table->timestamp('expires_at')->nullable()->change();
        });

        DB::table('licenses')
            ->whereNotNull('expires_at')
            ->whereNotNull('issued_at')
            ->update([
                'duration_days' => DB::raw('DATEDIFF(expires_at, issued_at)'),
            ]);

        DB::table('licenses')
            ->where('status', 'pending')
            ->update(['expires_at' => null]);
    }

    public function down(): void
    {
        DB::table('licenses')
            ->whereNull('expires_at')
            ->whereNotNull('issued_at')
            ->update([
                'expires_at' => DB::raw('DATE_ADD(issued_at, INTERVAL duration_days DAY)'),
            ]);

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('duration_days');
            $table->timestamp('expires_at')->nullable(false)->change();
        });
    }
};
