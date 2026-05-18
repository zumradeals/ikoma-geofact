<?php

namespace App\Filament\Org\Resources\KpiRecordResource\Pages;

use App\Filament\Org\Resources\KpiRecordResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Forms;

class ViewKpiRecord extends ViewRecord
{
    protected static string $resource = KpiRecordResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('kpi_type')->label('Type KPI')->disabled(),
                Forms\Components\TextInput::make('mode')->label('Mode')->disabled(),
                Forms\Components\TextInput::make('scope_type')->label('Scope type')->disabled(),
                Forms\Components\TextInput::make('scope_id')->label('Scope ID')->disabled(),
                Forms\Components\TextInput::make('value')->label('Valeur')->disabled(),
                Forms\Components\TextInput::make('unit')->label('Unité')->disabled(),
                Forms\Components\TextInput::make('version')->label('Version')->disabled(),
                Forms\Components\TextInput::make('computed_at')->label('Calculé le')->disabled(),
            ]),
        ]);
    }
}
