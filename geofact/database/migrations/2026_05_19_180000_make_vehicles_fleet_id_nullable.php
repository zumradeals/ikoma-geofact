<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE vehicles MODIFY fleet_id CHAR(36) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vehicles MODIFY fleet_id CHAR(36) NOT NULL');
    }
};
