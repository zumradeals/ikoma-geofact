<?php

namespace App\Filament\Org\Resources;

use App\Filament\Org\Resources\RuleConfigResource\Pages;
use App\Models\RuleConfig;
use App\Rules\Engine\RuleConfigResolver;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * OrgAdmin — surcharge des paramètres de règles pour l'organisation.
 * Les valeurs NULL tombent en cascade sur les défauts système (SuperAdmin).
 */
class RuleConfigResource extends Resource
{
    protected static ?string $model       = RuleConfig::class;
    protected static ?string $label       = 'Règle';
    protected static ?string $pluralLabel = 'Paramètres des règles';

    public static function getNavigationIcon(): string   { return 'heroicon-o-adjustments-horizontal'; }
    public static function getNavigationGroup(): ?string { return 'Configuration'; }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', Auth::user()?->organization_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Règle')->schema([
                Forms\Components\Select::make('rule_id')
                    ->label('Règle')
                    ->options([
                        'RS01' => 'RS01 — Excès de vitesse',
                        'RS02' => 'RS02 — Arrêt prolongé suspect',
                        'RS03' => 'RS03 — Freinage brusque',
                        'RS04' => 'RS04 — Entrée/sortie géozone',
                        'RS05' => 'RS05 — Seuil de maintenance',
                    ])
                    ->required()
                    ->live()
                    ->disabledOn('edit'),

                Forms\Components\Toggle::make('is_enabled')
                    ->label('Règle active pour cette organisation')
                    ->default(true)
                    ->required(),
            ]),

            Section::make('Paramètres de surcharge')->schema([
                Forms\Components\Placeholder::make('info')
                    ->label('')
                    ->content('Les valeurs laissées vides utilisent les défauts système définis par le SuperAdmin.'),

                // RS01
                Forms\Components\TextInput::make('params.threshold_kmh')
                    ->label('Seuil vitesse (km/h)')
                    ->numeric()->minValue(10)->maxValue(250)
                    ->visible(fn ($get) => $get('rule_id') === 'RS01'),
                Forms\Components\TextInput::make('params.severity_medium_pct')
                    ->label('% dépassement → MEDIUM')
                    ->numeric()->minValue(1)->maxValue(100)
                    ->visible(fn ($get) => $get('rule_id') === 'RS01'),
                Forms\Components\TextInput::make('params.severity_high_pct')
                    ->label('% dépassement → HIGH')
                    ->numeric()->minValue(1)->maxValue(100)
                    ->visible(fn ($get) => $get('rule_id') === 'RS01'),

                // RS02
                Forms\Components\TextInput::make('params.threshold_hours')
                    ->label('Durée arrêt suspect (heures)')
                    ->numeric()->minValue(1)->maxValue(72)
                    ->visible(fn ($get) => $get('rule_id') === 'RS02'),

                // RS03
                Forms\Components\TextInput::make('params.deceleration_ms2')
                    ->label('Décélération brusque (m/s²)')
                    ->numeric()->minValue(1)->maxValue(20)->step(0.1)
                    ->visible(fn ($get) => $get('rule_id') === 'RS03'),

                // RS04
                Forms\Components\Toggle::make('params.alert_on_entry')
                    ->label('Alerte à l\'entrée de zone')
                    ->visible(fn ($get) => $get('rule_id') === 'RS04'),
                Forms\Components\Toggle::make('params.alert_on_exit')
                    ->label('Alerte à la sortie de zone')
                    ->visible(fn ($get) => $get('rule_id') === 'RS04'),

                // RS05
                Forms\Components\TextInput::make('params.threshold_km')
                    ->label('Kilométrage avant maintenance (km)')
                    ->numeric()->minValue(1000)->maxValue(100000)
                    ->visible(fn ($get) => $get('rule_id') === 'RS05'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rule_id')
                    ->label('Règle')
                    ->badge()->color('info')->sortable(),

                Tables\Columns\TextColumn::make('rule_id')
                    ->label('Description')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'RS01' => 'Excès de vitesse',
                        'RS02' => 'Arrêt prolongé suspect',
                        'RS03' => 'Freinage brusque',
                        'RS04' => 'Entrée/sortie géozone',
                        'RS05' => 'Seuil de maintenance',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('params')
                    ->label('Paramètres')
                    ->formatStateUsing(function ($state, RuleConfig $record) {
                        if (empty($state)) {
                            return '— défauts système —';
                        }
                        return collect($state)->map(fn ($v, $k) => "$k: $v")->implode(' | ');
                    }),

                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modifié')
                    ->since(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter une surcharge')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['organization_id'] = Auth::user()?->organization_id;
                        $data['updated_by']      = Auth::id();
                        return $data;
                    })
                    ->after(function (RuleConfig $record): void {
                        RuleConfigResolver::clearCache($record->rule_id, $record->organization_id);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['updated_by'] = Auth::id();
                        return $data;
                    })
                    ->after(function (RuleConfig $record): void {
                        RuleConfigResolver::clearCache($record->rule_id, $record->organization_id);
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Réinitialiser')
                    ->modalHeading('Réinitialiser aux défauts système ?')
                    ->after(function (RuleConfig $record): void {
                        RuleConfigResolver::clearCache($record->rule_id, $record->organization_id);
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading('Aucune surcharge définie')
            ->emptyStateDescription('Toutes les règles utilisent les paramètres système par défaut. Cliquez sur "Ajouter une surcharge" pour personnaliser une règle.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrgRuleConfigs::route('/'),
        ];
    }
}
