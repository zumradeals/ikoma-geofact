<?php

namespace App\Filament\Org\Resources;

use App\Filament\Org\Resources\KpiRecordResource\Pages;
use App\Models\KpiRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class KpiRecordResource extends Resource
{
    protected static ?string $model = KpiRecord::class;
    protected static ?int $navigationSort = 1;
    protected static ?string $label = 'KPI';
    protected static ?string $pluralLabel = 'KPIs';

    public static function getNavigationIcon(): string { return 'heroicon-o-chart-bar'; }
    public static function getNavigationGroup(): ?string { return 'Analytique'; }

    // DC-12 : KpiRecord est immuable — 0 create/edit/delete
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

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
            ->defaultSort('computed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('kpi_type')
                    ->label('Type KPI')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('scope_type')
                    ->label('Scope')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'vehicle'      => 'success',
                        'driver'       => 'warning',
                        'fleet'        => 'info',
                        'organization' => 'danger',
                        default        => 'gray',
                    }),

                Tables\Columns\TextColumn::make('scope_id')
                    ->label('ID Scope')
                    ->limit(8)
                    ->tooltip(fn ($record) => $record->scope_id),

                Tables\Columns\TextColumn::make('mode')
                    ->label('Mode')
                    ->badge()
                    ->color(fn (string $state) => $state === 'RT' ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('value')
                    ->label('Valeur')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unité')
                    ->default('—'),

                Tables\Columns\TextColumn::make('version')
                    ->label('Ver.')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('period_from')
                    ->label('Période')
                    ->formatStateUsing(fn ($state, KpiRecord $record) =>
                        ($record->period_from?->format('d/m/Y') ?? '—') . ' → ' . ($record->period_to?->format('d/m/Y') ?? '—')
                    ),

                Tables\Columns\TextColumn::make('computed_at')
                    ->label('Calculé')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kpi_type')
                    ->label('Type KPI')
                    ->options([
                        'driver_score'         => 'Score conducteur',
                        'fleet_utilization'    => 'Utilisation flotte',
                        'overspeed_count'      => 'Excès de vitesse',
                        'current_speed'        => 'Vitesse courante',
                        'vehicle_status'       => 'Statut véhicule',
                        'active_trip_duration' => 'Durée trajet actif',
                    ])
                    ->searchable(),

                Tables\Filters\SelectFilter::make('scope_type')
                    ->label('Scope')
                    ->options([
                        'vehicle'      => 'Véhicule',
                        'driver'       => 'Conducteur',
                        'fleet'        => 'Flotte',
                        'organization' => 'Organisation',
                    ]),

                Tables\Filters\SelectFilter::make('mode')
                    ->label('Mode')
                    ->options(['RT' => 'Temps réel', 'DF' => 'Différé']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKpiRecords::route('/'),
            'view'  => Pages\ViewKpiRecord::route('/{record}'),
        ];
    }
}
