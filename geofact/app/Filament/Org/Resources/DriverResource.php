<?php

namespace App\Filament\Org\Resources;

use App\Filament\Org\Resources\DriverResource\Pages;
use App\Models\Driver;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;
    protected static ?int $navigationSort = 3;
    protected static ?string $label = 'Conducteur';
    protected static ?string $pluralLabel = 'Conducteurs';

    public static function getNavigationGroup(): ?string { return 'Flotte'; }
    public static function getNavigationIcon(): string { return 'heroicon-o-user-circle'; }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('fleet_id')
                ->label('Flotte')
                ->relationship('fleet', 'name')
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\TextInput::make('first_name')
                ->label('Prénom')
                ->required()
                ->maxLength(80),

            Forms\Components\TextInput::make('last_name')
                ->label('Nom')
                ->required()
                ->maxLength(80),

            Forms\Components\TextInput::make('phone')
                ->label('Téléphone')
                ->tel()
                ->maxLength(20),

            Forms\Components\TextInput::make('license_number')
                ->label('N° Permis')
                ->maxLength(50),

            Forms\Components\DatePicker::make('license_expiry')
                ->label('Expiration permis')
                ->displayFormat('d/m/Y'),

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
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state, Driver $record) => "{$record->first_name} {$state}"),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Téléphone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('fleet.name')
                    ->label('Flotte')
                    ->sortable(),

                Tables\Columns\TextColumn::make('license_number')
                    ->label('N° Permis'),

                Tables\Columns\TextColumn::make('license_expiry')
                    ->label('Expiration')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'    => 'success',
                        'inactive'  => 'warning',
                        'suspended' => 'danger',
                        default     => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('fleet_id')
                    ->label('Flotte')
                    ->relationship('fleet', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'active'    => 'Actif',
                        'inactive'  => 'Inactif',
                        'suspended' => 'Suspendu',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'view'   => Pages\ViewDriver::route('/{record}'),
            'edit'   => Pages\EditDriver::route('/{record}/edit'),
        ];
    }
}
