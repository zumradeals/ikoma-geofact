<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_policies', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36)->index('delivery_policies_org_idx');
            $table->string('event_type_filter', 100)->nullable();
            $table->enum('min_severity', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])->default('MEDIUM');
            $table->json('channels')->nullable();
            $table->string('recipient_email', 150)->nullable();
            $table->string('recipient_phone', 30)->nullable();
            $table->string('recipient_webhook_url', 255)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_policies');
    }
};
