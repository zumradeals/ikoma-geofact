<?php

namespace Tests\Unit\Rules;

use App\Core\Canonical\CanonicalEvent;
use App\Rules\SystemRules\RS01_OverspeedRule;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Valide C-04 — Règles système RS : non désactivables, MEDIUM/HIGH/CRITICAL.
 */
class RS01_OverspeedRuleTest extends TestCase
{
    private RS01_OverspeedRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new RS01_OverspeedRule(thresholdKmh: 90);
    }

    #[Test]
    public function test_triggers_alert_when_speed_exceeds_limit(): void
    {
        $event = $this->makeEvent(['speed_kmh' => 100]);

        $this->assertTrue($this->rule->applies($event));
        $alert = $this->rule->evaluate($event);

        $this->assertNotNull($alert);
        $this->assertSame('alert.overspeed.detected', $alert->event_type);
        $this->assertSame('RS', $alert->rule_type);
        $this->assertSame('RS01', $alert->rule_id);
    }

    #[Test]
    public function test_no_alert_below_limit(): void
    {
        $event = $this->makeEvent(['speed_kmh' => 80]);

        $this->assertTrue($this->rule->applies($event));
        $alert = $this->rule->evaluate($event);

        $this->assertNull($alert);
    }

    #[Test]
    public function test_severity_scales_with_overspeed_percentage(): void
    {
        // < 20% au-dessus → MEDIUM (90 * 1.19 = 107.1 km/h)
        $alertMedium = $this->rule->evaluate($this->makeEvent(['speed_kmh' => 105]));
        $this->assertSame('MEDIUM', $alertMedium->severity);

        // 20-40% au-dessus → HIGH (90 * 1.35 = 121.5 km/h)
        $alertHigh = $this->rule->evaluate($this->makeEvent(['speed_kmh' => 121]));
        $this->assertSame('HIGH', $alertHigh->severity);

        // ≥ 40% au-dessus → CRITICAL (90 * 1.45 = 130.5 km/h)
        $alertCritical = $this->rule->evaluate($this->makeEvent(['speed_kmh' => 135]));
        $this->assertSame('CRITICAL', $alertCritical->severity);
    }

    #[Test]
    public function test_system_rule_cannot_be_disabled(): void
    {
        // RS01 est non-désactivable (C-04.2) — getRuleType() retourne toujours 'RS'
        $this->assertSame('RS', $this->rule->getRuleType());
        $this->assertSame('RS01', $this->rule->getRuleId());

        // La règle s'applique à tout événement contenant speed_kmh
        $event = $this->makeEvent(['speed_kmh' => 200]);
        $this->assertTrue($this->rule->applies($event));

        // Même avec un seuil très élevé, elle reste présente et évaluable
        $strictRule = new RS01_OverspeedRule(thresholdKmh: 999);
        $this->assertSame('RS01', $strictRule->getRuleId());
    }

    #[Test]
    public function test_no_applies_when_speed_absent(): void
    {
        $event = $this->makeEvent([]);
        $this->assertFalse($this->rule->applies($event));
    }

    private function makeEvent(array $payload): CanonicalEvent
    {
        return new CanonicalEvent(
            eventId:        'evt-' . uniqid(),
            eventType:      'telemetry.speed.updated',
            connectorId:    'con-001',
            organizationId: 'org-001',
            deviceId:       'dev-001',
            vehicleId:      'veh-001',
            tripId:         null,
            timestamp:      Carbon::now(),
            receivedAt:     Carbon::now(),
            payload:        $payload,
            missingFields:  [],
            completeness:   'COMPLETE',
            rawRef:         'raw-001',
        );
    }
}
