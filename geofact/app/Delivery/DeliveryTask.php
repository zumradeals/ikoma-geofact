<?php

namespace App\Delivery;

use App\Models\Alert;

/**
 * Tâche de livraison atomique — un destinataire, un canal, une Alert.
 */
readonly class DeliveryTask
{
    public function __construct(
        public Alert   $alert,
        public Contact $contact,
        public string  $channel,      // whatsapp | email | api
        public string  $policyCode,   // SP-01, SP-02, CDP-xxx
    ) {}
}
