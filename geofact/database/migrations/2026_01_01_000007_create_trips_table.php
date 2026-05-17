<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('vehicle_id', 36);
            $table->char('driver_id', 36)->nullable();
            $table->char('organization_id', 36);
            $table->char('fleet_id', 36);
            $table->enum('status', ['pending', 'active', 'paused', 'completed', 'cancelled', 'anomalous'])->default('pending');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->smallInteger('duration_minutes')->unsigned()->nullable();  // calculé à la complétion
            $table->decimal('distance_km', 10, 3)->nullable();                // calculé à la complétion
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->decimal('end_latitude', 10, 7)->nullable();
            $table->decimal('end_longitude', 10, 7)->nullable();
            $table->text('anomaly_note')->nullable();  // requis si status=anomalous
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('vehicle_id', 'idx_trips_vehicle');
            $table->index('driver_id', 'idx_trips_driver');
            $table->index('organization_id', 'idx_trips_organization');
            $table->index('fleet_id', 'idx_trips_fleet');
            $table->index('status', 'idx_trips_status');
            $table->index('started_at', 'idx_trips_started_at');
            $table->index('ended_at', 'idx_trips_ended_at');
            $table->index(['vehicle_id', 'status'], 'idx_trips_vehicle_status');

            $table->foreign('vehicle_id', 'fk_trip_vehicle')
                  ->references('id')->on('vehicles')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');

            $table->foreign('driver_id', 'fk_trip_driver')
                  ->references('id')->on('drivers')
                  ->onDelete('SET NULL')->onUpdate('CASCADE');

            $table->foreign('organization_id', 'fk_trip_organization')
                  ->references('id')->on('organizations')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');

            $table->foreign('fleet_id', 'fk_trip_fleet')
                  ->references('id')->on('fleets')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') { DB::statement('ALTER TABLE trips ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'); }
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
