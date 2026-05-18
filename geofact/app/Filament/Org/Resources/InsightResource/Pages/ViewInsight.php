<?php

namespace App\Filament\Org\Resources\InsightResource\Pages;

use App\Filament\Org\Resources\InsightResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Forms;

class ViewInsight extends ViewRecord
{
    protected static string $resource = InsightResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('insight_type')->label('Type')->disabled(),
                Forms\Components\TextInput::make('confidence_level')->label('Confiance')->disabled(),
                Forms\Components\TextInput::make('scope_type')->label('Scope type')->disabled(),
                Forms\Components\TextInput::make('scope_id')->label('Scope ID')->disabled(),
                Forms\Components\TextInput::make('language')->label('Langue')->disabled(),
                Forms\Components\TextInput::make('version')->label('Version')->disabled(),
            ]),
            Forms\Components\Textarea::make('insight_text')
                ->label('Texte de l\'insight')
                ->disabled()
                ->rows(6)
                ->columnSpanFull(),
        ]);
    }
}
