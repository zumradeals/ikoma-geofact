<?php
namespace App\Filament\Org\Resources\DriverResource\Pages;
use App\Filament\Org\Resources\DriverResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditDriver extends EditRecord {
    protected static string $resource = DriverResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
