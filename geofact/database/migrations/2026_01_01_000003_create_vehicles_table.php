<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('fleet_id', 36);
            $table->char('organization_id', 36);  // déduit de fleet_id — jamais saisi manuellement
            $table->string('name', 100);
            $table->string('plate', 20);
            $table->string('brand', 80)->nullable();
            $table->string('model', 80)->nullable();
            $table->smallInteger('year')->unsigned()->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'transferred', 'archived', 'deleted'])->default('pending');
            $table->char('created_by', 36);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('archived_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->unique(['plate', 'organization_id'], 'uq_plate_org');
            $table->index('fleet_id', 'idx_fleet');
            $table->index('organization_id', 'idx_organization');
            $table->index('status', 'idx_status');
            $table->index('plate', 'idx_plate');

            $table->foreign('fleet_id', 'fk_vehicle_fleet')
                  ->references('id')->on('fleets')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');

            $table->foreign('organization_id', 'fk_vehicle_organization')
                  ->references('id')->on('organizations')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        DB::statement('ALTER TABLE vehicles ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
