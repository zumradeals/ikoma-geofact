<?php

namespace App\Delivery;

/**
 * Destinataire d'une livraison — jamais une adresse brute (C-07.7).
 * Rattaché au tenant : chargé depuis la configuration ou les User avec rôle approprié.
 */
readonly class Contact
{
    public function __construct(
        public string  $name,
        public ?string $email      = null,
        public ?string $phone      = null,   // numéro E.164 pour WhatsApp
        public ?string $webhookUrl = null,
    ) {}
}
