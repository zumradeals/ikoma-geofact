<?php

namespace App\Providers;

use App\Delivery\DeliveryEngine;
use App\Events\AlertTriggered;
use App\Events\CanonicalEventReceived;
use App\Kpi\RealTime\RtKpiEngine;
use App\Listeners\ComputeRealTimeKpiListener;
use App\Listeners\UpdateVehicleCurrentPositionListener;
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

        // CanonicalEventReceived → RtKpiEngine (C-05 pipeline RT — KPIs instantanés)
        Event::listen(CanonicalEventReceived::class, RtKpiEngine::class);

        // CanonicalEventReceived -> projection officielle de position courante IKOMA
        Event::listen(CanonicalEventReceived::class, UpdateVehicleCurrentPositionListener::class);

        // CanonicalEventReceived → KPIs agrégés journaliers (distance, vitesse, temps actif)
        // Alimente le paquet IA en données fraîches pendant la journée
        Event::listen(CanonicalEventReceived::class, ComputeRealTimeKpiListener::class);

        // AlertTriggered → DeliveryEngine (C-07 — jamais d'appel direct depuis Rules)
        Event::listen(AlertTriggered::class, DeliveryEngine::class);
    }
}
