<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wialon_unit_mappings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36);
            $table->bigInteger('wialon_unit_id')->unsigned();
            $table->string('wialon_unit_name', 150);
            $table->char('ikoma_vehicle_id', 36)->nullable();
            $table->char('ikoma_connector_id', 36);
            $table->integer('last_message_ts')->unsigned()->nullable()->comment('Unix timestamp du dernier message traité');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            // Index unique : une unité Wialon ne peut être mappée qu'une seule fois par organisation
            $table->unique(['organization_id', 'wialon_unit_id'], 'uq_wialon_unit_mappings_org_unit');

            $table->index('ikoma_connector_id', 'idx_wialon_unit_mappings_connector');
            $table->index('ikoma_vehicle_id', 'idx_wialon_unit_mappings_vehicle');
            $table->index('status', 'idx_wialon_unit_mappings_status');

            $table->foreign('organization_id', 'fk_wialon_unit_mappings_organization')
                  ->references('id')->on('organizations')
                  ->onDelete('CASCADE');

            $table->foreign('ikoma_vehicle_id', 'fk_wialon_unit_mappings_vehicle')
                  ->references('id')->on('vehicles')
                  ->onDelete('SET NULL');

            $table->foreign('ikoma_connector_id', 'fk_wialon_unit_mappings_connector')
                  ->references('id')->on('connectors')
                  ->onDelete('CASCADE');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE wialon_unit_mappings ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wialon_unit_mappings');
    }
};
