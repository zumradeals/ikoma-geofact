<?php
namespace App\Filament\Org\Resources\OrgUserResource\Pages;
use App\Filament\Org\Resources\OrgUserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
class CreateOrgUser extends CreateRecord {
    protected static string $resource = OrgUserResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array {
        $data['id']              = Str::uuid()->toString();
        $data['organization_id'] = auth()->user()->organization_id;
        $data['token_version']   = 1;
        $data['created_by']      = auth()->id();
        $data['created_at']      = now();
        return $data;
    }
}
