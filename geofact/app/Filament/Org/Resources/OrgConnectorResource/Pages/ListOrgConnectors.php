<?php

namespace App\Filament\Org\Resources\OrgConnectorResource\Pages;

use App\Filament\Org\Resources\OrgConnectorResource;
use Filament\Resources\Pages\ListRecords;

class ListOrgConnectors extends ListRecords
{
    protected static string $resource = OrgConnectorResource::class;

    protected function getHeaderActions(): array { return []; }
}
