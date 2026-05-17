<?php

namespace App\Kpi\RealTime\Calculators;

use App\Core\Canonical\CanonicalEvent;
use App\Models\GeoZone;

class CurrentZoneCalculator
{
    public function compute(CanonicalEvent $event): ?array
    {
        if ($event->vehicleId === null
            || ! isset($event->payload['latitude'], $event->payload['longitude'])) {
            return null;
        }

        $lat = (float) $event->payload['latitude'];
        $lng = (float) $event->payload['longitude'];

        // Zone la plus récente active pour l'organisation contenant la position
        $zone = GeoZone::where('organization_id', $event->organizationId)
            ->where('status', 'active')
            ->get()
            ->first(fn(GeoZone $z) => $this->isInside($lat, $lng, $z->geometry));

        return [
            'organization_id' => $event->organizationId,
            'scope_type'      => 'vehicle',
            'scope_id'        => $event->vehicleId,
            'kpi_type'        => 'current_zone',
            'value'           => 0,
            'unit'            => $zone ? $zone->id : 'none',
        ];
    }

    private function isInside(float $lat, float $lng, array $geometry): bool
    {
        return match($geometry['type'] ?? null) {
            'Circle'  => $this->inCircle($lat, $lng, $geometry),
            'Polygon' => $this->inPolygon($lat, $lng, $geometry['coordinates'][0] ?? []),
            default   => false,
        };
    }

    private function inCircle(float $lat, float $lng, array $g): bool
    {
        [$cLat, $cLng] = $g['center'];
        $r = 6371000;
        $dLat = deg2rad($lat - $cLat);
        $dLng = deg2rad($lng - $cLng);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($cLat)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;
        return ($r * 2 * asin(sqrt($a))) <= (float) $g['radius'];
    }

    private function inPolygon(float $lat, float $lng, array $coords): bool
    {
        $inside = false;
        $n      = count($coords);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = [$coords[$i][0], $coords[$i][1]];
            [$xj, $yj] = [$coords[$j][0], $coords[$j][1]];
            if ((($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
                $inside = ! $inside;
            }
        }
        return $inside;
    }
}
