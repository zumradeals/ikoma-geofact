<?php

namespace App\Filament\Org\Resources;

use App\Filament\Org\Resources\FleetResource\Pages;
use App\Models\Fleet;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class FleetResource extends Resource
{
    protected static ?string $model = Fleet::class;
    protected static ?int $navigationSort = 1;
    protected static ?string $label = 'Flotte';
    protected static ?string $pluralLabel = 'Flottes';

    public static function getNavigationGroup(): ?string { return 'Flotte'; }
    public static function getNavigationIcon(): string { return 'heroicon-o-truck'; }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nom de la flotte')
                ->required()
                ->maxLength(150),

            Forms\Components\Select::make('fleet_type')
                ->label('Type')
                ->options([
                    'urban'      => 'Urbain',
                    'intercity'  => 'Interurbain',
                    'logistics'  => 'Logistique',
                    'passenger'  => 'Passagers',
                    'mixed'      => 'Mixte',
                ])
                ->nullable(),

            Forms\Components\Select::make('status')
                ->label('Statut')
                ->options([
                    'active'    => 'Actif',
                    'inactive'  => 'Inactif',
                    'suspended' => 'Suspendu',
                ])
                ->default('active')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fleet_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'urban'     => 'Urbain',
                        'intercity' => 'Interurbain',
                        'logistics' => 'Logistique',
                        'passenger' => 'Passagers',
                        'mixed'     => 'Mixte',
                        default     => '—',
                    }),

                Tables\Columns\TextColumn::make('vehicles_count')
                    ->label('Véhicules')
                    ->counts('vehicles')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'    => 'success',
                        'inactive'  => 'warning',
                        'suspended' => 'danger',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'active'    => 'Actif',
                        'inactive'  => 'Inactif',
                        'suspended' => 'Suspendu',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFleets::route('/'),
            'create' => Pages\CreateFleet::route('/create'),
            'edit'   => Pages\EditFleet::route('/{record}/edit'),
        ];
    }
}
