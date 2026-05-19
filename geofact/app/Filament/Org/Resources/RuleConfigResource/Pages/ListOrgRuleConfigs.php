<?php

namespace App\Filament\Org\Resources\RuleConfigResource\Pages;

use App\Filament\Org\Resources\RuleConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrgRuleConfigs extends ListRecords
{
    protected static string $resource = RuleConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Ajouter une surcharge'),
        ];
    }
}
