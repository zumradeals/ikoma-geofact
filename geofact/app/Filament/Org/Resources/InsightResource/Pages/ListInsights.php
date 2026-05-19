<?php

namespace App\Filament\Org\Resources\InsightResource\Pages;

use App\Filament\Org\Resources\InsightResource;
use App\Insight\InsightEngine;
use App\Models\Fleet;
use App\Models\Vehicle;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListInsights extends ListRecords
{
    protected static string $resource = InsightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_fleet_insight')
                ->label('Insight flotte')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->form([
                    Select::make('fleet_id')
                        ->label('Flotte')
                        ->options(fn () => Fleet::where('organization_id', Auth::user()?->organization_id)
                            ->where('status', 'active')
                            ->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    Select::make('period')
                        ->label('Période')
                        ->options(['7' => '7 jours', '14' => '14 jours', '30' => '30 jours'])
                        ->default('7')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $orgId   = Auth::user()?->organization_id;
                    $engine  = app(InsightEngine::class);
                    $from    = Carbon::now()->subDays((int) $data['period'])->startOfDay();
                    $insight = $engine->generate('fleet', $data['fleet_id'], $orgId, $from, Carbon::now());

                    if ($insight) {
                        Notification::make()->title('Insight flotte v' . $insight->version)->body($insight->insight_text)->success()->send();
                    } else {
                        Notification::make()->title('IA indisponible ou données insuffisantes')->warning()->send();
                    }
                }),

            Action::make('generate_vehicle_insight')
                ->label('Insight véhicule')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->form([
                    Select::make('vehicle_id')
                        ->label('Véhicule')
                        ->options(fn () => Vehicle::where('organization_id', Auth::user()?->organization_id)
                            ->where('status', 'active')
                            ->get()
                            ->mapWithKeys(fn ($v) => [$v->id => "{$v->name} ({$v->plate})"]))
                        ->required()
                        ->searchable(),
                    Select::make('period')
                        ->label('Période')
                        ->options(['7' => '7 jours', '14' => '14 jours', '30' => '30 jours'])
                        ->default('7')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $orgId   = Auth::user()?->organization_id;
                    $engine  = app(InsightEngine::class);
                    $from    = Carbon::now()->subDays((int) $data['period'])->startOfDay();
                    $insight = $engine->generate('vehicle', $data['vehicle_id'], $orgId, $from, Carbon::now());

                    if ($insight) {
                        Notification::make()->title('Insight véhicule v' . $insight->version)->body($insight->insight_text)->success()->send();
                    } else {
                        Notification::make()->title('IA indisponible ou données insuffisantes')->warning()->send();
                    }
                }),
        ];
    }
}
