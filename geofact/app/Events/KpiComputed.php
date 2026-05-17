<?php

namespace App\Events;

use App\Models\KpiRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KpiComputed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly KpiRecord $kpiRecord) {}
}
