<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleets', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36);
            $table->string('name', 150);
            $table->enum('fleet_type', ['urban', 'intercity', 'logistics', 'passenger', 'mixed', 'transport', 'mining', 'maintenance', 'executive', 'regional', 'custom'])->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended', 'archived', 'deleted'])->default('active');
            $table->char('created_by', 36);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();

            $table->unique(['organization_id', 'name'], 'uq_fleet_name');
            $table->index('organization_id', 'idx_fleets_organization');
            $table->index('status', 'idx_fleets_status');
            $table->index('fleet_type', 'idx_fleets_type');

            $table->foreign('organization_id', 'fk_fleet_organization')
                  ->references('id')->on('organizations')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') { DB::statement('ALTER TABLE fleets ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'); }
    }

    public function down(): void
    {
        Schema::dropIfExists('fleets');
    }
};
