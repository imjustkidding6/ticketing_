<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bugs reported to the AI Assistant that internal staff can escalate to the
        // "AI Programmer" (Claude Code) for a fix. See CLAUDE.md "AI Bug Fixing".
        Schema::create('bug_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('chat_conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->text('steps_to_reproduce')->nullable();
            $table->string('area')->nullable();                  // e.g. "tickets/SLA"
            $table->string('severity')->default('medium');       // low|medium|high|critical
            $table->string('status')->default('new');            // new|triaged|escalated|pr_opened|merged|closed|rejected
            $table->unsignedBigInteger('github_issue_number')->nullable();
            $table->string('github_pr_url')->nullable();
            $table->json('ai_triage')->nullable();
            $table->string('user_notified_status')->nullable();  // last status surfaced to the reporter
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_reports');
    }
};
