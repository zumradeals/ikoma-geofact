<?php

namespace App\Filament\Org\Resources;

use App\Filament\Org\Resources\InsightResource\Pages;
use App\Insight\InsightEngine;
use App\Models\Insight;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class InsightResource extends Resource
{
    protected static ?string $model = Insight::class;
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Insight IA';
    protected static ?string $pluralLabel = 'Insights IA';

    public static function getNavigationIcon(): string { return 'heroicon-o-sparkles'; }
    public static function getNavigationGroup(): ?string { return 'Analytique'; }

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
            ->defaultSort('generated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('insight_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'anomaly'     => 'danger',
                        'trend'       => 'info',
                        'performance' => 'success',
                        'alert'       => 'warning',
                        default       => 'gray',
                    }),

                Tables\Columns\TextColumn::make('scope_type')
                    ->label('Scope')
                    ->badge(),

                Tables\Columns\TextColumn::make('confidence_level')
                    ->label('Confiance')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'high'   => 'success',
                        'medium' => 'warning',
                        'low'    => 'danger',
                        default  => 'gray',
                    }),

                Tables\Columns\TextColumn::make('trend_direction')
                    ->label('Tendance')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'improving' => 'success',
                        'stable'    => 'info',
                        'degrading' => 'danger',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'improving' => '↑ Amélioration',
                        'stable'    => '→ Stable',
                        'degrading' => '↓ Dégradation',
                        default     => '—',
                    }),

                Tables\Columns\TextColumn::make('risk_score')
                    ->label('Risque')
                    ->badge()
                    ->color(fn (?float $state) => match(true) {
                        $state === null => 'gray',
                        $state >= 7.0   => 'danger',
                        $state >= 4.0   => 'warning',
                        default         => 'success',
                    })
                    ->formatStateUsing(fn (?float $state) => $state !== null ? number_format($state, 1) . '/10' : '—'),

                Tables\Columns\IconColumn::make('follow_up_required')
                    ->label('Suivi')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('insight_text')
                    ->label('Insight')
                    ->limit(80)
                    ->wrap(),

                Tables\Columns\TextColumn::make('version')
                    ->label('Ver.')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('generated_at')
                    ->label('Généré')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('insight_type')
                    ->label('Type')
                    ->options([
                        'anomaly'     => 'Anomalie',
                        'trend'       => 'Tendance',
                        'performance' => 'Performance',
                        'alert'       => 'Alerte',
                        'summary'     => 'Résumé',
                    ]),

                Tables\Filters\SelectFilter::make('confidence_level')
                    ->label('Confiance')
                    ->options(['high' => 'Haute', 'medium' => 'Moyenne', 'low' => 'Faible']),

                Tables\Filters\SelectFilter::make('trend_direction')
                    ->label('Tendance')
                    ->options(['improving' => 'Amélioration', 'stable' => 'Stable', 'degrading' => 'Dégradation']),

                Tables\Filters\TernaryFilter::make('follow_up_required')
                    ->label('Suivi requis'),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('generate')
                    ->label('Générer')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Générer un nouvel Insight IA')
                    ->modalDescription('Cela appellera le pipeline Anthropic pour ce scope. Un nouvel insight (version+1) sera créé.')
                    ->action(function (Insight $record) {
                        $orgId  = Auth::user()?->organization_id;
                        $engine = app(InsightEngine::class);

                        $insight = $engine->generate(
                            $record->scope_type,
                            $record->scope_id,
                            $orgId,
                            Carbon::now()->subDays(7),
                            Carbon::now(),
                        );

                        if ($insight) {
                            Notification::make()->title('Insight généré — v' . $insight->version)->success()->send();
                        } else {
                            Notification::make()->title('IA indisponible ou données insuffisantes')->warning()->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInsights::route('/'),
            'view'  => Pages\ViewInsight::route('/{record}'),
        ];
    }
}
