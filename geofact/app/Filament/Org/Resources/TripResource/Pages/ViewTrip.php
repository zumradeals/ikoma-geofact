<?php

namespace App\Filament\Org\Resources\TripResource\Pages;

use App\Filament\Org\Resources\TripResource;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewTrip extends ViewRecord
{
    protected static string $resource = TripResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Infolists\Components\TextEntry::make('vehicle.plate')
                ->label('Véhicule'),

            Infolists\Components\TextEntry::make('status')
                ->label('Statut')
                ->badge()
                ->color(fn (string $state) => match ($state) {
                    'active'    => 'success',
                    'paused'    => 'warning',
                    'anomalous' => 'danger',
                    default     => 'gray',
                }),

            Infolists\Components\TextEntry::make('fleet.name')
                ->label('Flotte')
                ->default('—'),

            Infolists\Components\TextEntry::make('started_at')
                ->label('Départ')
                ->dateTime('d/m/Y H:i'),

            Infolists\Components\TextEntry::make('ended_at')
                ->label('Arrivée')
                ->dateTime('d/m/Y H:i')
                ->placeholder('En cours'),

            Infolists\Components\TextEntry::make('duration_minutes')
                ->label('Durée (min)')
                ->placeholder('—'),

            Infolists\Components\TextEntry::make('distance_km')
                ->label('Distance (km)')
                ->placeholder('—'),

            Infolists\Components\TextEntry::make('anomaly_note')
                ->label("Note d'anomalie")
                ->placeholder('Aucune')
                ->columnSpanFull(),
        ]);
    }
}
