<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\OrganizationResource\Pages;
use App\Models\Organization;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;
    protected static ?int $navigationSort = 1;
    protected static ?string $label = 'Organisation';
    protected static ?string $pluralLabel = 'Organisations';

    public static function getNavigationIcon(): string { return 'heroicon-o-building-office-2'; }
    public static function getNavigationGroup(): ?string { return 'Gestion'; }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nom')
                ->required()
                ->maxLength(150),

            Forms\Components\Select::make('country_code')
                ->label('Pays')
                ->options([
                    'CI' => 'Côte d\'Ivoire',
                    'SN' => 'Sénégal',
                    'ML' => 'Mali',
                    'BF' => 'Burkina Faso',
                    'GN' => 'Guinée',
                    'TG' => 'Togo',
                    'BJ' => 'Bénin',
                    'CM' => 'Cameroun',
                    'GH' => 'Ghana',
                    'NG' => 'Nigéria',
                ])
                ->default('CI')
                ->required()
                ->searchable(),

            Forms\Components\Select::make('timezone')
                ->label('Fuseau horaire')
                ->options([
                    'Africa/Abidjan'  => 'Abidjan (UTC+0)',
                    'Africa/Dakar'    => 'Dakar (UTC+0)',
                    'Africa/Bamako'   => 'Bamako (UTC+0)',
                    'Africa/Lagos'    => 'Lagos (UTC+1)',
                    'Africa/Douala'   => 'Douala (UTC+1)',
                    'Africa/Accra'    => 'Accra (UTC+0)',
                ])
                ->default('Africa/Abidjan')
                ->required(),

            Forms\Components\Select::make('status')
                ->label('Statut')
                ->options([
                    'active'    => 'Active',
                    'suspended' => 'Suspendue',
                    'deleted'   => 'Supprimée',
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
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('country_code')
                    ->label('Pays')
                    ->badge(),

                Tables\Columns\TextColumn::make('fleets_count')
                    ->label('Flottes')
                    ->counts('fleets')
                    ->sortable(),

                Tables\Columns\TextColumn::make('vehicles_count')
                    ->label('Véhicules')
                    ->counts('vehicles')
                    ->sortable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Utilisateurs')
                    ->counts('users')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'    => 'success',
                        'suspended' => 'warning',
                        'deleted'   => 'danger',
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
                        'active'    => 'Active',
                        'suspended' => 'Suspendue',
                        'deleted'   => 'Supprimée',
                    ]),

                Tables\Filters\SelectFilter::make('country_code')
                    ->label('Pays')
                    ->options([
                        'CI' => 'Côte d\'Ivoire',
                        'SN' => 'Sénégal',
                        'ML' => 'Mali',
                        'BF' => 'Burkina Faso',
                        'GN' => 'Guinée',
                        'TG' => 'Togo',
                        'BJ' => 'Bénin',
                        'CM' => 'Cameroun',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),

                Tables\Actions\Action::make('suspend')
                    ->label('Suspendre')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Organization $record) => $record->status === 'active')
                    ->action(fn (Organization $record) => $record->update(['status' => 'suspended'])),

                Tables\Actions\Action::make('activate')
                    ->label('Activer')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->visible(fn (Organization $record) => $record->status === 'suspended')
                    ->action(fn (Organization $record) => $record->update(['status' => 'active'])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'view'   => Pages\ViewOrganization::route('/{record}'),
            'edit'   => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}
