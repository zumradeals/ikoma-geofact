<?php
namespace App\Filament\Org\Resources\FleetResource\Pages;
use App\Filament\Org\Resources\FleetResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
class CreateFleet extends CreateRecord {
    protected static string $resource = FleetResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array {
        $data['id'] = Str::uuid()->toString();
        $data['created_by'] = auth()->id() ?? Str::uuid()->toString();
        $data['created_at'] = now();
        return $data;
    }
}
