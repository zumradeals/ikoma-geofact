<?php

namespace App\Rules\Contracts;

use App\Core\Canonical\CanonicalEvent;
use App\Models\Alert;

interface RuleInterface
{
    /**
     * Indique si cette règle s'applique à l'événement donné.
     */
    public function applies(CanonicalEvent $event): bool;

    /**
     * Évalue l'événement et retourne une Alert non-persistée, ou null si la règle n'est pas déclenchée.
     */
    public function evaluate(CanonicalEvent $event): ?Alert;

    /**
     * Identifiant métier de la règle (ex: RS01, RMC-abc-123).
     */
    public function getRuleId(): string;

    /**
     * Type de règle : RS (système) ou RMC (client).
     */
    public function getRuleType(): string;
}
