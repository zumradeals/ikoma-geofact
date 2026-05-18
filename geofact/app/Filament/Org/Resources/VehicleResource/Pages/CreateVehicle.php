<?php
namespace App\Filament\Org\Resources\VehicleResource\Pages;
use App\Filament\Org\Resources\VehicleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
class CreateVehicle extends CreateRecord {
    protected static string $resource = VehicleResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array {
        $data['id'] = Str::uuid()->toString();
        $data['created_by'] = auth()->id() ?? Str::uuid()->toString();
        $data['created_at'] = now();
        return $data;
    }
}
