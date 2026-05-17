<?php

namespace App\Core\Contracts;

use App\Models\Alert;

/**
 * Contrat C-07 : le Delivery Engine achemine les Alerts — le Rules Engine ne connaît pas Delivery.
 * Déclenché exclusivement via AlertTriggered Event — jamais d'appel direct.
 */
interface DeliveryEngineInterface
{
    /**
     * Achemine l'Alert vers les canaux configurés pour l'organisation.
     */
    public function deliver(Alert $alert): void;
}
