<?php

namespace App\Filament\Org\Resources\TripResource\Pages;

use App\Filament\Org\Resources\TripResource;
use App\Models\Trip;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewTrip extends ViewRecord
{
    protected static string $resource = TripResource::class;

    public function infolist(Schema $schema): Schema
    {
        /** @var Trip $trip */
        $trip = $this->record;

        return $schema->schema([
            Infolists\Components\Section::make('Informations générales')
                ->columns(2)
                ->schema([
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

                    Infolists\Components\TextEntry::make('driver_name')
                        ->label('Conducteur')
                        ->state(fn () => $trip->driver
                            ? "{$trip->driver->first_name} {$trip->driver->last_name}"
                            : '—'
                        ),

                    Infolists\Components\TextEntry::make('fleet.name')
                        ->label('Flotte')
                        ->default('—'),
                ]),

            Infolists\Components\Section::make('Chronologie')
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('started_at')
                        ->label('Départ')
                        ->dateTime('d/m/Y H:i'),

                    Infolists\Components\TextEntry::make('ended_at')
                        ->label('Arrivée')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('En cours'),

                    Infolists\Components\TextEntry::make('duration_minutes')
                        ->label('Durée')
                        ->state(fn () => $trip->duration_minutes ? "{$trip->duration_minutes} min" : '—'),

                    Infolists\Components\TextEntry::make('distance_km')
                        ->label('Distance')
                        ->state(fn () => $trip->distance_km
                            ? number_format((float) $trip->distance_km, 1) . ' km'
                            : '—'
                        ),
                ]),

            Infolists\Components\Section::make('Coordonnées GPS')
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('start_latitude')
                        ->label('Latitude départ')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('start_longitude')
                        ->label('Longitude départ')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('end_latitude')
                        ->label('Latitude arrivée')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('end_longitude')
                        ->label('Longitude arrivée')
                        ->placeholder('—'),
                ]),

            Infolists\Components\Section::make('Anomalie')
                ->schema([
                    Infolists\Components\TextEntry::make('anomaly_note')
                        ->label("Note d'anomalie")
                        ->placeholder('Aucune')
                        ->columnSpanFull(),
                ])
                ->hidden(fn () => $trip->anomaly_note === null),
        ]);
    }
}
