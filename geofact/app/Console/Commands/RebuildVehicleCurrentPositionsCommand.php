<?php

namespace App\Console\Commands;

use App\Models\TelemetryEvent;
use App\Services\VehicleCurrentPositionProjector;
use Illuminate\Console\Command;

class RebuildVehicleCurrentPositionsCommand extends Command
{
    protected $signature = 'positions:rebuild-current
                            {--org= : UUID organisation a reconstruire}
                            {--vehicle= : UUID vehicule a reconstruire}
                            {--dry-run : Afficher sans ecrire}';

    protected $description = 'Reconstruit vehicle_current_positions depuis les derniers telemetry_events GPS';

    public function handle(VehicleCurrentPositionProjector $projector): int
    {
        $query = TelemetryEvent::query()
            ->whereNotNull('vehicle_id')
            ->where('vehicle_id', '<>', '')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($orgId = $this->option('org')) {
            $query->where('organization_id', $orgId);
        }

        if ($vehicleId = $this->option('vehicle')) {
            $query->where('vehicle_id', $vehicleId);
        }

        $pairs = (clone $query)
            ->select('organization_id', 'vehicle_id')
            ->distinct()
            ->orderBy('organization_id')
            ->orderBy('vehicle_id')
            ->get();

        if ($pairs->isEmpty()) {
            $this->info('Aucune position GPS exploitable trouvee.');
            return self::SUCCESS;
        }

        $this->info("Vehicules a reconstruire : {$pairs->count()}");

        $projected = 0;

        foreach ($pairs as $pair) {
            $event = TelemetryEvent::where('organization_id', $pair->organization_id)
                ->where('vehicle_id', $pair->vehicle_id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderByDesc('ts')
                ->orderByDesc('received_at')
                ->first();

            if (! $event) {
                continue;
            }

            $this->line("  {$pair->vehicle_id} -> {$event->ts?->toDateTimeString()} ({$event->latitude}, {$event->longitude})");

            if (! $this->option('dry-run') && $projector->projectFromTelemetry($event)) {
                $projected++;
            }
        }

        $suffix = $this->option('dry-run') ? ' [dry-run]' : '';
        $this->info("Positions reconstruites : {$projected}{$suffix}");

        return self::SUCCESS;
    }
}
