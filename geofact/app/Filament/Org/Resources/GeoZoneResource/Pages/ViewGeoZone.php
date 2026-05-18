<?php

namespace App\Filament\Org\Resources\GeoZoneResource\Pages;

use App\Filament\Org\Resources\GeoZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGeoZone extends ViewRecord
{
    protected static string $resource = GeoZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}
