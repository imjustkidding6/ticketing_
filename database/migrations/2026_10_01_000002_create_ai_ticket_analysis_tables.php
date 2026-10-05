<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two tables behind ANALYZE TICKET: one row per analysis, one row per verdict.
 *
 * Both carry `tenant_id` even though a verdict could be reached through its analysis:
 * `BelongsToTenant` scopes on the model's own column, so a child without it would be
 * unscoped and the only thing separating workspaces would be a join nobody wrote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_ticket_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            // varchar, not an enum: AiTicketAnalysis::STATUSES is the only enumeration,
            // so adding a refusal reason stays a code change (the tickets.status lesson).
            $table->string('status')->default('succeeded');
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->decimal('estimated_cost', 12, 6)->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->json('suggestions')->nullable();
            $table->json('agent_recommendations')->nullable();
            $table->json('context_ticket_ids')->nullable();
            // The join key into ai_usage_logs. Cost per ticket must not be reconstructed
            // by matching timestamps.
            $table->json('usage_log_ids')->nullable();
            $table->json('rejected_fields')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'ticket_id']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('ai_suggestion_verdicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_ticket_analysis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('suggestion_type');
            $table->string('verdict');
            // Hashes, not the text: the engineer's final wording already lives on the
            // ticket, and a second copy here would be another thing to scope and delete.
            $table->char('suggested_hash', 64)->nullable();
            $table->char('applied_hash', 64)->nullable();
            $table->decimal('edit_ratio', 5, 4)->nullable();
            $table->unsignedSmallInteger('minutes_saved')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            // One verdict per suggestion; changing your mind updates the row.
            // Named explicitly: the generated name is 66 characters and MySQL caps
            // identifiers at 64, so the default errors out at migrate time.
            $table->unique(['ai_ticket_analysis_id', 'suggestion_type'], 'ai_verdicts_analysis_type_unique');
            $table->index(['tenant_id', 'suggestion_type', 'verdict']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_suggestion_verdicts');
        Schema::dropIfExists('ai_ticket_analyses');
    }
};
