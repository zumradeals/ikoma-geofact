<?php
namespace App\Filament\SuperAdmin\Resources\ConnectorResource\Pages;
use App\Filament\SuperAdmin\Resources\ConnectorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class EditConnector extends EditRecord {
    protected static string $resource = ConnectorResource::class;
    
    
}
