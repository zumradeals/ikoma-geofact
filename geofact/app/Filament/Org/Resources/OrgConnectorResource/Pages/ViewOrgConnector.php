<?php

namespace App\Filament\Org\Resources\OrgConnectorResource\Pages;

use App\Filament\Org\Resources\OrgConnectorResource;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewOrgConnector extends ViewRecord
{
    protected static string $resource = OrgConnectorResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Infolists\Components\TextEntry::make('provider_id')->label('Fournisseur GPS'),
            Infolists\Components\TextEntry::make('connector_type')->label('Type')->badge(),
            Infolists\Components\TextEntry::make('status')->label('Statut')->badge()
                ->color(fn (string $state) => match ($state) {
                    'active'  => 'success',
                    'revoked' => 'danger',
                    default   => 'gray',
                }),
            Infolists\Components\TextEntry::make('token_version')->label('Version token'),
            Infolists\Components\TextEntry::make('last_sync_at')->label('Dernière sync')
                ->dateTime('d/m/Y H:i')->placeholder('Jamais'),
            Infolists\Components\TextEntry::make('certified_at')->label('Certifié le')
                ->date('d/m/Y')->placeholder('—'),
        ]);
    }
}
