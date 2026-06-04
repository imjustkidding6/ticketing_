<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Embedding of a closed ticket's problem + resolution, so the AI assistant
            // can semantically find how similar issues were resolved before.
            $table->longText('solution_embedding')->nullable();
            $table->timestamp('solution_embedded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['solution_embedding', 'solution_embedded_at']);
        });
    }
};
