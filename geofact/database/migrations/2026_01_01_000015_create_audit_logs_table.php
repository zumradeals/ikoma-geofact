<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('actor_id', 36);
            $table->string('actor_role', 30);
            $table->char('organization_id', 36)->nullable();
            $table->string('action', 120);
            $table->string('resource_type', 60);
            $table->char('resource_id', 36)->nullable();
            $table->enum('result', ['success', 'rejected', 'forbidden']);
            $table->string('ip_address', 45)->nullable();   // IPv4 ou IPv6
            $table->string('user_agent', 255)->nullable();
            $table->json('payload')->nullable();  // contexte additionnel
            $table->dateTime('created_at', 3)->useCurrent();

            // Pas de updated_at — table immuable (DC-15)
            // Pas de FK — actor_id peut être system

            $table->index('actor_id', 'idx_actor');
            $table->index('organization_id', 'idx_organization');
            $table->index(['resource_type', 'resource_id'], 'idx_resource');
            $table->index('result', 'idx_result');
            $table->index('created_at', 'idx_created_at');
            $table->index('action', 'idx_action');
        });

        DB::statement('ALTER TABLE audit_logs ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
