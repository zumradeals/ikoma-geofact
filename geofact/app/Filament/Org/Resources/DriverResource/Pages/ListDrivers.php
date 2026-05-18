<?php
namespace App\Filament\Org\Resources\DriverResource\Pages;
use App\Filament\Org\Resources\DriverResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListDrivers extends ListRecords {
    protected static string $resource = DriverResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
