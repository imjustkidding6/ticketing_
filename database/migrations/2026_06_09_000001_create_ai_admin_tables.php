<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('type')->default('global'); // global, portal, agent, department
            $table->string('name')->default('Default Prompt');
            $table->text('prompt');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
        });

        Schema::create('chat_message_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rating'); // thumbs_up, thumbs_down
            $table->text('comment')->nullable();
            $table->text('question')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_feedbacks');
        Schema::dropIfExists('ai_prompt_templates');
    }
};
