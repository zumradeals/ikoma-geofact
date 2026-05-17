<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36);
            $table->char('fleet_id', 36);
            $table->char('vehicle_id', 36);
            $table->char('driver_id', 36)->nullable();
            $table->char('trip_id', 36)->nullable();
            $table->string('rule_id', 30);  // RS-01, RMC-CLIENT_X-01...
            $table->enum('rule_type', ['RS', 'RMC']);
            $table->string('event_type', 80);  // taxonomie C-09
            $table->enum('severity', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])->default('MEDIUM');
            $table->enum('status', ['open', 'triggered', 'delivered', 'acknowledged', 'escalated', 'resolved', 'expired'])->default('open');
            $table->dateTime('triggered_at');
            $table->dateTime('acknowledged_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();  // obligatoire si resolved
            $table->dateTime('escalated_at')->nullable();
            $table->json('payload')->nullable();  // contexte de l'alerte
            $table->dateTime('created_at')->useCurrent();

            $table->index('organization_id', 'idx_alerts_organization');
            $table->index('vehicle_id', 'idx_alerts_vehicle');
            $table->index('status', 'idx_alerts_status');
            $table->index('severity', 'idx_alerts_severity');
            $table->index('triggered_at', 'idx_alerts_triggered_at');
            $table->index('rule_id', 'idx_alerts_rule');
            $table->index('event_type', 'idx_alerts_event_type');
            $table->index(['vehicle_id', 'status', 'triggered_at'], 'idx_alerts_vehicle_status');

            $table->foreign('organization_id', 'fk_alert_organization')
                  ->references('id')->on('organizations')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');

            $table->foreign('vehicle_id', 'fk_alert_vehicle')
                  ->references('id')->on('vehicles')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');

            $table->foreign('trip_id', 'fk_alert_trip')
                  ->references('id')->on('trips')
                  ->onDelete('SET NULL')->onUpdate('CASCADE');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') { DB::statement('ALTER TABLE alerts ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'); }
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
