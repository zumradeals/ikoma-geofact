<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\ConnectorResource\Pages;
use App\Models\Connector;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ConnectorResource extends Resource
{
    protected static ?string $model = Connector::class;
    protected static ?int $navigationSort = 3;
    protected static ?string $label = 'Connecteur';
    protected static ?string $pluralLabel = 'Connecteurs';

    public static function getNavigationIcon(): string { return 'heroicon-o-cpu-chip'; }
    public static function getNavigationGroup(): ?string { return 'Gestion'; }

    // La suppression physique est réservée à geofact_admin — désactivée ici par prudence
    public static function canDelete(Model $record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('organization_id')
                ->label('Organisation')
                ->relationship('organization', 'name')
                ->required()
                ->searchable()
                ->preload(),

            Forms\Components\TextInput::make('provider_id')
                ->label('Fournisseur GPS')
                ->required()
                ->maxLength(100)
                ->live(),

            Forms\Components\Select::make('connector_type')
                ->label('Type')
                ->options([
                    'http_push'    => 'HTTP Push',
                    'mqtt'         => 'MQTT',
                    'websocket'    => 'WebSocket',
                    'polling'      => 'Polling HTTP',
                ])
                ->required(),

            Forms\Components\Select::make('status')
                ->label('Statut')
                ->options([
                    'active'      => 'Actif',
                    'inactive'    => 'Inactif',
                    'suspended'   => 'Suspendu',
                    'revoked'     => 'Révoqué',
                ])
                ->default('active')
                ->required(),

            Forms\Components\Placeholder::make('token_note')
                ->label('')
                ->content('Le token IKOMA est généré automatiquement à la création. Il ne peut être consulté qu\'une seule fois.'),

            Section::make('Configuration fournisseur')
                ->description('Identifiants spécifiques au fournisseur GPS (chiffrés en base).')
                ->schema([
                    Forms\Components\TextInput::make('provider_config.wialon_token')
                        ->label('Token API Wialon')
                        ->password()
                        ->revealable()
                        ->placeholder('Coller le token Wialon ici')
                        ->helperText('Trouvez ce token dans votre compte Wialon → Paramètres utilisateur → Token.')
                        ->visible(fn (Get $get) => $get('provider_id') === 'wialon'),

                    Forms\Components\TextInput::make('provider_config.wialon_base_url')
                        ->label('URL de base Wialon')
                        ->default('https://hst-api.wialon.com')
                        ->placeholder('https://hst-api.wialon.com')
                        ->visible(fn (Get $get) => $get('provider_id') === 'wialon'),
                ])
                ->visible(fn (Get $get) => in_array($get('provider_id'), ['wialon'])),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Infolists\Components\TextEntry::make('organization.name')->label('Organisation'),
            Infolists\Components\TextEntry::make('provider_id')->label('Fournisseur GPS'),
            Infolists\Components\TextEntry::make('connector_type')->label('Type')->badge(),
            Infolists\Components\TextEntry::make('status')->label('Statut')->badge()
                ->color(fn (string $state) => match ($state) {
                    'active'    => 'success',
                    'suspended' => 'warning',
                    'revoked'   => 'danger',
                    default     => 'gray',
                }),
            Infolists\Components\TextEntry::make('certified_by')->label('Certifié par')->placeholder('—'),
            Infolists\Components\TextEntry::make('certified_at')->label('Certifié le')->dateTime('d/m/Y H:i')->placeholder('—'),
            Infolists\Components\TextEntry::make('last_sync_at')->label('Dernière sync')->dateTime('d/m/Y H:i')->placeholder('Jamais'),
            Infolists\Components\TextEntry::make('token_version')->label('Version token'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider_id')
                    ->label('Fournisseur')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('connector_type')
                    ->label('Type')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'    => 'success',
                        'inactive'  => 'warning',
                        'suspended' => 'warning',
                        'revoked'   => 'danger',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('last_sync_at')
                    ->label('Dernière sync')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Jamais'),

                Tables\Columns\TextColumn::make('certified_by')
                    ->label('Certifié par')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'active'    => 'Actif',
                        'inactive'  => 'Inactif',
                        'suspended' => 'Suspendu',
                        'revoked'   => 'Révoqué',
                    ]),

                Tables\Filters\SelectFilter::make('connector_type')
                    ->label('Type')
                    ->options([
                        'http_push' => 'HTTP Push',
                        'mqtt'      => 'MQTT',
                        'websocket' => 'WebSocket',
                        'polling'   => 'Polling HTTP',
                    ]),

                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organisation')
                    ->relationship('organization', 'name'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('revoke')
                    ->label('Révoquer')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Connector $record) => $record->status === 'active')
                    ->action(fn (Connector $record) => $record->update([
                        'status'          => 'revoked',
                        'token_version'   => $record->token_version + 1,
                    ])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListConnectors::route('/'),
            'create' => Pages\CreateConnector::route('/create'),
            'view'   => Pages\ViewConnector::route('/{record}'),
            'edit'   => Pages\EditConnector::route('/{record}/edit'),
        ];
    }
}
