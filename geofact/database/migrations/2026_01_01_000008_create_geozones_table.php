<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geozones', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36);
            $table->char('fleet_id', 36)->nullable();  // NULL = applicable à toute l'org
            $table->string('name', 100);
            $table->enum('zone_type', ['authorized', 'restricted', 'depot', 'customer', 'alert', 'custom'])->default('custom');
            $table->json('geometry');  // GeoJSON Polygon ou Circle
            $table->smallInteger('max_stay_minutes')->unsigned()->nullable();
            $table->time('active_from')->nullable();
            $table->time('active_to')->nullable();
            $table->tinyInteger('version')->unsigned()->default(1);
            $table->enum('status', ['draft', 'active', 'suspended', 'archived', 'deleted'])->default('draft');
            $table->char('created_by', 36);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('organization_id', 'idx_geozones_organization');
            $table->index('fleet_id', 'idx_geozones_fleet');
            $table->index('status', 'idx_geozones_status');
            $table->index('zone_type', 'idx_geozones_type');
            $table->index(['id', 'version'], 'idx_geozones_version');

            $table->foreign('organization_id', 'fk_geozone_organization')
                  ->references('id')->on('organizations')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') { DB::statement('ALTER TABLE geozones ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'); }
    }

    public function down(): void
    {
        Schema::dropIfExists('geozones');
    }
};
