<?php

namespace App\Filament\Org\Pages;

use App\Connector\Drivers\WialonApiClient;
use App\Models\Connector;
use App\Models\Fleet;
use App\Models\Vehicle;
use App\Models\WialonUnitMapping;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WialonSyncPage extends Page
{
    protected static ?string $title         = 'Synchronisation Wialon';
    protected static ?int    $navigationSort = 20;
    protected string $view = 'filament.org.pages.wialon-sync';

    /** @var array<string, string> Sélections en cours : wialon_unit_id => ikoma_vehicle_id */
    public array $selectedVehicles = [];

    public static function getNavigationIcon(): string  { return 'heroicon-o-signal'; }
    public static function getNavigationGroup(): ?string { return 'Connecteurs GPS'; }

    public function getViewData(): array
    {
        $orgId      = Filament::getTenant()?->id ?? Auth::user()?->organization_id;
        $units      = [];
        $wialonError = null;

        $connector = Connector::where('organization_id', $orgId)
            ->where('provider_id', 'wialon')
            ->where('status', 'active')
            ->first();

        $wialonToken   = $connector?->getProviderConfigValue('wialon_token') ?? config('wialon.token');
        $wialonBaseUrl = $connector?->getProviderConfigValue('wialon_base_url');

        if (empty($wialonToken)) {
            $wialonError = 'Token Wialon non configuré — demandez au SuperAdmin de le saisir dans /admin → Connecteurs → modifier le connecteur Wialon.';
        } else {
            try {
                $client = new WialonApiClient($wialonToken, $wialonBaseUrl);
                $sid    = $client->login();
                $units  = $client->getUnits($sid);
            } catch (\Throwable $e) {
                $wialonError = 'Impossible de contacter Wialon : ' . $e->getMessage();
            }
        }

        $mappings = WialonUnitMapping::where('organization_id', $orgId)
            ->with(['vehicle', 'connector'])
            ->get()
            ->keyBy('wialon_unit_id');

        $vehicles = Vehicle::where('organization_id', $orgId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return [
            'units'       => $units,
            'mappings'    => $mappings,
            'vehicles'    => $vehicles,
            'wialonError' => $wialonError,
        ];
    }

    public function mapUnit(int $wialonUnitId, string $wialonUnitName): void
    {
        $vehicleId = $this->selectedVehicles[(string) $wialonUnitId] ?? '';
        $this->mapUnitWithVehicle($wialonUnitId, $wialonUnitName, $vehicleId);
    }

    /**
     * Crée automatiquement le véhicule depuis le nom de l'unité Wialon
     * puis le mappe — aucune création manuelle requise.
     */
    public function autoMapUnit(int $wialonUnitId, string $wialonUnitName): void
    {
        $orgId = Filament::getTenant()?->id ?? Auth::user()?->organization_id;
        $plate = $this->extractPlate($wialonUnitName);

        // Récupère la première flotte de l'org (optionnel — fleet_id est nullable)
        $fleetId = Fleet::where('organization_id', $orgId)->value('id');

        $vehicle = Vehicle::firstOrCreate(
            ['organization_id' => $orgId, 'plate' => $plate],
            [
                'id'              => Str::uuid()->toString(),
                'organization_id' => $orgId,
                'fleet_id'        => $fleetId,
                'name'            => mb_substr($wialonUnitName, 0, 100),
                'plate'           => $plate,
                'status'          => 'active',
                'created_by'      => Auth::id(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]
        );

        $this->mapUnitWithVehicle($wialonUnitId, $wialonUnitName, $vehicle->id);

        Notification::make()
            ->title('Véhicule créé et mappé')
            ->body("Véhicule « {$plate} » créé automatiquement depuis Wialon.")
            ->success()
            ->send();
    }

    /**
     * Extrait la plaque depuis le nom d'une unité Wialon.
     * Formats CI supportés : AA-524-BT · 44917 WW CI 01 · 2089 LE 01
     */
    private function extractPlate(string $unitName): string
    {
        // Format avec tirets : AA-524-BT
        if (preg_match('/\b([A-Z]{1,3}-\d{2,4}-[A-Z]{1,3})\b/', $unitName, $m)) {
            return $m[1];
        }
        // Format avec espaces type CI : 44917 WW CI 01 ou 2089 LE 01
        if (preg_match('/(\d{3,6}\s+[A-Z]{1,4}(?:\s+CI)?\s+\d{2})\s*$/i', $unitName, $m)) {
            return mb_substr(trim($m[1]), 0, 20);
        }
        // Fallback : 20 derniers caractères
        return mb_substr(trim($unitName), -20);
    }

    public function mapUnitWithVehicle(int $wialonUnitId, string $wialonUnitName, string $vehicleId): void
    {
        $orgId = Filament::getTenant()?->id ?? Auth::user()?->organization_id;

        $connector = Connector::where('organization_id', $orgId)
            ->where('provider_id', 'wialon')
            ->where('status', 'active')
            ->first();

        if (! $connector) {
            Notification::make()
                ->title('Connecteur Wialon introuvable')
                ->body('Créez d\'abord un connecteur avec provider_id=wialon dans /admin → Connecteurs.')
                ->danger()->send();
            return;
        }

        WialonUnitMapping::updateOrCreate(
            ['organization_id' => $orgId, 'wialon_unit_id' => $wialonUnitId],
            [
                'id'                 => Str::uuid()->toString(),
                'wialon_unit_name'   => $wialonUnitName,
                'ikoma_vehicle_id'   => $vehicleId ?: null,
                'ikoma_connector_id' => $connector->id,
                'status'             => 'active',
            ]
        );

        Notification::make()
            ->title('Unité mappée')
            ->body("Wialon « {$wialonUnitName} » synchronisée vers IKOMA.")
            ->success()->send();
    }

    public function toggleMapping(string $mappingId): void
    {
        $mapping = WialonUnitMapping::find($mappingId);
        if (! $mapping) return;

        $mapping->update(['status' => $mapping->status === 'active' ? 'inactive' : 'active']);

        Notification::make()
            ->title($mapping->status === 'active' ? 'Synchronisation activée' : 'Synchronisation suspendue')
            ->success()->send();
    }

    public function removeMapping(string $mappingId): void
    {
        WialonUnitMapping::where('id', $mappingId)
            ->where('organization_id', Auth::user()?->organization_id)
            ->delete();

        Notification::make()->title('Mapping supprimé')->success()->send();
    }
}
