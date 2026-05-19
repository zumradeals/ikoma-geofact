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
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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
                    'inactive' => 'Inactif',
                    'archived' => 'Archivé',
                ])
                ->default('active')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                        'inactive' => 'warning',
                        'archived' => 'gray',
                        default    => 'gray',
                    }),

                Tables\Columns\IconColumn::make('has_active_trip')
                    ->label('En cours')
                    ->boolean()
                    ->state(fn (Vehicle $record) => $record->activeTrip()->exists()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('fleet_id')
                    ->label('Flotte')
                    ->relationship('fleet', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'active'   => 'Actif',
                        'inactive' => 'Inactif',
                        'archived' => 'Archivé',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                TableAction::make('delete')
                    ->label('Supprimer')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Supprimer ce véhicule ?')
                    ->modalDescription('Les alertes, trajets et événements liés seront également supprimés.')
                    ->action(fn (Vehicle $record) => static::deleteVehicleCascade($record)),
            ])
            ->bulkActions([
                BulkAction::make('delete_selected')
                    ->label('Supprimer la sélection')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Supprimer les véhicules sélectionnés ?')
                    ->modalDescription('Les alertes, trajets et événements liés seront également supprimés.')
                    ->action(fn (Collection $records) => $records->each(fn ($r) => static::deleteVehicleCascade($r))),
            ]);
    }

    public static function deleteVehicleCascade(Vehicle $vehicle): void
    {
        $id = $vehicle->id;
        DB::table('wialon_unit_mappings')->where('ikoma_vehicle_id', $id)->update(['ikoma_vehicle_id' => null]);
        DB::table('alerts')->where('vehicle_id', $id)->delete();
        DB::table('trips')->where('vehicle_id', $id)->delete();
        DB::table('telemetry_events')->where('vehicle_id', $id)->delete();
        DB::table('devices')->where('vehicle_id', $id)->update(['vehicle_id' => null]);
        $vehicle->delete();
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
