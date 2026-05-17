<?php

namespace App\Kpi\RealTime;

use App\Core\Canonical\CanonicalEvent;
use App\Core\Contracts\KpiEngineInterface;
use App\Events\CanonicalEventReceived;
use App\Events\KpiComputed;
use App\Kpi\RealTime\Calculators\ActiveTripDurationCalculator;
use App\Kpi\RealTime\Calculators\CurrentSpeedCalculator;
use App\Kpi\RealTime\Calculators\CurrentZoneCalculator;
use App\Kpi\RealTime\Calculators\OverspeedCountCalculator;
use App\Kpi\RealTime\Calculators\VehicleStatusCalculator;
use App\Models\KpiRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pipeline RT — déclenché par CanonicalEventReceived.
 * Contrat C-05 : RT et DF sont deux pipelines indépendants — jamais mélangés.
 * kpi_records : INSERT uniquement — jamais UPDATE (DC-12).
 */
class RtKpiEngine implements KpiEngineInterface
{
    /**
     * Point d'entrée pour l'Event Listener Laravel.
     */
    public function handle(CanonicalEventReceived $received): void
    {
        $this->compute($received->event);
    }

    /**
     * Implémentation KpiEngineInterface (C-05).
     * Ne bloque jamais — try/catch individuel par calculateur.
     */
    public function compute(CanonicalEvent $event): void
    {
        $calculators = [
            new CurrentSpeedCalculator(),
            new VehicleStatusCalculator(),
            new OverspeedCountCalculator(),
            new ActiveTripDurationCalculator(),
            new CurrentZoneCalculator(),
        ];

        foreach ($calculators as $calculator) {
            try {
                $result = $calculator->compute($event);

                if ($result === null) {
                    continue;
                }

                $record = KpiRecord::create([
                    'id'              => Str::uuid()->toString(),
                    'organization_id' => $result['organization_id'],
                    'scope_type'      => $result['scope_type'],
                    'scope_id'        => $result['scope_id'],
                    'kpi_type'        => $result['kpi_type'],
                    'mode'            => 'RT',
                    'period_from'     => null,
                    'period_to'       => null,
                    'value'           => $result['value'],
                    'unit'            => $result['unit'] ?? null,
                    'version'         => 1,
                    'computed_at'     => now(),
                ]);

                event(new KpiComputed($record));

            } catch (\Throwable $e) {
                Log::error('geofact.kpi.rt.calculator_failed', [
                    'calculator' => get_class($calculator),
                    'event_id'   => $event->eventId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }
}
