<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_events', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('connector_id', 36);
            $table->char('device_id', 36);
            $table->char('vehicle_id', 36)->nullable();
            $table->char('organization_id', 36);
            $table->char('trip_id', 36)->nullable();
            $table->string('event_type', 80);  // taxonomie C-09
            $table->dateTime('ts', 3);          // précision milliseconde
            $table->dateTime('received_at', 3);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('speed_kmh', 6, 2)->nullable();
            $table->smallInteger('heading')->nullable();  // 0-359 degrés
            $table->decimal('altitude_m', 8, 2)->nullable();
            $table->decimal('fuel_level_pct', 5, 2)->nullable();
            $table->decimal('temperature_celsius', 5, 2)->nullable();
            $table->tinyInteger('ignition')->nullable();
            $table->json('payload');
            $table->enum('completeness', ['COMPLETE', 'INCOMPLETE', 'REJECTED'])->default('COMPLETE');
            $table->json('missing_fields')->nullable();
            $table->char('raw_ref', 36)->nullable();  // référence Raw Store

            // Pas de updated_at — table immuable (DC-10)

            $table->index('vehicle_id', 'idx_telemetry_events_vehicle');
            $table->index('device_id', 'idx_telemetry_events_device');
            $table->index('organization_id', 'idx_telemetry_events_organization');
            $table->index('trip_id', 'idx_telemetry_events_trip');
            $table->index('ts', 'idx_telemetry_events_ts');
            $table->index('event_type', 'idx_telemetry_events_event_type');
            $table->index('completeness', 'idx_telemetry_events_completeness');
            $table->index(['vehicle_id', 'ts'], 'idx_telemetry_events_vehicle_ts');

            $table->foreign('device_id', 'fk_telemetry_device')
                  ->references('id')->on('devices')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');

            $table->foreign('organization_id', 'fk_telemetry_organization')
                  ->references('id')->on('organizations')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') { DB::statement('ALTER TABLE telemetry_events ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'); }
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_events');
    }
};
