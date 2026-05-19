<?php

namespace App\Filament\Org\Resources\RuleConfigResource\Pages;

use App\Filament\Org\Resources\RuleConfigResource;
use App\Rules\Engine\RuleConfigResolver;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateOrgRuleConfig extends CreateRecord
{
    protected static string $resource = RuleConfigResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = Auth::user()?->organization_id;
        $data['updated_by']      = Auth::id();
        return $data;
    }

    protected function afterCreate(): void
    {
        RuleConfigResolver::clearCache(
            $this->record->rule_id,
            $this->record->organization_id
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
