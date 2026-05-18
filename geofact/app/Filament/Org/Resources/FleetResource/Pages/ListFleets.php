<?php
namespace App\Filament\Org\Resources\FleetResource\Pages;
use App\Filament\Org\Resources\FleetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListFleets extends ListRecords {
    protected static string $resource = FleetResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
