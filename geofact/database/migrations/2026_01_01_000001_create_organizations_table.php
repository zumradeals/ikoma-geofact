<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 150);
            $table->char('country_code', 2)->default('CI');
            $table->string('timezone', 60)->default('Africa/Abidjan');
            $table->enum('status', ['active', 'suspended', 'deleted'])->default('active');
            $table->char('created_by', 36);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();

            $table->index('status', 'idx_organizations_status');
            $table->index('country_code', 'idx_organizations_country');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') { DB::statement('ALTER TABLE organizations ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'); }
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
