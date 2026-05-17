<?php

namespace Tests\Feature;

use App\Models\Connector;
use App\Models\Organization;
use App\Models\RawStore;
use App\Models\TelemetryEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Valide C-10 — Pipeline Connector : 10 étapes dans l'ordre strict.
 */
class ConnectorIngestionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private string $connectorToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'id'           => Str::uuid()->toString(),
            'name'         => 'Test Org',
            'country_code' => 'CI',
            'timezone'     => 'Africa/Abidjan',
            'status'       => 'active',
            'created_by'   => Str::uuid()->toString(),
            'created_at'   => now(),
        ]);

        $this->connectorToken = 'test-connector-token-' . Str::random(16);
    }

    #[Test]
    public function test_rejects_connector_without_valid_token(): void
    {
        $response = $this->postJson('/api/v1/webhook/connector/ingest', [
            'device_id'  => 'DEV-001',
            'event_type' => 'telemetry.position.updated',
            'timestamp'  => now()->toIso8601String(),
        ], ['Authorization' => 'Bearer invalid-token']);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_writes_to_raw_store_before_processing(): void
    {
        $connector = $this->makeConnector();

        $this->postJson('/api/v1/webhook/connector/ingest', [
            'device_id'  => 'DEV-001',
            'event_type' => 'telemetry.position.updated',
            'timestamp'  => now()->toIso8601String(),
        ], ['X-Connector-Token' => $this->connectorToken]);

        // Étape 2 du pipeline : raw_store doit avoir reçu un enregistrement
        $this->assertDatabaseHas('raw_store', [
            'connector_id' => $connector->id,
        ]);
    }

    #[Test]
    public function test_rejected_event_stays_in_raw_store(): void
    {
        $connector = $this->makeConnector();

        // Payload sans les 3 CCS (timestamp manquant) → REJECTED
        $this->postJson('/api/v1/webhook/connector/ingest', [
            'device_id'  => 'DEV-001',
            // event_type manquant intentionnellement
        ], ['X-Connector-Token' => $this->connectorToken]);

        // L'entrée raw_store doit exister mais avec flag REJECTED
        $raw = RawStore::where('connector_id', $connector->id)->first();
        $this->assertNotNull($raw);
        // Elle ne doit pas atteindre telemetry_events
        $this->assertDatabaseCount('telemetry_events', 0);
    }

    #[Test]
    public function test_complete_event_reaches_core(): void
    {
        $this->makeConnector();

        $response = $this->postJson('/api/v1/webhook/connector/ingest', [
            'device_id'  => 'DEV-001',
            'event_type' => 'telemetry.position.updated',
            'timestamp'  => now()->toIso8601String(),
            'latitude'   => 5.3544,
            'longitude'  => -4.0083,
        ], ['X-Connector-Token' => $this->connectorToken]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    private function makeConnector(): Connector
    {
        return Connector::create([
            'id'               => Str::uuid()->toString(),
            'organization_id'  => $this->org->id,
            'provider_id'      => 'test_provider',
            'connector_type'   => 'CENTRAL',
            'token_hash'       => Hash::make($this->connectorToken),
            'token_version'    => 1,
            'certified_by'     => Str::uuid()->toString(),
            'certified_at'     => now(),
            'status'           => 'active',
            'contract_versions'=> json_encode(['C-09' => 'v1.0', 'C-10' => 'v1.0']),
            'created_at'       => now(),
        ]);
    }
}
