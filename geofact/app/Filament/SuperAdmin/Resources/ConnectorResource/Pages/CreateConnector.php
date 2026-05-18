<?php
namespace App\Filament\SuperAdmin\Resources\ConnectorResource\Pages;
use App\Filament\SuperAdmin\Resources\ConnectorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class CreateConnector extends CreateRecord {
    protected static string $resource = ConnectorResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array { $raw = Str::random(64); $data["id"] = Str::uuid()->toString(); $data["token_hash"] = Hash::make($raw); $data["token_version"] = 1; $data["created_at"] = now(); session(["connector_token_" . $data["id"] => $raw]); return $data; }
}
