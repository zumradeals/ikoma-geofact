<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_configs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36)->nullable(); // NULL = défaut système (super_admin)
            $table->string('rule_id', 30);                  // RS01, RS02, RS03, RS04, RS05
            $table->json('params');                          // paramètres configurables
            $table->boolean('is_enabled')->default(true);   // activer/désactiver la règle
            $table->char('updated_by', 36)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Une seule config par (org, règle) — NULL org = défaut système
            $table->unique(['organization_id', 'rule_id'], 'uq_rule_configs_org_rule');
            $table->index('rule_id', 'idx_rule_configs_rule_id');
            $table->index('organization_id', 'idx_rule_configs_org');

            $table->foreign('organization_id', 'fk_rule_config_organization')
                  ->references('id')->on('organizations')
                  ->onDelete('CASCADE')->onUpdate('CASCADE');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE rule_configs ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }

        // Insérer les valeurs par défaut système (organization_id = NULL)
        $defaults = [
            ['rule_id' => 'RS01', 'params' => json_encode(['threshold_kmh' => 90, 'severity_medium_pct' => 20, 'severity_high_pct' => 40])],
            ['rule_id' => 'RS02', 'params' => json_encode(['threshold_hours' => 4])],
            ['rule_id' => 'RS03', 'params' => json_encode(['deceleration_ms2' => 7.0])],
            ['rule_id' => 'RS04', 'params' => json_encode(['alert_on_entry' => true, 'alert_on_exit' => false])],
            ['rule_id' => 'RS05', 'params' => json_encode(['threshold_km' => 10000])],
        ];

        foreach ($defaults as $d) {
            DB::table('rule_configs')->insert([
                'id'              => \Illuminate\Support\Str::uuid()->toString(),
                'organization_id' => null,
                'rule_id'         => $d['rule_id'],
                'params'          => $d['params'],
                'is_enabled'      => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_configs');
    }
};
