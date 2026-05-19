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

        // Aligne l'ENUM fleet_type avec les valeurs du formulaire Filament
        DB::statement("ALTER TABLE fleets MODIFY fleet_type ENUM(
            'urban','intercity','logistics','passenger','mixed',
            'transport','mining','maintenance','executive','regional','custom'
        ) NULL DEFAULT NULL");

        // Ajoute 'inactive' dans status si absent
        DB::statement("ALTER TABLE fleets MODIFY status ENUM(
            'active','inactive','suspended','archived','deleted'
        ) NOT NULL DEFAULT 'active'");
    }

    public function down(): void {}
};
