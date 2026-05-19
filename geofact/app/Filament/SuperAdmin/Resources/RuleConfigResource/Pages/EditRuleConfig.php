<?php

namespace App\Filament\SuperAdmin\Resources\RuleConfigResource\Pages;

use App\Filament\SuperAdmin\Resources\RuleConfigResource;
use Filament\Resources\Pages\EditRecord;

class EditRuleConfig extends EditRecord
{
    protected static string $resource = RuleConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
