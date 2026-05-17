<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_records', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36);
            $table->enum('scope_type', ['organization', 'fleet', 'vehicle', 'driver']);
            $table->char('scope_id', 36);
            $table->string('kpi_type', 80);  // driver_score, fleet_utilization_rate...
            $table->enum('mode', ['RT', 'DF']);
            $table->dateTime('period_from')->nullable();  // NULL pour RT
            $table->dateTime('period_to')->nullable();
            $table->decimal('value', 15, 4);
            $table->string('unit', 20)->nullable();  // km, %, score, minutes...
            $table->tinyInteger('version')->unsigned()->default(1);
            $table->dateTime('computed_at')->useCurrent();
            $table->char('scheduler_run_id', 36)->nullable();  // référence run DF

            // Pas de updated_at — table immuable (DC-12)

            $table->index('organization_id', 'idx_organization');
            $table->index(['scope_type', 'scope_id'], 'idx_scope');
            $table->index('kpi_type', 'idx_kpi_type');
            $table->index('mode', 'idx_mode');
            $table->index(['period_from', 'period_to'], 'idx_period');
            $table->index('computed_at', 'idx_computed_at');
            $table->index(['scope_id', 'kpi_type', 'period_from'], 'idx_scope_kpi_period');
        });

        DB::statement('ALTER TABLE kpi_records ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_records');
    }
};
