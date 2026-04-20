<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `tickets` MODIFY `priority` ENUM('low','medium','high','critical') NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE `tickets` SET `priority` = 'medium' WHERE `priority` IS NULL");
        DB::statement("ALTER TABLE `tickets` MODIFY `priority` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium'");
    }
};
