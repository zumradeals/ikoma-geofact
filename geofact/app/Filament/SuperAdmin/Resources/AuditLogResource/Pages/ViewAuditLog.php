<?php

namespace App\Filament\SuperAdmin\Resources\AuditLogResource\Pages;

use App\Filament\SuperAdmin\Resources\AuditLogResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Forms;

class ViewAuditLog extends ViewRecord
{
    protected static string $resource = AuditLogResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('actor_id')->label('Acteur')->disabled(),
            Forms\Components\TextInput::make('actor_role')->label('Rôle')->disabled(),
            Forms\Components\TextInput::make('organization_id')->label('Organisation')->disabled(),
            Forms\Components\TextInput::make('action')->label('Action')->disabled(),
            Forms\Components\TextInput::make('resource_type')->label('Type ressource')->disabled(),
            Forms\Components\TextInput::make('resource_id')->label('ID ressource')->disabled(),
            Forms\Components\TextInput::make('result')->label('Résultat')->disabled(),
            Forms\Components\TextInput::make('ip_address')->label('Adresse IP')->disabled(),
            Forms\Components\Textarea::make('user_agent')->label('User-Agent')->disabled()->rows(2),
            Forms\Components\KeyValue::make('payload')->label('Payload')->disabled(),
            Forms\Components\TextInput::make('created_at')->label('Date')->disabled(),
        ]);
    }
}
