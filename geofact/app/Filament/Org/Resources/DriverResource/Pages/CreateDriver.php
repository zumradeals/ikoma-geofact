<?php
namespace App\Filament\Org\Resources\DriverResource\Pages;
use App\Filament\Org\Resources\DriverResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
class CreateDriver extends CreateRecord {
    protected static string $resource = DriverResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array {
        $data['id'] = Str::uuid()->toString();
        $data['created_by'] = auth()->id() ?? Str::uuid()->toString();
        $data['created_at'] = now();
        return $data;
    }
}
