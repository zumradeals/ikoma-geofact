<?php

namespace App\Filament\Org\Resources;

use App\Filament\Org\Resources\VehicleResource\Pages;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action as TableAction;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Véhicule';
    protected static ?string $pluralLabel = 'Véhicules';

    public static function getNavigationGroup(): ?string { return 'Flotte'; }
    public static function getNavigationIcon(): string { return 'heroicon-o-map-pin'; }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('fleet_id')
                ->label('Flotte')
                ->relationship('fleet', 'name')
                ->required()
                ->searchable()
                ->preload(),

            Forms\Components\TextInput::make('name')
                ->label('Nom / Alias')
                ->required()
                ->maxLength(100),

            Forms\Components\TextInput::make('plate')
                ->label('Immatriculation')
                ->required()
                ->maxLength(20),

            Forms\Components\TextInput::make('brand')
                ->label('Marque')
                ->maxLength(60),

            Forms\Components\TextInput::make('model')
                ->label('Modèle')
                ->maxLength(60),

            Forms\Components\TextInput::make('year')
                ->label('Année')
                ->numeric()
                ->minValue(1990)
                ->maxValue(date('Y') + 1),

            Forms\Components\Select::make('status')
                ->label('Statut')
                ->options([
                    'active'   => 'Actif',
                    'suspended' => 'Suspendu',
                    'archived' => 'Archivé',
                ])
                ->default('active')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('currentPosition'))
            ->columns([
                Tables\Columns\TextColumn::make('plate')
                    ->label('Immatriculation')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fleet.name')
                    ->label('Flotte')
                    ->sortable(),

                Tables\Columns\TextColumn::make('brand')
                    ->label('Marque')
                    ->formatStateUsing(fn (?string $state, Vehicle $record) => trim("{$state} {$record->model}") ?: '—'),

                Tables\Columns\TextColumn::make('year')
                    ->label('Année'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'   => 'success',
                        'suspended' => 'warning',
                        'archived' => 'gray',
                        default    => 'gray',
                    }),

                Tables\Columns\IconColumn::make('has_active_trip')
                    ->label('En cours')
                    ->boolean()
                    ->state(fn (Vehicle $record) => $record->activeTrip()->exists()),

                Tables\Columns\TextColumn::make('currentPosition.position_ts')
                    ->label('Dernière pos. GPS')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn (Vehicle $record) => match ($record->currentPosition?->live_freshness_status) {
                        'fresh'   => 'success',
                        'delayed' => 'warning',
                        'stale'   => 'danger',
                        default   => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('fleet_id')
                    ->label('Flotte')
                    ->relationship('fleet', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'active'   => 'Actif',
                        'suspended' => 'Suspendu',
                        'archived' => 'Archivé',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                TableAction::make('archive')
                    ->label('Archiver')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Archiver ce vehicule ?')
                    ->modalDescription('Le vehicule sera retire des listes actives, sans supprimer son historique.')
                    ->action(fn (Vehicle $record) => static::archiveVehicle($record)),
            ])
            ->bulkActions([
                BulkAction::make('archive_selected')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->label('Archiver la selection')
                    ->modalHeading('Archiver les vehicules selectionnes ?')
                    ->modalDescription('Les vehicules seront retires des listes actives, sans supprimer leur historique.')
                    ->action(fn (Collection $records) => $records->each(fn ($r) => static::archiveVehicle($r))),
            ]);
    }

    public static function archiveVehicle(Vehicle $vehicle): void
    {
        $vehicle->update([
            'status'      => 'archived',
            'archived_at' => now(),
            'updated_at'  => now(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'view'   => Pages\ViewVehicle::route('/{record}'),
            'edit'   => Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
}
