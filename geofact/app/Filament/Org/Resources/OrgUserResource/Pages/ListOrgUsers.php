<?php
namespace App\Filament\Org\Resources\OrgUserResource\Pages;
use App\Filament\Org\Resources\OrgUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListOrgUsers extends ListRecords {
    protected static string $resource = OrgUserResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
