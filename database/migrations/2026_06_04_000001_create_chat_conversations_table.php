<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel')->default('portal'); // portal | agent
            $table->string('session_token', 64)->nullable();
            $table->string('title')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('client_id');
            $table->index('ticket_id');
            $table->index('status');
            $table->index('last_message_at');
            $table->index(['tenant_id', 'session_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
