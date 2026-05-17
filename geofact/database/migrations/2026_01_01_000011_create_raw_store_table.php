<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_store', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('connector_id', 36);
            $table->char('organization_id', 36);
            $table->dateTime('received_at', 3);
            $table->longText('payload_raw');  // données brutes exactes reçues
            $table->enum('payload_format', ['json', 'xml', 'csv', 'binary', 'unknown'])->default('json');
            $table->enum('flag', ['OK', 'INCOMPLETE', 'REJECTED', 'CORRUPTED', 'DUPLICATE_TRANSPORT'])->default('OK');
            $table->char('canonical_ref', 36)->nullable();  // référence TelemetryEvent si produit
            $table->dateTime('processed_at', 3)->nullable();

            // Pas de created_at / updated_at — table immuable (DC-11)

            $table->index('connector_id', 'idx_connector');
            $table->index('organization_id', 'idx_organization');
            $table->index('received_at', 'idx_received_at');
            $table->index('flag', 'idx_flag');
            $table->index('canonical_ref', 'idx_canonical_ref');
        });

        DB::statement('ALTER TABLE raw_store ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_store');
    }
};
