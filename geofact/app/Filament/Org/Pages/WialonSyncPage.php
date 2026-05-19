<?php

namespace App\Filament\Org\Pages;

use App\Connector\Drivers\WialonApiClient;
use App\Models\Connector;
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
