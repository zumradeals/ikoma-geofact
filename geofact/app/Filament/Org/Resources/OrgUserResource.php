<?php

namespace App\Filament\Org\Resources;

use App\Filament\Org\Resources\OrgUserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OrgUserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?int $navigationSort = 10;
    protected static ?string $label = 'Utilisateur';
    protected static ?string $pluralLabel = 'Utilisateurs';

    public static function getNavigationIcon(): string { return 'heroicon-o-users'; }
    public static function getNavigationGroup(): ?string { return 'Administration'; }

    // Scope : org_admin ne voit que les utilisateurs de son organisation
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', auth()->user()?->organization_id)
            ->whereNotIn('role', ['geofact_admin']); // jamais les super-admins
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('first_name')
                ->label('Prénom')
                ->required()
                ->maxLength(80),

            Forms\Components\TextInput::make('last_name')
                ->label('Nom')
                ->required()
                ->maxLength(80),

            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->maxLength(150),

            Forms\Components\TextInput::make('password_hash')
                ->label('Mot de passe')
                ->password()
                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create'),

            Forms\Components\Select::make('role')
                ->label('Rôle')
                ->options([
                    'org_admin'  => 'Admin Organisation',
                    'fleet_admin' => 'Admin Flotte',
                    'supervisor'  => 'Superviseur',
                    'driver'      => 'Conducteur',
                ])
                ->required(),

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
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_name')
                    ->label('Nom')
                    ->formatStateUsing(fn (string $state, User $record) => "{$record->first_name} {$state}")
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Rôle')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'org_admin'   => 'warning',
                        'fleet_admin' => 'info',
                        'supervisor'  => 'gray',
                        'driver'      => 'gray',
                        default       => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'    => 'success',
                        'inactive'  => 'warning',
                        'suspended' => 'danger',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Dernière connexion')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Jamais'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rôle')
                    ->options([
                        'org_admin'   => 'Admin Organisation',
                        'fleet_admin' => 'Admin Flotte',
                        'supervisor'  => 'Superviseur',
                        'driver'      => 'Conducteur',
                    ]),
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
                Tables\Actions\Action::make('suspend')
                    ->label('Suspendre')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => $record->status === 'active' && $record->id !== auth()->id())
                    ->action(fn (User $record) => $record->update(['status' => 'suspended'])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrgUsers::route('/'),
            'create' => Pages\CreateOrgUser::route('/create'),
            'edit'   => Pages\EditOrgUser::route('/{record}/edit'),
        ];
    }
}
