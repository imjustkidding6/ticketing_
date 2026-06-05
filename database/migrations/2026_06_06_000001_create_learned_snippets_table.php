<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Q&A snippets an agent explicitly confirmed as helpful in the AI chat.
        // Opt-in (ai_learn_from_chat) and reviewable, so the learning corpus stays clean.
        Schema::create('learned_snippets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('question');
            $table->longText('answer');
            $table->longText('embedding')->nullable(); // JSON-encoded vector of the question
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learned_snippets');
    }
};
