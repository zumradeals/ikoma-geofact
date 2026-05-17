<?php

namespace App\Events;

use App\Models\Insight;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InsightGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Insight $insight) {}
}
