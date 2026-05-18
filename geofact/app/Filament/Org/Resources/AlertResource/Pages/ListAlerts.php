<?php
namespace App\Filament\Org\Resources\AlertResource\Pages;
use App\Filament\Org\Resources\AlertResource;
use Filament\Resources\Pages\ListRecords;
class ListAlerts extends ListRecords {
    protected static string $resource = AlertResource::class;
    protected function getHeaderActions(): array { return []; }
}
