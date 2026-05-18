<?php

namespace App\Filament\Org\Resources\GeoZoneResource\Pages;

use App\Filament\Org\Resources\GeoZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGeoZone extends EditRecord
{
    protected static string $resource = GeoZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }
}
