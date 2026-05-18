<?php

namespace App\Filament\Org\Resources\InsightResource\Pages;

use App\Filament\Org\Resources\InsightResource;
use Filament\Resources\Pages\ListRecords;

class ListInsights extends ListRecords
{
    protected static string $resource = InsightResource::class;

    protected function getHeaderActions(): array { return []; }
}
