<?php

namespace Tests\Unit\Core;

use App\Core\Canonical\CanonicalEvent;
use App\Core\Canonical\CanonicalEventFactory;
use App\Exceptions\CanonicalValidationException;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Valide C-01 — Isolation du Core & Canonical Event.
 */
class CanonicalEventTest extends TestCase
{
    #[Test]
    public function test_canonical_event_is_immutable(): void
    {
        $event = $this->makeEvent();

        // PHP readonly classes lèvent Error à la tentative de modification
        $this->expectException(\Error::class);

        // @phpstan-ignore-next-line
        $event->eventId = 'tampered';
    }

    #[Test]
    public function test_rejects_unknown_event_type(): void
    {
        $this->expectException(CanonicalValidationException::class);

        $factory = new CanonicalEventFactory();
        $connector = $this->makeConnector();

        $factory->create([
            'event_type' => 'unknown.invalid.type',
            'device_id'  => 'DEV-001',
            'timestamp'  => now()->toIso8601String(),
        ], $connector, 'raw-001');
    }

    #[Test]
    public function test_requires_three_ccs_fields(): void
    {
        // C-02 : timestamp, device_id, event_type sont les 3 CCS
        $evaluator = new \App\Core\Canonical\CompletenessEvaluator();

        $result = $evaluator->evaluate([
            // timestamp manquant intentionnellement
            'device_id'  => 'DEV-001',
            'event_type' => 'telemetry.position.updated',
        ], 'org-001');

        $this->assertSame('REJECTED', $result['completeness']);
        $this->assertContains('timestamp', $result['missing_fields']);
    }

    #[Test]
    public function test_valid_event_type_is_accepted(): void
    {
        $factory   = new CanonicalEventFactory();
        $connector = $this->makeConnector();

        $event = $factory->create([
            'event_type' => 'telemetry.position.updated',
            'device_id'  => 'DEV-001',
            'timestamp'  => now()->toIso8601String(),
        ], $connector, 'raw-001');

        $this->assertSame('telemetry.position.updated', $event->eventType);
        $this->assertNotEmpty($event->eventId);
    }

    private function makeEvent(): CanonicalEvent
    {
        return new CanonicalEvent(
            eventId:        'evt-001',
            eventType:      'telemetry.position.updated',
            connectorId:    'con-001',
            organizationId: 'org-001',
            deviceId:       'dev-001',
            vehicleId:      null,
            tripId:         null,
            timestamp:      Carbon::now(),
            receivedAt:     Carbon::now(),
            payload:        [],
            missingFields:  [],
            completeness:   'COMPLETE',
            rawRef:         'raw-001',
        );
    }

    private function makeConnector(): \App\Models\Connector
    {
        $connector = new \App\Models\Connector();
        $connector->id              = 'con-001';
        $connector->organization_id = 'org-001';
        $connector->provider_id     = 'test';
        return $connector;
    }
}
