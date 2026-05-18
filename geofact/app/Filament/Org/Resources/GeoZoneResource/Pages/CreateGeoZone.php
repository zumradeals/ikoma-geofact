<?php

namespace App\Filament\Org\Resources\GeoZoneResource\Pages;

use App\Filament\Org\Resources\GeoZoneResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateGeoZone extends CreateRecord
{
    protected static string $resource = GeoZoneResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['id']              = Str::uuid()->toString();
        $data['organization_id'] = Auth::user()?->organization_id;
        $data['created_by']      = Auth::id();
        $data['version']         = 1;
        return $data;
    }
}
