<?php
namespace App\Filament\Org\Resources\FleetResource\Pages;
use App\Filament\Org\Resources\FleetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditFleet extends EditRecord {
    protected static string $resource = FleetResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
