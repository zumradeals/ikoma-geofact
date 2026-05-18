<?php
namespace App\Filament\SuperAdmin\Resources\ConnectorResource\Pages;
use App\Filament\SuperAdmin\Resources\ConnectorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class ListConnectors extends ListRecords {
    protected static string $resource = ConnectorResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
    
}
