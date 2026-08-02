<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learned_snippets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('question');
            $table->longText('answer');
            $table->longText('embedding')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learned_snippets');
    }
};
