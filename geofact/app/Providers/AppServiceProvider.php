<?php

namespace App\Providers;

use App\Delivery\DeliveryEngine;
use App\Events\AlertTriggered;
use App\Events\CanonicalEventReceived;
use App\Kpi\RealTime\RtKpiEngine;
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

        // CanonicalEventReceived → RtKpiEngine (C-05 pipeline RT)
        Event::listen(CanonicalEventReceived::class, RtKpiEngine::class);

        // AlertTriggered → DeliveryEngine (C-07 — jamais d'appel direct depuis Rules)
        Event::listen(AlertTriggered::class, DeliveryEngine::class);
    }
}
