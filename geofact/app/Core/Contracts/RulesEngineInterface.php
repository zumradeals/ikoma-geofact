<?php

namespace App\Core\Contracts;

use App\Core\Canonical\CanonicalEvent;

/**
 * Contrat C-04 : le Rules Engine évalue les événements canoniques — RS avant RMC, jamais de court-circuit entre RS.
 */
interface RulesEngineInterface
{
    /**
     * Évalue un CanonicalEvent et retourne les Alerts déclenchées (tableau de Models Alert).
     * L'implémentation est interdite de modifier le CanonicalEvent.
     *
     * @return array<\App\Models\Alert>
     */
    public function evaluate(CanonicalEvent $event): array;
}
