<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;
    protected static ?int $navigationSort = 4;
    protected static ?string $label = 'Journal d\'audit';
    protected static ?string $pluralLabel = 'Journal d\'audit';

    public static function getNavigationIcon(): string { return 'heroicon-o-shield-check'; }
    public static function getNavigationGroup(): ?string { return 'Sécurité'; }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('actor_id')
                    ->label('Acteur')
                    ->limit(12)
                    ->tooltip(fn ($record) => $record->actor_id),

                Tables\Columns\TextColumn::make('actor_role')
                    ->label('Rôle')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'geofact_admin' => 'danger',
                        'org_admin'     => 'warning',
                        'fleet_admin'   => 'info',
                        default         => 'gray',
                    }),

                Tables\Columns\TextColumn::make('organization_id')
                    ->label('Org')
                    ->limit(8)
                    ->tooltip(fn ($record) => $record->organization_id),

                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('resource_type')
                    ->label('Ressource')
                    ->badge(),

                Tables\Columns\TextColumn::make('result')
                    ->label('Résultat')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'success'   => 'success',
                        'forbidden' => 'danger',
                        'error'     => 'warning',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('actor_role')
                    ->label('Rôle')
                    ->options([
                        'geofact_admin' => 'geofact_admin',
                        'org_admin'     => 'org_admin',
                        'fleet_admin'   => 'fleet_admin',
                        'supervisor'    => 'supervisor',
                        'driver'        => 'driver',
                        'integrator'    => 'integrator',
                    ]),

                Tables\Filters\SelectFilter::make('result')
                    ->label('Résultat')
                    ->options([
                        'success'   => 'Succès',
                        'forbidden' => 'Interdit',
                        'error'     => 'Erreur',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view'  => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}
