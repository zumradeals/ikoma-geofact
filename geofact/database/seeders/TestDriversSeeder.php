<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Driver;
use App\Models\Fleet;
use App\Models\Organization;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crée 5 conducteurs de test avec des alertes variées pour valider
 * le module de score (DriverScoreChart + DriverScoreboardPage).
 *
 * Usage : php artisan db:seed --class=TestDriversSeeder
 * Suppression : php artisan tinker -> Driver::where('license_number', 'like', 'TEST-%')->forceDelete()
 */
class TestDriversSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::first();
        if (! $org) {
            $this->command->error('Aucune organisation trouvée.');
            return;
        }

        $fleet = Fleet::where('organization_id', $org->id)->first();

        $vehicles = Vehicle::where('organization_id', $org->id)
            ->where('status', 'active')
            ->limit(5)
            ->get();

        $drivers = [
            [
                'first_name'     => 'Kouamé',
                'last_name'      => 'DIALLO',
                'phone'          => '+22507000001',
                'license_number' => 'TEST-001',
                'license_expiry' => now()->addYears(2),
                'status'         => 'active',
                // Profil : excellent (peu d'alertes)
                'alerts'         => ['medium' => 1, 'low' => 2],
            ],
            [
                'first_name'     => 'Seydou',
                'last_name'      => 'COULIBALY',
                'phone'          => '+22507000002',
                'license_number' => 'TEST-002',
                'license_expiry' => now()->addYear(),
                'status'         => 'active',
                // Profil : bon
                'alerts'         => ['high' => 1, 'medium' => 2, 'low' => 3],
            ],
            [
                'first_name'     => 'Fatou',
                'last_name'      => 'TRAORÉ',
                'phone'          => '+22507000003',
                'license_number' => 'TEST-003',
                'license_expiry' => now()->addMonths(8),
                'status'         => 'active',
                // Profil : moyen
                'alerts'         => ['high' => 3, 'medium' => 4, 'low' => 2],
            ],
            [
                'first_name'     => 'Moussa',
                'last_name'      => 'KONÉ',
                'phone'          => '+22507000004',
                'license_number' => 'TEST-004',
                'license_expiry' => now()->addMonths(3),
                'status'         => 'active',
                // Profil : mauvais (beaucoup d'alertes)
                'alerts'         => ['critical' => 2, 'high' => 4, 'medium' => 3],
            ],
            [
                'first_name'     => 'Aminata',
                'last_name'      => 'BAMBA',
                'phone'          => '+22507000005',
                'license_number' => 'TEST-005',
                'license_expiry' => now()->subMonths(1), // permis expiré
                'status'         => 'suspended',
                // Profil : très mauvais
                'alerts'         => ['critical' => 4, 'high' => 5, 'medium' => 2],
            ],
        ];

        $alertTypes = [
            'critical' => ['overspeed.critical', 'stop.suspicious'],
            'high'     => ['overspeed.detected', 'harsh.braking'],
            'medium'   => ['night.activity', 'idle.prolonged'],
            'low'      => ['door.open', 'fuel.low'],
        ];

        foreach ($drivers as $index => $data) {
            // Récupère ou crée le conducteur
            $driver = Driver::where('license_number', $data['license_number'])->first();

            if (! $driver) {
                $driver = Driver::create([
                    'id'              => Str::uuid()->toString(),
                    'organization_id' => $org->id,
                    'fleet_id'        => $fleet?->id,
                    'first_name'      => $data['first_name'],
                    'last_name'       => $data['last_name'],
                    'phone'           => $data['phone'],
                    'license_number'  => $data['license_number'],
                    'license_expiry'  => $data['license_expiry'],
                    'status'          => $data['status'],
                    'created_by'      => $org->id,
                ]);
                $this->command->line("  + Conducteur créé : {$driver->first_name} {$driver->last_name}");
            } else {
                $this->command->line("  → {$data['license_number']} déjà existant, alertes mises à jour.");
            }

            // Associer un véhicule si disponible
            $vehicle = $vehicles->get($index);

            // Associer les trips existants sans driver
            if ($vehicle) {
                Trip::where('vehicle_id', $vehicle->id)
                    ->whereNull('driver_id')
                    ->where('organization_id', $org->id)
                    ->limit(3)
                    ->each(fn ($t) => $t->update(['driver_id' => $driver->id]));
            }

            // Supprimer les alertes test existantes pour ce conducteur avant de recréer
            DB::table('alerts')
                ->where('driver_id', $driver->id)
                ->where('organization_id', $org->id)
                ->whereIn('event_type', array_merge(...array_values($alertTypes)))
                ->delete();

            // Créer des alertes représentatives sur les 30 derniers jours
            $created = 0;
            foreach ($data['alerts'] as $severity => $count) {
                $types = $alertTypes[$severity];
                for ($i = 0; $i < $count; $i++) {
                    $type = $types[$i % count($types)];
                    Alert::create([
                        'id'              => Str::uuid()->toString(),
                        'organization_id' => $org->id,
                        'vehicle_id'      => $vehicle?->id,
                        'driver_id'       => $driver->id,
                        'event_type'      => 'alert.' . $type,
                        'severity'        => $severity,
                        'status'          => 'open',
                        'triggered_at'    => now()->subDays(rand(1, 28)),
                        'created_at'      => now(),
                    ]);
                    $created++;
                }
            }

            $this->command->info("  ✓ {$data['first_name']} {$data['last_name']} — {$created} alertes créées");
        }

        $this->command->info('');
        $this->command->info('Conducteurs de test créés. Vérifiez :');
        $this->command->info('  → /app/{org}/conducteurs');
        $this->command->info('  → /app/{org}/classement-conducteurs');
    }
}
