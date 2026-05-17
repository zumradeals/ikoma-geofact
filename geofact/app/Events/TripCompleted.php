<?php

namespace App\Events;

use App\Models\Trip;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Trip $trip) {}
}
