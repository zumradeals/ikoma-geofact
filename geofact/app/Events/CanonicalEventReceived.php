<?php

namespace App\Events;

use App\Core\Canonical\CanonicalEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CanonicalEventReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly CanonicalEvent $event) {}
}
