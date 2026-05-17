<?php

namespace App\Rules\SystemRules;

use App\Core\Canonical\CanonicalEvent;
use App\Models\Alert;
use App\Models\GeoZone;
use App\Rules\Contracts\RuleInterface;
use Illuminate\Support\Str;

class RS04_GeozoneEntryRule implements RuleInterface
{
    public function getRuleId(): string { return 'RS04'; }
    public function getRuleType(): string { return 'RS'; }

    public function applies(CanonicalEvent $event): bool
    {
        return isset($event->payload['latitude'], $event->payload['longitude']);
    }

    public function evaluate(CanonicalEvent $event): ?Alert
    {
        $lat = (float) $event->payload['latitude'];
        $lng = (float) $event->payload['longitude'];

        $geozones = GeoZone::where('organization_id', $event->organizationId)
            ->where('status', 'active')
            ->get();

        foreach ($geozones as $zone) {
            if (! $this->isInsideZone($lat, $lng, $zone->geometry)) {
                continue;
            }

            $isRestricted = $zone->zone_type === 'restricted';
            $eventType    = $isRestricted ? 'geozone.violated' : 'geozone.entered';
            $severity     = $isRestricted ? 'HIGH' : 'LOW';

            return new Alert([
                'id'              => Str::uuid()->toString(),
                'organization_id' => $event->organizationId,
                'vehicle_id'      => $event->vehicleId,
                'trip_id'         => $event->tripId,
                'rule_id'         => $this->getRuleId(),
                'rule_type'       => $this->getRuleType(),
                'event_type'      => $eventType,
                'severity'        => $severity,
                'status'          => 'open',
                'triggered_at'    => $event->timestamp,
                'payload'         => [
                    'geozone_id'   => $zone->id,
                    'geozone_name' => $zone->name,
                    'zone_type'    => $zone->zone_type,
                    'latitude'     => $lat,
                    'longitude'    => $lng,
                    'canonical_id' => $event->eventId,
                ],
            ]);
        }

        return null;
    }

    private function isInsideZone(float $lat, float $lng, array $geometry): bool
    {
        $type = $geometry['type'] ?? null;

        return match($type) {
            'Circle'  => $this->isInsideCircle($lat, $lng, $geometry),
            'Polygon' => $this->isInsidePolygon($lat, $lng, $geometry['coordinates'][0] ?? []),
            default   => false,
        };
    }

    // Haversine — distance en mètres entre deux points GPS
    private function isInsideCircle(float $lat, float $lng, array $geometry): bool
    {
        $center      = $geometry['center'];     // [lat, lng]
        $radiusM     = (float) $geometry['radius'];
        $earthRadius = 6371000;

        $dLat = deg2rad($lat - $center[0]);
        $dLng = deg2rad($lng - $center[1]);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($center[0])) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        $distance = $earthRadius * 2 * asin(sqrt($a));

        return $distance <= $radiusM;
    }

    // Ray casting algorithm — point dans polygone GeoJSON ([lng, lat] pairs)
    private function isInsidePolygon(float $lat, float $lng, array $coords): bool
    {
        $inside = false;
        $n      = count($coords);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $coords[$i][0]; // lng
            $yi = $coords[$i][1]; // lat
            $xj = $coords[$j][0];
            $yj = $coords[$j][1];

            if ((($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
