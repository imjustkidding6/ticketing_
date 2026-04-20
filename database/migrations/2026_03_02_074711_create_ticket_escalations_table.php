<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('escalated_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('escalated_from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('escalated_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('from_tier', ['tier_1', 'tier_2', 'tier_3'])->nullable();
            $table->enum('to_tier', ['tier_1', 'tier_2', 'tier_3'])->nullable();
            $table->enum('trigger_type', ['manual', 'sla_breach', 'time_based', 'priority_upgrade'])->default('manual');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_escalations');
    }
};
