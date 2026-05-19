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
        // Colonne JSON chiffrée pour stocker les configs spécifiques au fournisseur
        // Ex. Wialon : {"wialon_token": "...", "wialon_base_url": "..."}
        DB::statement('ALTER TABLE connectors ADD COLUMN provider_config TEXT NULL AFTER contract_versions');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }
        DB::statement('ALTER TABLE connectors DROP COLUMN provider_config');
    }
};
