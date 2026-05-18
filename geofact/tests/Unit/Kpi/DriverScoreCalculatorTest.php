<?php

namespace Tests\Unit\Kpi;

use App\Kpi\Deferred\Calculators\DriverScoreCalculator;
use App\Kpi\Deferred\DfKpiVersioner;
use App\Models\KpiRecord;
use App\Models\TelemetryEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Valide C-05 — KPI Engine DF : calcul du driver_score.
 */
class DriverScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private DriverScoreCalculator $calculator;
    private Carbon $from;
    private Carbon $to;
    private string $driverId;
    private string $orgId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new DriverScoreCalculator();
        $this->from       = Carbon::now()->startOfDay();
        $this->to         = Carbon::now()->endOfDay();
        $this->driverId   = Str::uuid()->toString();
        $this->orgId      = Str::uuid()->toString();
    }

    #[Test]
    public function test_score_starts_at_100(): void
    {
        // Aucun événement pour ce driver → score = 100
        $result = $this->calculator->compute($this->driverId, $this->from, $this->to);

        $this->assertSame(100, (int) $result['value']);
        $this->assertSame('driver_score', $result['kpi_type']);
        $this->assertSame('driver', $result['scope_type']);
    }

    #[Test]
    public function test_overspeed_reduces_score(): void
    {
        // 2 overSpeed events → score = 100 - (2*3) = 94
        $this->insertTelemetry('alert.overspeed.detected', 2);

        $result = $this->calculator->compute($this->driverId, $this->from, $this->to);

        $this->assertSame(94, (int) $result['value']);
        $this->assertSame(2, $result['breakdown']['overspeed_count']);
    }

    #[Test]
    public function test_harsh_braking_reduces_score(): void
    {
        // 1 harsh_braking event → score = 100 - (1*2) = 98
        $this->insertTelemetry('alert.harsh.braking', 1);

        $result = $this->calculator->compute($this->driverId, $this->from, $this->to);

        $this->assertSame(98, (int) $result['value']);
    }

    #[Test]
    public function test_score_never_goes_below_zero(): void
    {
        // Beaucoup d'infractions → score ne peut pas être négatif
        $this->insertTelemetry('alert.overspeed.detected', 50);

        $result = $this->calculator->compute($this->driverId, $this->from, $this->to);

        $this->assertGreaterThanOrEqual(0, (int) $result['value']);
    }

    #[Test]
    public function test_new_version_created_on_recalculation(): void
    {
        $versioner = new DfKpiVersioner();
        $scopeId   = $this->driverId;

        $v1 = $versioner->nextVersion($scopeId, 'driver_score', $this->from, $this->to);
        $this->assertSame(1, $v1);

        // Simule l'insertion d'un premier record
        KpiRecord::create([
            'id'              => Str::uuid()->toString(),
            'organization_id' => $this->orgId,
            'scope_type'      => 'driver',
            'scope_id'        => $scopeId,
            'kpi_type'        => 'driver_score',
            'mode'            => 'DF',
            'period_from'     => $this->from,
            'period_to'       => $this->to,
            'value'           => 95,
            'unit'            => 'score',
            'version'         => 1,
            'computed_at'     => now(),
        ]);

        $v2 = $versioner->nextVersion($scopeId, 'driver_score', $this->from, $this->to);
        $this->assertSame(2, $v2);
    }

    private function insertTelemetry(string $eventType, int $count): void
    {
        // Force noon to avoid night-activity penalty (22h–06h) skewing score tests
        $daytime = Carbon::today()->setHour(12)->setMinute(0)->setSecond(0);

        for ($i = 0; $i < $count; $i++) {
            TelemetryEvent::insert([
                'id'              => Str::uuid()->toString(),
                'connector_id'    => Str::uuid()->toString(),
                'device_id'       => 'dev-001',
                'organization_id' => $this->orgId,
                'event_type'      => $eventType,
                'ts'              => $daytime->toDateTimeString(),
                'received_at'     => $daytime->toDateTimeString(),
                'completeness'    => 'COMPLETE',
                'payload'         => json_encode(['driver_id' => $this->driverId]),
            ]);
        }
    }
}
