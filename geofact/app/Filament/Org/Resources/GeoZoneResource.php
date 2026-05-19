<?php

namespace App\Filament\Org\Resources;

use App\Filament\Org\Resources\GeoZoneResource\Pages;
use App\Models\Fleet;
use App\Models\GeoZone;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GeoZoneResource extends Resource
{
    protected static ?string $model = GeoZone::class;
    protected static ?int $navigationSort = 4;
    protected static ?string $label = 'Géozone';
    protected static ?string $pluralLabel = 'Géozones';

    public static function getNavigationIcon(): string { return 'heroicon-o-map'; }
    public static function getNavigationGroup(): ?string { return 'Flotte'; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', Auth::user()?->organization_id);
    }

    public static function form(Schema $schema): Schema
    {
        $orgId = Auth::user()?->organization_id;

        return $schema->schema([
            Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nom de la zone')
                    ->required()
                    ->maxLength(100),

                Forms\Components\Select::make('zone_type')
                    ->label('Type')
                    ->options([
                        'circle'    => 'Cercle',
                        'polygon'   => 'Polygone',
                        'rectangle' => 'Rectangle',
                    ])
                    ->required()
                    ->live(),

                Forms\Components\Select::make('fleet_id')
                    ->label('Flotte (optionnel)')
                    ->options(
                        Fleet::where('organization_id', $orgId)
                            ->where('status', 'active')
                            ->pluck('name', 'id')
                    )
                    ->nullable()
                    ->searchable(),

                Forms\Components\Select::make('status')
                    ->label('Statut')
                    ->options([
                        'active'   => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active')
                    ->required(),

                Forms\Components\TextInput::make('max_stay_minutes')
                    ->label('Durée max (minutes)')
                    ->numeric()
                    ->nullable()
                    ->minValue(1),

                Forms\Components\TextInput::make('version')
                    ->label('Version')
                    ->disabled()
                    ->default(1)
                    ->dehydrated(false),
            ]),

            Section::make('Géométrie')
                ->description('Définissez la forme de la zone. Pour un cercle : {"type":"circle","center":[lat,lng],"radius":500}. Pour un polygone : {"type":"polygon","coordinates":[[lat,lng],...]}')
                ->schema([
                    Forms\Components\Textarea::make('geometry')
                        ->label('Coordonnées JSON')
                        ->required()
                        ->rows(4)
                        ->helperText('Format JSON. Modifier la géométrie d\'une zone active incrémente automatiquement la version.')
                        ->afterStateUpdated(null),
                ]),

            Section::make('Aperçu carte')
                ->schema([
                    \Filament\Forms\Components\ViewField::make('map_preview')
                        ->view('filament.org.components.geozone-map-preview'),
                ])
                ->visible(fn ($record) => $record !== null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('zone_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'circle'    => 'info',
                        'polygon'   => 'warning',
                        'rectangle' => 'success',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('fleet.name')
                    ->label('Flotte')
                    ->default('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('version')
                    ->label('Version')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('max_stay_minutes')
                    ->label('Durée max')
                    ->formatStateUsing(fn ($state) => $state ? $state . ' min' : '—'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modifiée')
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('zone_type')
                    ->label('Type')
                    ->options([
                        'circle'    => 'Cercle',
                        'polygon'   => 'Polygone',
                        'rectangle' => 'Rectangle',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive']),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('toggle')
                    ->label(fn (GeoZone $record) => $record->status === 'active' ? 'Désactiver' : 'Activer')
                    ->icon(fn (GeoZone $record) => $record->status === 'active' ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (GeoZone $record) => $record->status === 'active' ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(function (GeoZone $record) {
                        $record->update([
                            'status' => $record->status === 'active' ? 'inactive' : 'active',
                        ]);
                        Notification::make()
                            ->title('Statut mis à jour')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListGeoZones::route('/'),
            'create' => Pages\CreateGeoZone::route('/create'),
            'edit'   => Pages\EditGeoZone::route('/{record}/edit'),
            'view'   => Pages\ViewGeoZone::route('/{record}'),
        ];
    }
}
