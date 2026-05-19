<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36);
            $table->enum('report_type', ['fleet', 'driver', 'vehicle']);
            $table->enum('status', ['pending', 'generating', 'ready', 'failed'])->default('pending');
            $table->dateTime('period_from');
            $table->dateTime('period_to');
            $table->string('title', 200);
            $table->string('file_path', 500)->nullable();
            $table->text('ai_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->char('generated_by', 36)->nullable();
            $table->dateTime('generated_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('organization_id', 'idx_reports_organization');
            $table->index('report_type', 'idx_reports_type');
            $table->index('status', 'idx_reports_status');
            $table->index('created_at', 'idx_reports_created_at');

            $table->foreign('organization_id', 'fk_report_organization')
                  ->references('id')->on('organizations')
                  ->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE reports ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
