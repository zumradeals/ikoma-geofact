<?php
namespace App\Filament\SuperAdmin\Resources\OrganizationResource\Pages;
use App\Filament\SuperAdmin\Resources\OrganizationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditOrganization extends EditRecord {
    protected static string $resource = OrganizationResource::class;
    
    protected function getHeaderActions(): array { return []; }
}
