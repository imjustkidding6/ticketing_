<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_ticket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['ticket_id', 'product_id']);
        });

        // Migrate existing product_id data into pivot
        DB::statement('
            INSERT INTO product_ticket (ticket_id, product_id, created_at, updated_at)
            SELECT id, product_id, NOW(), NOW()
            FROM tickets
            WHERE product_id IS NOT NULL
        ');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });

        Schema::dropIfExists('product_ticket');
    }
};
