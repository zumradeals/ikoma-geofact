<?php

namespace App\Filament\SuperAdmin\Resources\RuleConfigResource\Pages;

use App\Filament\SuperAdmin\Resources\RuleConfigResource;
use Filament\Resources\Pages\ListRecords;

class ListRuleConfigs extends ListRecords
{
    protected static string $resource = RuleConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
