<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_current_positions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36);
            $table->char('vehicle_id', 36);
            $table->char('connector_id', 36)->nullable();
            $table->char('device_id', 36)->nullable();
            $table->char('telemetry_event_id', 36)->nullable();
            $table->string('provider_id', 60)->nullable();
            $table->string('provider_unit_id', 80)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed_kmh', 6, 2)->nullable();
            $table->smallInteger('heading')->nullable();
            $table->tinyInteger('ignition')->nullable();
            $table->dateTime('position_ts', 3);
            $table->dateTime('received_at', 3)->nullable();
            $table->enum('freshness_status', ['fresh', 'delayed', 'stale', 'unknown'])->default('unknown');
            $table->enum('source_status', ['official', 'stale', 'unknown'])->default('official');
            $table->unsignedInteger('raw_age_seconds')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'vehicle_id'], 'uq_vehicle_current_positions_org_vehicle');
            $table->index('organization_id', 'idx_vehicle_current_positions_org');
            $table->index('vehicle_id', 'idx_vehicle_current_positions_vehicle');
            $table->index('position_ts', 'idx_vehicle_current_positions_position_ts');
            $table->index('freshness_status', 'idx_vehicle_current_positions_freshness');
            $table->index('provider_unit_id', 'idx_vehicle_current_positions_provider_unit');

            $table->foreign('organization_id', 'fk_vehicle_current_positions_org')
                ->references('id')->on('organizations')
                ->onDelete('RESTRICT')->onUpdate('CASCADE');

            $table->foreign('vehicle_id', 'fk_vehicle_current_positions_vehicle')
                ->references('id')->on('vehicles')
                ->onDelete('RESTRICT')->onUpdate('CASCADE');

            $table->foreign('connector_id', 'fk_vehicle_current_positions_connector')
                ->references('id')->on('connectors')
                ->onDelete('RESTRICT')->onUpdate('CASCADE');

            $table->foreign('device_id', 'fk_vehicle_current_positions_device')
                ->references('id')->on('devices')
                ->onDelete('RESTRICT')->onUpdate('CASCADE');

            $table->foreign('telemetry_event_id', 'fk_vehicle_current_positions_event')
                ->references('id')->on('telemetry_events')
                ->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE vehicle_current_positions ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_current_positions');
    }
};
