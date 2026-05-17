<?php

namespace Tests\Feature;

use App\Delivery\Policies\SystemPoliciesEvaluator;
use App\Exceptions\ContractViolationException;
use App\Models\Alert;
use App\Models\Fleet;
use App\Models\Organization;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Valide C-07 — Delivery Engine : SP non désactivables, escalade, DC-09.
 */
class AlertEscalationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Fleet $fleet;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'id' => Str::uuid()->toString(), 'name' => 'Test Org',
            'country_code' => 'CI', 'timezone' => 'Africa/Abidjan',
            'status' => 'active', 'created_by' => Str::uuid()->toString(),
            'created_at' => now(),
        ]);

        $this->fleet = Fleet::create([
            'id' => Str::uuid()->toString(), 'organization_id' => $this->org->id,
            'name' => 'Test Fleet', 'status' => 'active',
            'created_by' => Str::uuid()->toString(), 'created_at' => now(),
        ]);

        $this->vehicle = Vehicle::create([
            'id' => Str::uuid()->toString(), 'fleet_id' => $this->fleet->id,
            'organization_id' => $this->org->id, 'name' => 'VH-001',
            'plate' => 'CI-001-A', 'status' => 'active',
            'created_by' => Str::uuid()->toString(), 'created_at' => now(),
        ]);
    }

    #[Test]
    public function test_system_policy_cannot_be_disabled(): void
    {
        // SP-01 : alerte CRITICAL produit toujours des DeliveryTasks (C-07.2)
        $evaluator = new SystemPoliciesEvaluator();
        $alert     = $this->makeCriticalAlert();

        $tasks = $evaluator->evaluate($alert);

        // Au moins une tâche de livraison doit être produite pour CRITICAL
        $this->assertNotEmpty($tasks);
        $this->assertSame('SP-01', $tasks[0]->policyCode);
    }

    #[Test]
    public function test_critical_alert_triggers_sp01(): void
    {
        $evaluator = new SystemPoliciesEvaluator();
        $alert     = $this->makeCriticalAlert();

        $tasks     = $evaluator->evaluate($alert);
        $policyCodes = array_map(fn($t) => $t->policyCode, $tasks);

        $this->assertContains('SP-01', $policyCodes);

        // SP-01 doit produire email ET whatsapp
        $channels = array_map(fn($t) => $t->channel, array_filter($tasks, fn($t) => $t->policyCode === 'SP-01'));
        $this->assertContains('email', $channels);
        $this->assertContains('whatsapp', $channels);
    }

    #[Test]
    public function test_unacknowledged_alert_can_be_escalated(): void
    {
        $alert = Alert::create([
            'id'              => Str::uuid()->toString(),
            'organization_id' => $this->org->id,
            'fleet_id'        => $this->fleet->id,
            'vehicle_id'      => $this->vehicle->id,
            'rule_id'         => 'RS01',
            'rule_type'       => 'RS',
            'event_type'      => 'alert.overspeed.detected',
            'severity'        => 'HIGH',
            'status'          => 'open',
            'triggered_at'    => now()->subHours(3),
            'created_at'      => now()->subHours(3),
        ]);

        // Simule l'escalade (EscalateUnacknowledgedAlerts)
        Alert::withoutEvents(function () use ($alert) {
            $alert->status       = 'escalated';
            $alert->escalated_at = now();
            $alert->save();
        });

        $this->assertSame('escalated', $alert->fresh()->status);
        $this->assertNotNull($alert->fresh()->escalated_at);
    }

    #[Test]
    public function test_alert_physical_deletion_is_forbidden(): void
    {
        $alert = $this->makeCriticalAlert();

        $this->expectException(ContractViolationException::class);
        $alert->delete();
    }

    #[Test]
    public function test_resolved_alert_requires_resolution_note(): void
    {
        $alert = $this->makeCriticalAlert();

        $this->expectException(ContractViolationException::class);

        // Tenter de résoudre sans resolution_note doit lancer une exception (DC-09)
        $alert->status = 'resolved';
        // resolution_note non renseignée intentionnellement
        $alert->save();
    }

    #[Test]
    public function test_resolved_alert_with_note_succeeds(): void
    {
        $alert = $this->makeCriticalAlert();

        $alert->status          = 'resolved';
        $alert->resolved_at     = now();
        $alert->resolution_note = 'Situation resolved by supervisor.';
        $alert->save();

        $this->assertSame('resolved', $alert->fresh()->status);
        $this->assertNotEmpty($alert->fresh()->resolution_note);
    }

    private function makeCriticalAlert(): Alert
    {
        return Alert::create([
            'id'              => Str::uuid()->toString(),
            'organization_id' => $this->org->id,
            'fleet_id'        => $this->fleet->id,
            'vehicle_id'      => $this->vehicle->id,
            'rule_id'         => 'RS01',
            'rule_type'       => 'RS',
            'event_type'      => 'alert.overspeed.detected',
            'severity'        => 'CRITICAL',
            'status'          => 'open',
            'triggered_at'    => now(),
            'created_at'      => now(),
        ]);
    }
}
