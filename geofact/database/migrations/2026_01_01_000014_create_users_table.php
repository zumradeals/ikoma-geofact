<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36);
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 150)->unique();
            $table->string('password_hash', 255);  // bcrypt coût 12 minimum
            $table->enum('role', ['geofact_admin', 'org_admin', 'integrator', 'fleet_admin', 'supervisor', 'driver']);
            $table->json('fleet_ids')->nullable();  // fleets accessibles si fleet_admin/supervisor
            $table->tinyInteger('token_version')->unsigned()->default(1);
            $table->enum('status', ['pending', 'active', 'suspended', 'deleted'])->default('pending');
            $table->dateTime('last_login_at')->nullable();
            $table->char('created_by', 36);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();

            $table->index('organization_id', 'idx_organization');
            $table->index('role', 'idx_role');
            $table->index('status', 'idx_status');
            $table->index('email', 'idx_email');

            $table->foreign('organization_id', 'fk_user_organization')
                  ->references('id')->on('organizations')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        DB::statement('ALTER TABLE users ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
