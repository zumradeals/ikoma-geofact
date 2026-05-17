<?php

namespace App\Providers;

use App\Events\CanonicalEventReceived;
use App\Rules\Engine\RulesEngine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // CanonicalEventReceived → RulesEngine (C-04 + C-10 isolation)
        Event::listen(CanonicalEventReceived::class, RulesEngine::class);
    }
}
