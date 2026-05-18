<?php
namespace App\Filament\Org\Resources\VehicleResource\Pages;
use App\Filament\Org\Resources\VehicleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListVehicles extends ListRecords {
    protected static string $resource = VehicleResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
