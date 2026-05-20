<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\TripDetectorJob;
use App\Models\TelemetryEvent;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelemetryController extends Controller
{
    /**
     * GET /api/v1/telemetry/{vehicleId}
     * Retourne les derniers événements de télémétrie d'un véhicule (100 max).
     */
    public function recent(string $vehicleId, Request $request): JsonResponse
    {
        $orgId = $request->tenant['organization_id'];

        $vehicle = Vehicle::where('id', $vehicleId)
            ->where('organization_id', $orgId)
            ->first();

        if (! $vehicle) {
            return $this->apiResponse(null, 'Vehicle not found', 404);
        }

        $limit = min((int) $request->query('limit', 100), 500);

        $events = TelemetryEvent::where('vehicle_id', $vehicleId)
            ->where('organization_id', $orgId)
            ->orderByDesc('ts')
            ->limit($limit)
            ->get();

        return $this->apiResponse($events);
    }

    /**
     * POST /api/v1/telemetry
     * Ingère un ou plusieurs événements de télémétrie directement via l'API.
     * L'organization_id vient du JWT (jamais du body — contrat C-01).
     *
     * Body JSON accepte soit un objet unique, soit un tableau d'objets.
     * Champs obligatoires par événement : vehicle_id, ts
     * Champs optionnels : latitude, longitude, speed_kmh, heading, altitude_m,
     *                     fuel_level_pct, temperature_celsius, ignition, event_type, payload
     */
    public function ingest(Request $request): JsonResponse
    {
        $orgId = $request->tenant['organization_id'];

        $body = $request->json()->all();

        // Accepte un tableau ou un objet unique
        $events = isset($body[0]) ? $body : [$body];

        if (count($events) > 200) {
            return $this->apiResponse(null, 'Maximum 200 events per request', 422);
        }

        $created    = 0;
        $errors     = [];
        $vehicleIds = [];

        foreach ($events as $index => $raw) {
            // Validation minimale
            if (empty($raw['vehicle_id']) || empty($raw['ts'])) {
                $errors[] = "Event #{$index}: vehicle_id and ts are required";
                continue;
            }

            // Vérification appartenance au tenant
            $vehicle = Vehicle::where('id', $raw['vehicle_id'])
                ->where('organization_id', $orgId)
                ->first();

            if (! $vehicle) {
                $errors[] = "Event #{$index}: vehicle {$raw['vehicle_id']} not found in your organization";
                continue;
            }

            try {
                TelemetryEvent::create([
                    'id'                  => Str::uuid()->toString(),
                    'organization_id'     => $orgId,
                    'vehicle_id'          => $raw['vehicle_id'],
                    'event_type'          => $raw['event_type'] ?? 'position',
                    'ts'                  => $raw['ts'],
                    'received_at'         => now(),
                    'latitude'            => isset($raw['latitude'])  ? (float) $raw['latitude']  : null,
                    'longitude'           => isset($raw['longitude']) ? (float) $raw['longitude'] : null,
                    'speed_kmh'           => isset($raw['speed_kmh']) ? (float) $raw['speed_kmh'] : null,
                    'heading'             => isset($raw['heading'])   ? (int)   $raw['heading']   : null,
                    'altitude_m'          => isset($raw['altitude_m'])? (float) $raw['altitude_m']: null,
                    'fuel_level_pct'      => isset($raw['fuel_level_pct'])     ? (float) $raw['fuel_level_pct']      : null,
                    'temperature_celsius' => isset($raw['temperature_celsius'])? (float) $raw['temperature_celsius'] : null,
                    'ignition'            => isset($raw['ignition']) ? (bool) $raw['ignition'] : null,
                    'payload'             => isset($raw['payload']) && is_array($raw['payload']) ? $raw['payload'] : null,
                    'completeness'        => $this->computeCompleteness($raw),
                ]);

                $vehicleIds[] = $raw['vehicle_id'];
                $created++;

            } catch (\Throwable $e) {
                Log::error('geofact.telemetry.ingest.error', [
                    'index'      => $index,
                    'vehicle_id' => $raw['vehicle_id'],
                    'error'      => $e->getMessage(),
                ]);
                $errors[] = "Event #{$index}: internal error";
            }
        }

        // Déclenche la détection de trajet pour chaque véhicule concerné
        foreach (array_unique($vehicleIds) as $vid) {
            TripDetectorJob::dispatch($vid, $orgId)->onQueue('default');
        }

        $status = $created > 0 ? 201 : 422;
        return $this->apiResponse(
            ['created' => $created, 'errors' => $errors],
            "{$created} event(s) ingested",
            $status
        );
    }

    private function computeCompleteness(array $raw): float
    {
        $fields  = ['latitude', 'longitude', 'speed_kmh', 'heading', 'ignition'];
        $present = count(array_filter($fields, fn ($f) => isset($raw[$f])));
        return round($present / count($fields), 2);
    }
}
