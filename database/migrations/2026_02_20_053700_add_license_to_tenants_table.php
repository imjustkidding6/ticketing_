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
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('license_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->json('settings')->nullable()->after('is_active');
            $table->timestamp('suspended_at')->nullable()->after('settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['license_id']);
            $table->dropColumn(['license_id', 'settings', 'suspended_at']);
        });
    }
};
