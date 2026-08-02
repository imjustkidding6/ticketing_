<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->longText('embedding')->nullable();
            $table->timestamp('embedded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'embedded_at']);
        });
    }
};
