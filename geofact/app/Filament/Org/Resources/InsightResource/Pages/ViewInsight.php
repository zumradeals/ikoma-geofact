<?php

namespace App\Filament\Org\Resources\InsightResource\Pages;

use App\Filament\Org\Resources\InsightResource;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewInsight extends ViewRecord
{
    protected static string $resource = InsightResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Infolists\Components\TextEntry::make('insight_type')->label('Type'),
            Infolists\Components\TextEntry::make('confidence_level')->label('Confiance'),
            Infolists\Components\TextEntry::make('scope_type')->label('Scope type'),
            Infolists\Components\TextEntry::make('scope_id')->label('Scope ID'),
            Infolists\Components\TextEntry::make('language')->label('Langue'),
            Infolists\Components\TextEntry::make('version')->label('Version'),
            Infolists\Components\TextEntry::make('insight_text')
                ->label("Texte de l'insight")
                ->columnSpanFull(),
        ]);
    }
}
