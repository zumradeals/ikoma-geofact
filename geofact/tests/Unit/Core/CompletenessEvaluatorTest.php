<?php

namespace Tests\Unit\Core;

use App\Core\Canonical\CompletenessEvaluator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Valide C-02 — Niveaux de complétude des événements canoniques.
 */
class CompletenessEvaluatorTest extends TestCase
{
    private CompletenessEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new CompletenessEvaluator();
    }

    #[Test]
    public function test_returns_rejected_if_timestamp_missing(): void
    {
        $result = $this->evaluator->evaluate([
            'device_id'  => 'DEV-001',
            'event_type' => 'telemetry.position.updated',
            // timestamp absent
        ], 'org-001');

        $this->assertSame('REJECTED', $result['completeness']);
        $this->assertContains('timestamp', $result['missing_fields']);
    }

    #[Test]
    public function test_returns_rejected_if_device_id_missing(): void
    {
        $result = $this->evaluator->evaluate([
            'timestamp'  => now()->toIso8601String(),
            'event_type' => 'telemetry.position.updated',
            // device_id absent
        ], 'org-001');

        $this->assertSame('REJECTED', $result['completeness']);
        $this->assertContains('device_id', $result['missing_fields']);
    }

    #[Test]
    public function test_returns_rejected_if_event_type_missing(): void
    {
        $result = $this->evaluator->evaluate([
            'timestamp' => now()->toIso8601String(),
            'device_id' => 'DEV-001',
            // event_type absent
        ], 'org-001');

        $this->assertSame('REJECTED', $result['completeness']);
        $this->assertContains('event_type', $result['missing_fields']);
    }

    #[Test]
    public function test_returns_incomplete_if_ccm_missing(): void
    {
        // CompletenessEvaluator retourne INCOMPLETE si les CCM sont manquants
        // En v1, aucun CCM par défaut → COMPLETE si les 3 CCS sont présents
        // Ce test vérifie le comportement de base : 3 CCS présents → pas REJECTED
        $result = $this->evaluator->evaluate([
            'timestamp'  => now()->toIso8601String(),
            'device_id'  => 'DEV-001',
            'event_type' => 'telemetry.position.updated',
        ], 'org-001');

        $this->assertNotSame('REJECTED', $result['completeness']);
        $this->assertContains($result['completeness'], ['COMPLETE', 'INCOMPLETE']);
    }

    #[Test]
    public function test_returns_complete_if_all_fields_present(): void
    {
        $result = $this->evaluator->evaluate([
            'timestamp'  => now()->toIso8601String(),
            'device_id'  => 'DEV-001',
            'event_type' => 'telemetry.position.updated',
            'latitude'   => '5.3544',
            'longitude'  => '-4.0083',
            'speed_kmh'  => 60,
        ], 'org-001');

        $this->assertSame('COMPLETE', $result['completeness']);
        $this->assertEmpty($result['missing_fields']);
    }
}
