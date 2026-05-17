<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insights', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36);
            $table->enum('scope_type', ['event', 'trip', 'vehicle', 'driver', 'fleet', 'organization']);
            $table->char('scope_id', 36);
            $table->enum('insight_type', ['anomaly', 'trend', 'performance', 'alert', 'summary']);
            $table->char('language', 2)->default('fr');
            $table->text('insight_text');
            $table->enum('confidence_level', ['high', 'medium', 'low'])->default('medium');
            $table->json('source_kpis')->nullable();    // [kpi_record_id, ...]
            $table->json('source_events')->nullable();  // [telemetry_event_id, ...]
            $table->tinyInteger('version')->unsigned()->default(1);
            $table->dateTime('generated_at')->useCurrent();

            $table->index('organization_id', 'idx_organization');
            $table->index(['scope_type', 'scope_id'], 'idx_scope');
            $table->index('insight_type', 'idx_type');
            $table->index('generated_at', 'idx_generated_at');
            $table->index('confidence_level', 'idx_confidence');
        });

        DB::statement('ALTER TABLE insights ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        Schema::dropIfExists('insights');
    }
};
