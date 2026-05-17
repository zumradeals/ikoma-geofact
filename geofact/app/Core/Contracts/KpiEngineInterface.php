<?php

namespace App\Core\Contracts;

use App\Core\Canonical\CanonicalEvent;

/**
 * Contrat C-05 : le KPI Engine est déclenché par CanonicalEventReceived (RT) ou par le Scheduler (DF).
 * Les deux pipelines RT et DF sont indépendants — jamais dans le même pipeline.
 */
interface KpiEngineInterface
{
    /**
     * Calcule les KPI en temps réel depuis l'événement canonique.
     * INSERT uniquement dans kpi_records — jamais UPDATE.
     */
    public function compute(CanonicalEvent $event): void;
}
