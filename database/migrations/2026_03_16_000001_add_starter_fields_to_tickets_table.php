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
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('incident_date')->nullable()->after('description');
            $table->timestamp('preferred_service_date')->nullable()->after('incident_date');
            $table->boolean('is_false_alarm')->default(false)->after('preferred_service_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['incident_date', 'preferred_service_date', 'is_false_alarm']);
        });
    }
};
