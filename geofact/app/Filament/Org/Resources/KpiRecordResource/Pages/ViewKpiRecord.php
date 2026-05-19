<?php

namespace App\Filament\Org\Resources\KpiRecordResource\Pages;

use App\Filament\Org\Resources\KpiRecordResource;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewKpiRecord extends ViewRecord
{
    protected static string $resource = KpiRecordResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Infolists\Components\TextEntry::make('kpi_type')->label('Type KPI'),
            Infolists\Components\TextEntry::make('mode')->label('Mode'),
            Infolists\Components\TextEntry::make('scope_type')->label('Scope type'),
            Infolists\Components\TextEntry::make('scope_id')->label('Scope ID'),
            Infolists\Components\TextEntry::make('value')->label('Valeur'),
            Infolists\Components\TextEntry::make('unit')->label('Unité'),
            Infolists\Components\TextEntry::make('version')->label('Version'),
            Infolists\Components\TextEntry::make('computed_at')->label('Calculé le')->dateTime('d/m/Y H:i'),
        ]);
    }
}
