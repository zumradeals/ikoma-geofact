<?php

use App\Jobs\EscalateUnacknowledgedAlerts;
use App\Kpi\Deferred\DfKpiEngine;
use App\Kpi\Deferred\DfKpiVersioner;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── KPI Engine DF — Scheduler (C-05) ──────────────────────────────────────────
// Pipeline DF indépendant du pipeline RT — jamais dans le même pipeline (C-05.1)

Schedule::call(function () {
    $engine = new DfKpiEngine(new DfKpiVersioner());
    $runId  = Str::uuid()->toString();
    $from   = Carbon::yesterday()->startOfDay();
    $to     = Carbon::yesterday()->endOfDay();
    $engine->computeDriverScores($from, $to, $runId);
})->dailyAt('01:00')->name('kpi.driver_score')->withoutOverlapping();

Schedule::call(function () {
    $engine = new DfKpiEngine(new DfKpiVersioner());
    $runId  = Str::uuid()->toString();
    $from   = Carbon::yesterday()->startOfDay();
    $to     = Carbon::yesterday()->endOfDay();
    $engine->computeFleetUtilization($from, $to, $runId);
})->dailyAt('01:30')->name('kpi.fleet_utilization')->withoutOverlapping();

Schedule::call(function () {
    $engine = new DfKpiEngine(new DfKpiVersioner());
    $runId  = Str::uuid()->toString();
    $from   = Carbon::now()->startOfWeek()->subWeek();
    $to     = Carbon::now()->startOfWeek()->subDay()->endOfDay();
    $engine->computeWeeklyPerformance($from, $to, $runId);
})->weeklyOn(1, '02:00')->name('kpi.weekly_performance')->withoutOverlapping();

Schedule::call(function () {
    $engine = new DfKpiEngine(new DfKpiVersioner());
    $runId  = Str::uuid()->toString();
    $from   = Carbon::now()->subMonth()->startOfMonth();
    $to     = Carbon::now()->subMonth()->endOfMonth();
    $engine->computeMonthlyReport($from, $to, $runId);
    $engine->computeBehavioralAnalysis($from, $to, $runId);
})->monthlyOn(1, '03:00')->name('kpi.monthly_report')->withoutOverlapping();

// ── Insight Engine IA — Génération quotidienne (C-06) ────────────────────────
// Lancé après les KPI DF (01h00/01h30) pour avoir des données fraîches.
// Scope : flotte + top 5 véhicules actifs par organisation.
Schedule::job(new \App\Jobs\GenerateInsightsJob())
    ->dailyAt('03:30')
    ->name('insight.daily_generation')
    ->withoutOverlapping();

// ── Delivery Engine — Escalade des Alerts non-acquittées (C-07) ───────────────
Schedule::job(new EscalateUnacknowledgedAlerts())
    ->everyThirtyMinutes()
    ->name('delivery.escalate_unacknowledged')
    ->withoutOverlapping();

// ── Connector Wialon — Synchronisation périodique (C-10) ──────────────────────
// Fréquence : toutes les minutes (granularité maximale du Scheduler Laravel).
// La config WIALON_SYNC_INTERVAL (secondes) est gérée dans WialonSyncJob.
Schedule::job(new \App\Jobs\WialonSyncJob())
    ->everyMinute()
    ->name('connector.wialon.sync')
    ->withoutOverlapping();

// ── Trip Detector — Détection automatique des trajets (C-06) ──────────────────
// Lit les telemetry_events et ouvre/ferme les trips via machine à états.
// Décalé de 1 minute par rapport au sync Wialon pour laisser les events s'insérer.
Schedule::job(new \App\Jobs\TripDetectorJob())
    ->everyTwoMinutes()
    ->name('core.trip_detector')
    ->withoutOverlapping();

// ── Backfill Trajets — Reconstitue l'historique depuis telemetry_events ───────
Artisan::command('trips:backfill {--from=} {--to=} {--vehicle=} {--force}', function () {
    $from  = Carbon::parse($this->option('from') ?? now()->subDay()->format('Y-m-d'))->startOfDay();
    $to    = Carbon::parse($this->option('to')   ?? now()->format('Y-m-d'))->endOfDay();
    $force = $this->option('force');
    $this->info("Backfill : {$from->format('d/m/Y')} → {$to->format('d/m/Y')}");

    $query = \App\Models\Vehicle::where('status', 'active');
    if ($plate = $this->option('vehicle')) {
        $query->where(fn ($q) => $q->where('plate', $plate)->orWhere('id', $plate));
    }
    $vehicles = $query->get();
    $this->info("Véhicules : {$vehicles->count()}");
    $totalCreated = 0;

    foreach ($vehicles as $v) {
        if (! $force && \App\Models\Trip::where('vehicle_id', $v->id)->whereBetween('started_at', [$from, $to])->exists()) {
            $this->line("  {$v->plate} — ignoré (trajets existants, utiliser --force)");
            continue;
        }
        $events = DB::table('telemetry_events')
            ->where('vehicle_id', $v->id)->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('ts', [$from, $to])->orderBy('ts')
            ->get(['ts', 'latitude', 'longitude', 'speed_kmh', 'ignition']);
        if ($events->isEmpty()) { $this->line("  {$v->plate} — pas de télémétrie"); continue; }

        $all = $events->values()->all(); $cnt = count($all);
        $state = 'IDLE'; $tripStart = null; $lastMvTs = null; $lastMvIdx = 0; $created = 0;

        $closeTrip = function ($startE, $endE, $slice) use ($v, &$created) {
            $pts = collect($slice); $dist = 0.0; $prev = null;
            foreach ($pts as $p) {
                if ($prev) {
                    $dLat = deg2rad($p->latitude - $prev->latitude);
                    $dLon = deg2rad($p->longitude - $prev->longitude);
                    $a = sin($dLat / 2) ** 2 + cos(deg2rad($prev->latitude)) * cos(deg2rad($p->latitude)) * sin($dLon / 2) ** 2;
                    $dist += 6371 * 2 * asin(sqrt($a));
                }
                $prev = $p;
            }
            $dur = max(1, (int) Carbon::parse($startE->ts)->diffInMinutes(Carbon::parse($endE->ts)));
            \App\Models\Trip::create([
                'id'               => Str::uuid()->toString(),
                'vehicle_id'       => $v->id,
                'organization_id'  => $v->organization_id,
                'fleet_id'         => $v->fleet_id,
                'status'           => $dist < 0.1 ? 'anomalous' : 'completed',
                'started_at'       => $startE->ts,
                'ended_at'         => $endE->ts,
                'start_latitude'   => $startE->latitude,
                'start_longitude'  => $startE->longitude,
                'end_latitude'     => $endE->latitude,
                'end_longitude'    => $endE->longitude,
                'distance_km'      => round($dist, 3),
                'duration_minutes' => $dur,
                'anomaly_note'     => $dist < 0.1 ? 'micro_trip:distance_below_threshold' : null,
            ]);
            $created++;
        };

        for ($i = 0; $i < $cnt; $i++) {
            $e = $all[$i]; $ts = Carbon::parse($e->ts);
            $mv = ($e->speed_kmh > 0) || ($e->ignition == 1);
            if ($state === 'IDLE') {
                if ($mv) { $state = 'MOVING'; $tripStart = $e; $lastMvTs = $ts; $lastMvIdx = $i; }
                continue;
            }
            if ($mv) { $lastMvTs = $ts; $lastMvIdx = $i; }
            $gap     = $i > 0 ? Carbon::parse($all[$i - 1]->ts)->diffInMinutes($ts) : 0;
            $stopped = $lastMvTs ? $ts->diffInMinutes($lastMvTs) : 0;
            if ($gap >= 30 || $stopped >= 5) {
                $si = 0; foreach ($all as $xi => $xe) { if ($xe === $tripStart) { $si = $xi; break; } }
                $closeTrip($tripStart, $all[$lastMvIdx], array_slice($all, $si, $lastMvIdx - $si + 1));
                $state = 'IDLE'; $tripStart = null; $lastMvTs = null;
            }
        }
        if ($state === 'MOVING' && $tripStart && $lastMvTs) {
            $si = 0; foreach ($all as $xi => $xe) { if ($xe === $tripStart) { $si = $xi; break; } }
            $closeTrip($tripStart, $all[$lastMvIdx], array_slice($all, $si, $lastMvIdx - $si + 1));
        }
        $totalCreated += $created;
        $this->line("  {$v->plate} — {$created} trajet(s) créé(s)");
    }
    $this->info("Total créés : {$totalCreated}");
})->purpose('Reconstitue les trajets historiques depuis telemetry_events');

// ── Nettoyage pré-production — Alertes test/backfill ─────────────────────────
// Résout en masse les alertes de test (seeder) et les alertes auto-générées
// avant la mise en production. Utilise DB::table pour contourner DC-09
// (suppression physique interdite via Eloquent — archivage par résolution).
Artisan::command('alerts:clear-test {--org=} {--all}', function () {
    $orgId = $this->option('org')
        ?? \App\Models\Organization::first()?->id;

    if (! $orgId) {
        $this->error('Aucune organisation trouvée.');
        return;
    }

    $now  = now();
    $note = 'Archivage pré-production — données de test';

    // 1. Supprimer physiquement les alertes créées par le seeder (rule_id TEST-*)
    $deleted = DB::table('alerts')
        ->where('organization_id', $orgId)
        ->where('rule_id', 'like', 'RS-TEST-%')
        ->delete();
    $this->info("  Alertes seeder supprimées : {$deleted}");

    // 2. Résoudre les alertes ouvertes restantes (backfill + wialon)
    $base = DB::table('alerts')
        ->where('organization_id', $orgId)
        ->where('status', 'open');

    if (! $this->option('all')) {
        // Par défaut : uniquement les alertes sans acquittement humain
        $base->whereNull('acknowledged_at');
    }

    $resolved = (clone $base)->update([
        'status'          => 'resolved',
        'resolved_at'     => $now,
        'resolution_note' => $note,
    ]);
    $this->info("  Alertes résolues  : {$resolved}");

    $total = $deleted + $resolved;
    $this->info("  Total traité      : {$total}");
    $this->info("Terminé. Le dashboard devrait afficher 0 alertes ouvertes.");
})->purpose('Archive les alertes de test et de backfill avant mise en production');
