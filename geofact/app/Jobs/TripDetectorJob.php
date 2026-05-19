<?php

namespace App\Jobs;

use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Détecte les débuts et fins de trajet à partir des telemetry_events.
 *
 * Algorithme (machine à états par véhicule) :
 *   IDLE  → MOVING : premier event avec speed > 0 ou ignition = 1
 *   MOVING → IDLE  : aucun mouvement pendant STOP_THRESHOLD_MINUTES consécutives
 *
 * Distance : haversine entre positions GPS consécutives dans la fenêtre du trajet.
 * Exécution : toutes les 2 minutes via Scheduler (routes/console.php).
 */
class TripDetectorJob
{
    use Dispatchable;

    private const STOP_THRESHOLD_MINUTES = 5;   // arrêt consécutif → fin de trajet
    private const OFFLINE_THRESHOLD_MINUTES = 30; // silence GPS → fermer le trajet
    private const MIN_TRIP_DISTANCE_KM = 0.1;   // ignorer les micro-trajets < 100m
    private const LOOK_BACK_MINUTES = 10;        // fenêtre de lecture des events récents

    public function handle(): void
    {
        Log::info('geofact.trip_detector.started');

        $vehicles = Vehicle::where('status', 'active')->get();

        foreach ($vehicles as $vehicle) {
            try {
                $this->processVehicle($vehicle);
            } catch (\Throwable $e) {
                Log::error('geofact.trip_detector.vehicle_error', [
                    'vehicle_id' => $vehicle->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        Log::info('geofact.trip_detector.completed', ['vehicles' => $vehicles->count()]);
    }

    private function processVehicle(Vehicle $vehicle): void
    {
        $activeTrip = Trip::where('vehicle_id', $vehicle->id)
            ->where('status', 'active')
            ->first();

        // Récupère les derniers events du véhicule
        $recentEvents = DB::table('telemetry_events')
            ->where('vehicle_id', $vehicle->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('ts', '>=', now()->subMinutes(self::LOOK_BACK_MINUTES))
            ->orderBy('ts')
            ->get(['ts', 'latitude', 'longitude', 'speed_kmh', 'ignition']);

        $latestEvent = DB::table('telemetry_events')
            ->where('vehicle_id', $vehicle->id)
            ->whereNotNull('latitude')
            ->orderByDesc('ts')
            ->first(['ts', 'latitude', 'longitude', 'speed_kmh', 'ignition']);

        if (! $latestEvent) {
            return;
        }

        if ($activeTrip) {
            $this->handleActiveTrip($vehicle, $activeTrip, $recentEvents, $latestEvent);
        } else {
            $this->handleIdleVehicle($vehicle, $latestEvent);
        }
    }

    private function handleActiveTrip(Vehicle $vehicle, Trip $trip, Collection $recentEvents, object $latestEvent): void
    {
        $latestTs = strtotime($latestEvent->ts);
        $tripAge  = (time() - strtotime($trip->started_at)) / 60;

        // Fermeture par silence GPS (véhicule offline)
        $minutesSinceLastEvent = (time() - $latestTs) / 60;
        if ($minutesSinceLastEvent >= self::OFFLINE_THRESHOLD_MINUTES) {
            $this->closeTrip($trip, $latestEvent, 'completed');
            Log::info('geofact.trip_detector.closed_offline', ['vehicle_id' => $vehicle->id]);
            return;
        }

        // Vérifier si le véhicule est arrêté depuis STOP_THRESHOLD_MINUTES
        $isStopped = $this->isVehicleStopped($recentEvents);

        if ($isStopped && $tripAge >= self::STOP_THRESHOLD_MINUTES) {
            $this->closeTrip($trip, $latestEvent, 'completed');
            Log::info('geofact.trip_detector.closed_stopped', ['vehicle_id' => $vehicle->id]);
        }
        // Sinon le trajet continue — pas d'action
    }

    private function handleIdleVehicle(Vehicle $vehicle, object $latestEvent): void
    {
        $minutesSinceLastEvent = (time() - strtotime($latestEvent->ts)) / 60;

        // Ne pas ouvrir un trajet sur un event trop vieux
        if ($minutesSinceLastEvent > self::LOOK_BACK_MINUTES) {
            return;
        }

        $isMoving = ($latestEvent->speed_kmh > 0) || ($latestEvent->ignition == 1);

        if ($isMoving) {
            $this->openTrip($vehicle, $latestEvent);
            Log::info('geofact.trip_detector.opened', ['vehicle_id' => $vehicle->id]);
        }
    }

    private function openTrip(Vehicle $vehicle, object $event): void
    {
        Trip::create([
            'id'              => Str::uuid()->toString(),
            'vehicle_id'      => $vehicle->id,
            'driver_id'       => null,
            'organization_id' => $vehicle->organization_id,
            'fleet_id'        => $vehicle->fleet_id,
            'status'          => 'active',
            'started_at'      => $event->ts,
            'start_latitude'  => $event->latitude,
            'start_longitude' => $event->longitude,
        ]);
    }

    private function closeTrip(Trip $trip, object $lastEvent, string $status): void
    {
        $endedAt = $lastEvent->ts;

        // Calcule distance totale via haversine sur tous les points du trajet
        $points = DB::table('telemetry_events')
            ->where('vehicle_id', $trip->vehicle_id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('ts', [$trip->started_at, $endedAt])
            ->orderBy('ts')
            ->pluck('longitude', 'latitude')
            ->toArray();

        // Re-query pour avoir lat+lon ensemble
        $coords = DB::table('telemetry_events')
            ->where('vehicle_id', $trip->vehicle_id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('ts', [$trip->started_at, $endedAt])
            ->orderBy('ts')
            ->get(['latitude', 'longitude']);

        $distanceKm = $this->calculateDistance($coords);

        // Ignorer les micro-trajets (GPS drift, redémarrage moteur)
        if ($distanceKm < self::MIN_TRIP_DISTANCE_KM) {
            $trip->delete();
            return;
        }

        $durationMinutes = max(1, round(
            (strtotime($endedAt) - strtotime($trip->started_at)) / 60
        ));

        $trip->update([
            'status'          => $status,
            'ended_at'        => $endedAt,
            'end_latitude'    => $lastEvent->latitude,
            'end_longitude'   => $lastEvent->longitude,
            'distance_km'     => round($distanceKm, 3),
            'duration_minutes'=> $durationMinutes,
        ]);
    }

    private function isVehicleStopped(Collection $events): bool
    {
        if ($events->isEmpty()) {
            return true;
        }

        // Tous les events récents ont speed=0 et ignition=0
        return $events->every(
            fn ($e) => ($e->speed_kmh <= 0 || $e->speed_kmh === null)
                    && ($e->ignition == 0  || $e->ignition === null)
        );
    }

    private function calculateDistance(Collection $coords): float
    {
        $total = 0.0;
        $prev  = null;

        foreach ($coords as $point) {
            if ($prev !== null) {
                $total += $this->haversine(
                    (float) $prev->latitude,
                    (float) $prev->longitude,
                    (float) $point->latitude,
                    (float) $point->longitude,
                );
            }
            $prev = $point;
        }

        return $total;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R    = 6371.0; // rayon Terre en km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $R * 2 * asin(sqrt($a));
    }
}
