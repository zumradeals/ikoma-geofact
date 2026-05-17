<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connectors', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36);
            $table->string('provider_id', 60);
            $table->enum('connector_type', ['CENTRAL', 'EDGE'])->default('CENTRAL');
            $table->string('token_hash', 255);  // hash bcrypt du JWT
            $table->tinyInteger('token_version')->unsigned()->default(1);
            $table->char('certified_by', 36);  // geofact_admin user_id
            $table->dateTime('certified_at');
            $table->enum('status', ['pending', 'active', 'suspended', 'revoked'])->default('pending');
            $table->json('contract_versions');  // {"C-09":"v1.0","C-10":"v1.0"}
            $table->dateTime('last_sync_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('organization_id', 'idx_organization');
            $table->index('status', 'idx_status');
            $table->index('provider_id', 'idx_provider');
            $table->index('last_sync_at', 'idx_last_sync');

            $table->foreign('organization_id', 'fk_connector_organization')
                  ->references('id')->on('organizations')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        DB::statement('ALTER TABLE connectors ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        Schema::dropIfExists('connectors');
    }
};
