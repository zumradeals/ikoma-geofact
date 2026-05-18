<?php

namespace App\Filament\Org\Resources\OrgConnectorResource\Pages;

use App\Filament\Org\Resources\OrgConnectorResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Forms;

class ViewOrgConnector extends ViewRecord
{
    protected static string $resource = OrgConnectorResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('provider_id')->label('Fournisseur')->disabled(),
                Forms\Components\TextInput::make('connector_type')->label('Type')->disabled(),
                Forms\Components\TextInput::make('status')->label('Statut')->disabled(),
                Forms\Components\TextInput::make('token_version')->label('Version token')->disabled(),
                Forms\Components\TextInput::make('last_sync_at')->label('Dernière sync')->disabled(),
                Forms\Components\TextInput::make('certified_at')->label('Certifié le')->disabled(),
            ]),
        ]);
    }
}
