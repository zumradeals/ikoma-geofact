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

// ── Delivery Engine — Escalade des Alerts non-acquittées (C-07) ───────────────
Schedule::job(new EscalateUnacknowledgedAlerts())
    ->everyThirtyMinutes()
    ->name('delivery.escalate_unacknowledged')
    ->withoutOverlapping();
