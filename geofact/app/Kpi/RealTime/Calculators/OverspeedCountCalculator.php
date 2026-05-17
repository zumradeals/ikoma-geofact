<?php

namespace App\Kpi\RealTime\Calculators;

use App\Core\Canonical\CanonicalEvent;
use App\Models\KpiRecord;

class OverspeedCountCalculator
{
    public function compute(CanonicalEvent $event): ?array
    {
        if ($event->eventType !== 'alert.overspeed.detected' || $event->vehicleId === null) {
            return null;
        }

        // Compteur journalier courant (lecture seule — INSERT sera géré par RtKpiEngine)
        $today = now()->startOfDay();
        $currentCount = KpiRecord::where('scope_id', $event->vehicleId)
            ->where('kpi_type', 'overspeed_count_daily')
            ->where('mode', 'RT')
            ->whereDate('computed_at', $today)
            ->max('value') ?? 0;

        return [
            'organization_id' => $event->organizationId,
            'scope_type'      => 'vehicle',
            'scope_id'        => $event->vehicleId,
            'kpi_type'        => 'overspeed_count_daily',
            'value'           => (int) $currentCount + 1,
            'unit'            => 'count',
        ];
    }
}
