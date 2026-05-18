<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE connectors MODIFY certified_by CHAR(36) NULL DEFAULT NULL');
        DB::statement('ALTER TABLE connectors MODIFY certified_at DATETIME NULL DEFAULT NULL');
        DB::statement('ALTER TABLE connectors MODIFY contract_versions JSON NULL');
        DB::statement("ALTER TABLE connectors MODIFY connector_type ENUM('http_push','mqtt','websocket','polling') NOT NULL DEFAULT 'polling'");
    }

    public function down(): void
    {
        // Intentionally left blank — reversing to NOT NULL is unsafe on live data
    }
};
