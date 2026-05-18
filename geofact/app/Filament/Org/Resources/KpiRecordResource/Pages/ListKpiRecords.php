<?php

namespace App\Filament\Org\Resources\KpiRecordResource\Pages;

use App\Filament\Org\Resources\KpiRecordResource;
use Filament\Resources\Pages\ListRecords;

class ListKpiRecords extends ListRecords
{
    protected static string $resource = KpiRecordResource::class;

    protected function getHeaderActions(): array { return []; }
}
