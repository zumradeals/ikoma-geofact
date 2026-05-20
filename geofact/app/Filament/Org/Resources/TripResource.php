<?php

namespace App\Filament\Org\Resources;

use App\Filament\Org\Pages\TripReplayPage;
use App\Filament\Org\Resources\TripResource\Pages;
use App\Models\Trip;
use Illuminate\Support\Facades\Auth;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Trajet';
    protected static ?string $pluralLabel = 'Trajets';

    public static function getNavigationGroup(): ?string { return 'Sécurité'; }
    public static function getNavigationIcon(): string { return 'heroicon-o-map'; }

    // Trajets gérés par l'API uniquement — lecture seule dans le dashboard
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Infolists\Components\Section::make('Informations générales')
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('vehicle.plate')
                        ->label('Véhicule'),

                    Infolists\Components\TextEntry::make('driver.first_name')
                        ->label('Conducteur')
                        ->formatStateUsing(fn (?string $state, Trip $record) =>
                            $record->driver
                                ? "{$record->driver->first_name} {$record->driver->last_name}"
                                : '—'
                        ),

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
                        ->formatStateUsing(fn (?int $state) => $state ? "{$state} min" : '—'),

                    Infolists\Components\TextEntry::make('distance_km')
                        ->label('Distance')
                        ->formatStateUsing(fn (?string $state) =>
                            $state ? number_format((float) $state, 1) . ' km' : '—'
                        ),
                ]),

            Infolists\Components\Section::make('Coordonnées')
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
                        ->label('Note d\'anomalie')
                        ->placeholder('Aucune')
                        ->columnSpanFull(),
                ])
                ->visible(fn (Trip $record) => $record->anomaly_note !== null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('vehicle.plate')
                    ->label('Véhicule')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('driver.last_name')
                    ->label('Conducteur')
                    ->formatStateUsing(fn (?string $state, Trip $record) =>
                        $record->driver ? "{$record->driver->first_name} {$state}" : '—'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'    => 'success',
                        'paused'    => 'warning',
                        'anomalous' => 'danger',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Début')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ended_at')
                    ->label('Fin')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('En cours'),

                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Durée')
                    ->formatStateUsing(fn (?int $state) => $state ? "{$state} min" : '—'),

                Tables\Columns\TextColumn::make('distance_km')
                    ->label('Distance')
                    ->formatStateUsing(fn (?string $state) => $state ? number_format((float) $state, 1) . ' km' : '—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'active'    => 'En cours',
                        'paused'    => 'En pause',
                        'completed' => 'Terminé',
                        'anomalous' => 'Anomalie',
                        'cancelled' => 'Annulé',
                    ]),

                Tables\Filters\Filter::make('active_only')
                    ->label('Trajets actifs')
                    ->query(fn ($query) => $query->whereIn('status', ['active', 'paused'])),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('replay')
                    ->label('Replay')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->url(fn (Trip $record) => TripReplayPage::getUrl(['trip' => $record->id], tenant: auth()->user()?->organization))
                    ->visible(fn (Trip $record) => in_array($record->status, ['completed', 'anomalous'])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrips::route('/'),
            'view'  => Pages\ViewTrip::route('/{record}'),
        ];
    }
}
