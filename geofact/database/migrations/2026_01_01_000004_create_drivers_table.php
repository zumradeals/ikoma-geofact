<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36);
            $table->char('fleet_id', 36)->nullable();  // optionnel — conducteur peut être multi-fleet
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('phone', 20)->nullable();  // format E.164
            $table->string('license_number', 50)->nullable();
            $table->date('license_expiry')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'archived', 'deleted'])->default('pending');
            $table->char('created_by', 36);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();

            $table->index('organization_id', 'idx_organization');
            $table->index('fleet_id', 'idx_fleet');
            $table->index('status', 'idx_status');
            $table->index('license_expiry', 'idx_license_expiry');

            $table->foreign('organization_id', 'fk_driver_organization')
                  ->references('id')->on('organizations')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        DB::statement('ALTER TABLE drivers ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
