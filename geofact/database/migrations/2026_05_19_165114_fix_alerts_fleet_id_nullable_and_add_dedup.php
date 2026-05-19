<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // fleet_id nullable — les règles système n'ont pas toujours le fleet_id disponible
        DB::statement('ALTER TABLE alerts MODIFY fleet_id CHAR(36) NULL');

        // Colonne dedup_key pour éviter les alertes dupliquées sur la même condition
        Schema::table('alerts', function (Blueprint $table) {
            $table->string('dedup_key', 120)->nullable()->after('payload');
            $table->index('dedup_key', 'idx_alerts_dedup_key');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex('idx_alerts_dedup_key');
            $table->dropColumn('dedup_key');
        });
        DB::statement('ALTER TABLE alerts MODIFY fleet_id CHAR(36) NOT NULL');
    }
};
