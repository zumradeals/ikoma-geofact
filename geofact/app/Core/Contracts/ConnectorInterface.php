<?php

namespace App\Core\Contracts;

use App\Core\Canonical\CanonicalEvent;

/**
 * Contrat C-01 : le Connector Layer produit des CanonicalEvents — le Core ne connaît aucun protocole de transport.
 */
interface ConnectorInterface
{
    /**
     * Transforme un payload brut en CanonicalEvent immuable.
     */
    public function produceCanonicalEvent(array $raw): CanonicalEvent;
}
