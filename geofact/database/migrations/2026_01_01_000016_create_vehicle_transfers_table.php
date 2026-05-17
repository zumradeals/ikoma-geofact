<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_transfers', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('vehicle_id', 36);
            $table->enum('transfer_type', ['inter_fleet', 'inter_organization']);
            $table->char('source_organization_id', 36);
            $table->char('source_fleet_id', 36);
            $table->char('target_organization_id', 36);
            $table->char('target_fleet_id', 36);
            $table->enum('historical_data_policy', ['stays_source', 'follows_vehicle']);  // immuable après validation
            $table->dateTime('effective_date');
            $table->enum('status', ['pending', 'validated', 'completed', 'rejected'])->default('pending');
            $table->char('initiated_by', 36);
            $table->dateTime('initiated_at')->useCurrent();
            $table->char('validated_by', 36)->nullable();
            $table->dateTime('validated_at')->nullable();
            $table->text('notes')->nullable();

            $table->index('vehicle_id', 'idx_vehicle');
            $table->index('status', 'idx_status');
            $table->index('source_organization_id', 'idx_source_org');
            $table->index('target_organization_id', 'idx_target_org');
            $table->index('effective_date', 'idx_effective_date');

            $table->foreign('vehicle_id', 'fk_transfer_vehicle')
                  ->references('id')->on('vehicles')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        DB::statement('ALTER TABLE vehicle_transfers ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_transfers');
    }
};
