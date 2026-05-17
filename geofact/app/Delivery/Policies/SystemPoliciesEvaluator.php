<?php

namespace App\Delivery\Policies;

use App\Delivery\Contact;
use App\Delivery\DeliveryTask;
use App\Models\Alert;

/**
 * Évalue les System Policies (SP) — non désactivables, évaluées avant CDP (C-07.1 + C-07.2).
 *
 * SP-01 : severity=CRITICAL           → Email + WhatsApp → Admin GEOFACT + Contact sécurité
 * SP-02 : event_type starts with system.*  → Email → Admin GEOFACT
 * SP-03 : event_type starts with connector.* → Email → Admin GEOFACT + Intégrateur
 * SP-04 : system.kpi.computation.failed → Email → Admin GEOFACT
 */
class SystemPoliciesEvaluator
{
    /** @return DeliveryTask[] */
    public function evaluate(Alert $alert): array
    {
        $tasks = [];

        $adminContact      = $this->geofactAdmin();
        $securityContact   = $this->securityContact();
        $integratorContact = $this->integratorContact();

        // SP-01 — Alerte critique → Email + WhatsApp → Admin + Sécurité
        if ($alert->severity === 'CRITICAL') {
            $tasks[] = new DeliveryTask($alert, $adminContact,    'email',    'SP-01');
            $tasks[] = new DeliveryTask($alert, $adminContact,    'whatsapp', 'SP-01');
            if ($securityContact !== null) {
                $tasks[] = new DeliveryTask($alert, $securityContact, 'email',    'SP-01');
                $tasks[] = new DeliveryTask($alert, $securityContact, 'whatsapp', 'SP-01');
            }
        }

        // SP-02 — Événements système → Email → Admin
        if (str_starts_with($alert->event_type, 'system.')) {
            $tasks[] = new DeliveryTask($alert, $adminContact, 'email', 'SP-02');
        }

        // SP-03 — Incidents connecteur → Email → Admin + Intégrateur
        if (str_starts_with($alert->event_type, 'connector.')) {
            $tasks[] = new DeliveryTask($alert, $adminContact, 'email', 'SP-03');
            if ($integratorContact !== null) {
                $tasks[] = new DeliveryTask($alert, $integratorContact, 'email', 'SP-03');
            }
        }

        // SP-04 — Échec KPI DF → Email → Admin
        if ($alert->event_type === 'system.kpi.computation.failed') {
            $tasks[] = new DeliveryTask($alert, $adminContact, 'email', 'SP-04');
        }

        return $tasks;
    }

    private function geofactAdmin(): Contact
    {
        return new Contact(
            name:  config('geofact.admin_name',  'Admin GEOFACT'),
            email: config('geofact.admin_email', env('GEOFACT_ADMIN_EMAIL', '')),
            phone: config('geofact.admin_phone', env('GEOFACT_ADMIN_PHONE', '')),
        );
    }

    private function securityContact(): ?Contact
    {
        $email = env('GEOFACT_SECURITY_EMAIL');
        $phone = env('GEOFACT_SECURITY_PHONE');
        if (empty($email) && empty($phone)) {
            return null;
        }
        return new Contact(name: 'Contact Sécurité', email: $email, phone: $phone);
    }

    private function integratorContact(): ?Contact
    {
        $email = env('GEOFACT_INTEGRATOR_EMAIL');
        if (empty($email)) {
            return null;
        }
        return new Contact(name: 'Intégrateur Certifié', email: $email);
    }
}
