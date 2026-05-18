<?php
namespace App\Filament\Org\Resources\TripResource\Pages;
use App\Filament\Org\Resources\TripResource;
use Filament\Resources\Pages\ListRecords;
class ListTrips extends ListRecords {
    protected static string $resource = TripResource::class;
    protected function getHeaderActions(): array { return []; }
}
