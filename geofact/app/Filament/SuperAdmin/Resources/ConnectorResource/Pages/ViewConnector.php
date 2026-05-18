<?php
namespace App\Filament\SuperAdmin\Resources\ConnectorResource\Pages;
use App\Filament\SuperAdmin\Resources\ConnectorResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class ViewConnector extends ViewRecord {
    protected static string $resource = ConnectorResource::class;
    
    
}
