<?php

namespace App\Kpi\Deferred;

use App\Events\KpiComputed;
use App\Kpi\Deferred\Calculators\BehavioralAnalysisCalculator;
use App\Kpi\Deferred\Calculators\DriverScoreCalculator;
use App\Kpi\Deferred\Calculators\FleetUtilizationCalculator;
use App\Kpi\Deferred\Calculators\MonthlyReportCalculator;
use App\Kpi\Deferred\Calculators\WeeklyPerformanceCalculator;
use App\Models\Driver;
use App\Models\Fleet;
use App\Models\KpiRecord;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pipeline DF — déclenché exclusivement par le Scheduler Laravel (C-05).
 * Jamais appelé depuis le pipeline RT ni depuis un événement.
 * kpi_records : INSERT uniquement, versionné par DfKpiVersioner (DC-12).
 */
class DfKpiEngine
{
    public function __construct(private readonly DfKpiVersioner $versioner) {}

    public function computeDriverScores(Carbon $from, Carbon $to, string $schedulerRunId): void
    {
        $calculator = new DriverScoreCalculator();

        Driver::select('id', 'organization_id')->chunk(100, function ($drivers) use ($calculator, $from, $to, $schedulerRunId) {
            foreach ($drivers as $driver) {
                $this->insertDf(
                    $calculator->compute($driver->id, $from, $to),
                    $driver->organization_id,
                    $from,
                    $to,
                    $schedulerRunId
                );
            }
        });
    }

    public function computeFleetUtilization(Carbon $from, Carbon $to, string $schedulerRunId): void
    {
        $calculator = new FleetUtilizationCalculator();

        Fleet::select('id', 'organization_id')->chunk(100, function ($fleets) use ($calculator, $from, $to, $schedulerRunId) {
            foreach ($fleets as $fleet) {
                $this->insertDf(
                    $calculator->compute($fleet->id, $from, $to),
                    $fleet->organization_id,
                    $from,
                    $to,
                    $schedulerRunId
                );
            }
        });
    }

    public function computeWeeklyPerformance(Carbon $from, Carbon $to, string $schedulerRunId): void
    {
        $calculator = new WeeklyPerformanceCalculator();

        Fleet::select('id', 'organization_id')->chunk(100, function ($fleets) use ($calculator, $from, $to, $schedulerRunId) {
            foreach ($fleets as $fleet) {
                $this->insertDf(
                    $calculator->compute($fleet->id, $from, $to),
                    $fleet->organization_id,
                    $from,
                    $to,
                    $schedulerRunId
                );
            }
        });
    }

    public function computeBehavioralAnalysis(Carbon $from, Carbon $to, string $schedulerRunId): void
    {
        $calculator = new BehavioralAnalysisCalculator();

        Organization::select('id')->chunk(50, function ($orgs) use ($calculator, $from, $to, $schedulerRunId) {
            foreach ($orgs as $org) {
                $this->insertDf(
                    $calculator->compute($org->id, $from, $to),
                    $org->id,
                    $from,
                    $to,
                    $schedulerRunId
                );
            }
        });
    }

    public function computeMonthlyReport(Carbon $from, Carbon $to, string $schedulerRunId): void
    {
        $calculator = new MonthlyReportCalculator();

        Organization::select('id')->chunk(50, function ($orgs) use ($calculator, $from, $to, $schedulerRunId) {
            foreach ($orgs as $org) {
                $this->insertDf(
                    $calculator->compute($org->id, $from, $to),
                    $org->id,
                    $from,
                    $to,
                    $schedulerRunId
                );
            }
        });
    }

    private function insertDf(array $result, string $organizationId, Carbon $from, Carbon $to, string $schedulerRunId): void
    {
        try {
            $version = $this->versioner->nextVersion($result['scope_id'], $result['kpi_type'], $from, $to);

            $record = KpiRecord::create([
                'id'               => Str::uuid()->toString(),
                'organization_id'  => $organizationId,
                'scope_type'       => $result['scope_type'],
                'scope_id'         => $result['scope_id'],
                'kpi_type'         => $result['kpi_type'],
                'mode'             => 'DF',
                'period_from'      => $from,
                'period_to'        => $to,
                'value'            => $result['value'],
                'unit'             => $result['unit'] ?? null,
                'version'          => $version,
                'computed_at'      => now(),
                'scheduler_run_id' => $schedulerRunId,
            ]);

            event(new KpiComputed($record));

        } catch (\Throwable $e) {
            Log::error('geofact.kpi.df.insert_failed', [
                'kpi_type'         => $result['kpi_type'],
                'scope_id'         => $result['scope_id'],
                'scheduler_run_id' => $schedulerRunId,
                'error'            => $e->getMessage(),
            ]);
        }
    }
}
