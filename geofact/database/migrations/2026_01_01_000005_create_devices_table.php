<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('vehicle_id', 36)->nullable();  // NULL si non encore affecté
            $table->char('organization_id', 36);
            $table->string('imei', 20);
            $table->string('provider_id', 60);  // wialon|traccar|teltonika|custom
            $table->string('serial_number', 80)->nullable();
            $table->string('firmware_version', 40)->nullable();
            $table->enum('status', ['pending', 'active', 'disconnected', 'error', 'tampered', 'decommissioned', 'deleted'])->default('pending');
            $table->dateTime('last_seen_at')->nullable();
            $table->char('created_by', 36);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('imei', 'uq_imei');
            $table->index('vehicle_id', 'idx_devices_vehicle');
            $table->index('organization_id', 'idx_devices_organization');
            $table->index('status', 'idx_devices_status');
            $table->index('last_seen_at', 'idx_devices_last_seen');
            $table->index('imei', 'idx_devices_imei');

            $table->foreign('vehicle_id', 'fk_device_vehicle')
                  ->references('id')->on('vehicles')
                  ->onDelete('SET NULL')->onUpdate('CASCADE');

            $table->foreign('organization_id', 'fk_device_organization')
                  ->references('id')->on('organizations')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') { DB::statement('ALTER TABLE devices ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'); }
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
