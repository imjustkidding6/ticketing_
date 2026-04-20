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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('support_tier', ['tier_1', 'tier_2', 'tier_3'])->nullable()->after('is_admin');
            $table->boolean('is_available')->default(true)->after('support_tier');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete()->after('is_available');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['support_tier', 'is_available', 'department_id', 'deleted_at']);
        });
    }
};
