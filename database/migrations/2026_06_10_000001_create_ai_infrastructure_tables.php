<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();
            $table->foreignId('chat_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->string('model')->default('gpt-5');
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost', 10, 6)->default(0.000000);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('response_status')->default('success'); // success, failed, rate_limited, moderated
            $table->text('error_message')->nullable();
            $table->string('feature')->default('chat'); // chat, copilot, embed, web_search
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['model', 'created_at']);
        });

        Schema::create('ai_moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('moderation'); // moderation, prompt_injection
            $table->string('severity')->default('medium'); // low, medium, high, critical
            $table->text('input_text');
            $table->text('reason')->nullable();
            $table->string('action_taken')->default('blocked'); // blocked, flagged, sanitized
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
        });

        Schema::create('ai_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Fast, Balanced, Cheap, High Accuracy, Vision, Reasoning
            $table->string('slug')->unique();
            $table->string('model')->default('gpt-5');
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->unsignedInteger('max_tokens')->default(2000);
            $table->decimal('top_p', 3, 2)->default(1.00);
            $table->decimal('frequency_penalty', 3, 2)->default(0.00);
            $table->decimal('presence_penalty', 3, 2)->default(0.00);
            $table->json('enabled_tools')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_profiles');
        Schema::dropIfExists('ai_moderation_logs');
        Schema::dropIfExists('ai_usage_logs');
    }
};
