<?php

namespace App\Filament\Org\Widgets;

use App\Models\Alert;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CriticalAlertsWidget extends BaseWidget
{
    protected static ?int $sort = 5;
    protected static ?string $heading = 'Alertes critiques non résolues';
    protected int|string|array $columnSpan = 'full';
    protected static ?string $pollingInterval = '20s';

    // N'afficher que s'il y a des alertes critiques ouvertes
    public static function canView(): bool
    {
        $orgId = Auth::user()?->organization_id;
        return Alert::where('organization_id', $orgId)
            ->where('severity', 'CRITICAL')
            ->whereNotIn('status', ['resolved', 'expired'])
            ->exists();
    }

    public function table(Table $table): Table
    {
        $orgId = Auth::user()?->organization_id;

        return $table
            ->query(
                Alert::query()
                    ->where('organization_id', $orgId)
                    ->where('severity', 'CRITICAL')
                    ->whereNotIn('status', ['resolved', 'expired'])
                    ->orderByDesc('triggered_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('vehicle.plate')
                    ->label('Véhicule')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('rule_id')
                    ->label('Règle')
                    ->badge(),

                Tables\Columns\TextColumn::make('event_type')
                    ->label('Événement')
                    ->limit(40),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'escalated' => 'danger',
                        'acknowledged' => 'success',
                        default => 'warning',
                    }),

                Tables\Columns\TextColumn::make('triggered_at')
                    ->label('Déclenchée')
                    ->dateTime('d/m/Y H:i')
                    ->since(),
            ])
            ->actions([
                Action::make('view')
                    ->label('Voir')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Alert $record) => route('filament.org.resources.alerts.view', [
                        'tenant' => Auth::user()?->organization_id,
                        'record' => $record->id,
                    ])),
            ])
            ->paginated(false);
    }
}
