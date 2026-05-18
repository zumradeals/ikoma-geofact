<?php

namespace App\Filament\Org\Resources;

use App\Exceptions\ContractViolationException;
use App\Filament\Org\Resources\AlertResource\Pages;
use App\Models\Alert;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AlertResource extends Resource
{
    protected static ?string $model = Alert::class;
    protected static ?int $navigationSort = 1;
    protected static ?string $label = 'Alerte';
    protected static ?string $pluralLabel = 'Alertes';

    public static function getNavigationGroup(): ?string { return 'Sécurité'; }
    public static function getNavigationIcon(): string { return 'heroicon-o-bell-alert'; }

    // C-13 : alertes jamais créées depuis le dashboard
    public static function canCreate(): bool { return false; }

    // C-13 : suppression physique interdite
    public static function canDelete(Model $record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Placeholder::make('rule_id')
                ->label('Règle déclenchante')
                ->content(fn (Alert $record) => $record->rule_id),

            Forms\Components\Placeholder::make('event_type')
                ->label("Type d'événement")
                ->content(fn (Alert $record) => $record->event_type),

            Forms\Components\Placeholder::make('severity')
                ->label('Sévérité')
                ->content(fn (Alert $record) => $record->severity),

            Forms\Components\Placeholder::make('triggered_at')
                ->label('Déclenchée le')
                ->content(fn (Alert $record) => $record->triggered_at?->format('d/m/Y H:i')),

            Forms\Components\Textarea::make('resolution_note')
                ->label('Note de résolution')
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('triggered_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('severity')
                    ->label('Sévérité')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'LOW'      => 'gray',
                        'MEDIUM'   => 'warning',
                        'HIGH', 'CRITICAL' => 'danger',
                        default    => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('vehicle.plate')
                    ->label('Véhicule')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('rule_id')
                    ->label('Règle')
                    ->badge(),

                Tables\Columns\TextColumn::make('event_type')
                    ->label('Événement')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'acknowledged', 'resolved' => 'success',
                        'triggered', 'delivered'   => 'warning',
                        'escalated'                => 'danger',
                        default                    => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('triggered_at')
                    ->label('Déclenchée')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('severity')
                    ->label('Sévérité')
                    ->options([
                        'LOW'      => 'Faible',
                        'MEDIUM'   => 'Moyenne',
                        'HIGH'     => 'Haute',
                        'CRITICAL' => 'Critique',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'open'         => 'Ouvert',
                        'triggered'    => 'Déclenchée',
                        'delivered'    => 'Livrée',
                        'acknowledged' => 'Prise en compte',
                        'escalated'    => 'Escaladée',
                        'resolved'     => 'Résolue',
                        'expired'      => 'Expirée',
                    ]),

                Tables\Filters\Filter::make('unresolved')
                    ->label('Non résolues')
                    ->query(fn ($query) => $query->whereNotIn('status', ['resolved', 'expired']))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('acknowledge')
                    ->label('Prendre en compte')
                    ->icon('heroicon-o-check')
                    ->color('info')
                    ->visible(fn (Alert $record) => in_array($record->status, ['open', 'triggered', 'delivered']))
                    ->action(function (Alert $record) {
                        $record->update([
                            'status'          => 'acknowledged',
                            'acknowledged_at' => now(),
                        ]);
                        Notification::make()->title('Alerte prise en compte.')->success()->send();
                    }),

                Tables\Actions\Action::make('resolve')
                    ->label('Résoudre')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Alert $record) => ! in_array($record->status, ['resolved', 'expired']))
                    ->form([
                        Forms\Components\Textarea::make('resolution_note')
                            ->label('Note de résolution (obligatoire)')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Alert $record, array $data) {
                        try {
                            $record->update([
                                'status'          => 'resolved',
                                'resolved_at'     => now(),
                                'resolution_note' => $data['resolution_note'],
                            ]);
                            Notification::make()->title('Alerte résolue.')->success()->send();
                        } catch (ContractViolationException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlerts::route('/'),
            'view'  => Pages\ViewAlert::route('/{record}'),
        ];
    }
}
