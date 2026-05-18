<?php
namespace App\Filament\Org\Resources\VehicleResource\Pages;
use App\Filament\Org\Resources\VehicleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditVehicle extends EditRecord {
    protected static string $resource = VehicleResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
