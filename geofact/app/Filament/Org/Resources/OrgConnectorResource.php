<?php

namespace App\Filament\Org\Resources;

use App\Filament\Org\Resources\OrgConnectorResource\Pages;
use App\Models\Connector;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OrgConnectorResource extends Resource
{
    protected static ?string $model = Connector::class;
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Connecteur GPS';
    protected static ?string $pluralLabel = 'Connecteurs GPS';

    public static function getNavigationIcon(): string { return 'heroicon-o-signal'; }
    public static function getNavigationGroup(): ?string { return null; }

    // Lecture seule — pas de create/delete depuis le panel org
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', Auth::user()?->organization_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('provider_id')
                    ->label('Fournisseur GPS')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('connector_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state) => $state === 'push' ? 'info' : 'warning'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'  => 'success',
                        'revoked' => 'danger',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('token_version')
                    ->label('Version token')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('last_sync_at')
                    ->label('Dernière sync')
                    ->since()
                    ->placeholder('Jamais'),

                Tables\Columns\TextColumn::make('certified_at')
                    ->label('Certifié le')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('rotate_token')
                    ->label('Rotation token')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Rotation du token connecteur')
                    ->modalDescription('Un nouveau token_hash sera généré et token_version incrémenté. L\'ancien token sera immédiatement invalide. Le nouveau token ne sera affiché qu\'une seule fois.')
                    ->action(function (Connector $record) {
                        $newToken = Str::random(64);
                        $record->update([
                            'token_hash'    => bcrypt($newToken),
                            'token_version' => $record->token_version + 1,
                        ]);
                        Notification::make()
                            ->title('Nouveau token généré — copiez-le maintenant')
                            ->body("Token : {$newToken}")
                            ->warning()
                            ->persistent()
                            ->send();
                    })
                    ->visible(fn (Connector $record) => $record->status === 'active'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrgConnectors::route('/'),
            'view'  => Pages\ViewOrgConnector::route('/{record}'),
        ];
    }
}
