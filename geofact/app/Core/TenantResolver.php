<?php

namespace App\Core;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

class TenantResolver
{
    /**
     * Résout organization_id, fleet_id et vehicle_id depuis un device_id.
     * Contrat C-10 étape 8 : utilisé quand vehicle_id est absent du payload Connector.
     *
     * @return array{organization_id: string|null, fleet_id: string|null, vehicle_id: string|null}
     */
    public function resolve(string $deviceId): array
    {
        $device = Device::with('vehicle.fleet')
                        ->where('id', $deviceId)
                        ->orWhere('imei', $deviceId)
                        ->first();

        if (! $device) {
            Log::warning('geofact.tenant_resolver.device_not_found', ['device_id' => $deviceId]);
            return [
                'organization_id' => null,
                'fleet_id'        => null,
                'vehicle_id'      => null,
            ];
        }

        $vehicle = $device->vehicle;

        return [
            'organization_id' => $device->organization_id,
            'fleet_id'        => $vehicle?->fleet_id,
            'vehicle_id'      => $vehicle?->id,
        ];
    }
}
