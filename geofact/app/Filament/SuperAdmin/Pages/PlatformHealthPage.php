<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\Connector;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class PlatformHealthPage extends Page
{
    protected static ?string $title = 'Santé plateforme';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.superadmin.pages.platform-health';

    public static function getNavigationIcon(): string { return 'heroicon-o-heart'; }
    public static function getNavigationGroup(): ?string { return 'Système'; }

    public function getViewData(): array
    {
        $now = now();

        // Jobs en attente dans la queue database
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs  = DB::table('failed_jobs')->count();

        // Connecteurs silencieux : last_sync_at > 24h ou null
        $silentConnectors = Connector::where('status', 'active')
            ->where(fn ($q) => $q
                ->whereNull('last_sync_at')
                ->orWhere('last_sync_at', '<', $now->copy()->subHours(24))
            )
            ->with('organization')
            ->get()
            ->map(fn ($c) => [
                'id'           => $c->id,
                'provider_id'  => $c->provider_id,
                'org'          => $c->organization?->name ?? '—',
                'last_sync_at' => $c->last_sync_at?->diffForHumans() ?? 'Jamais',
            ]);

        // Connecteurs révoqués récemment (7 jours)
        $revokedRecent = Connector::where('status', 'revoked')
            ->where('updated_at', '>=', $now->copy()->subDays(7))
            ->count();

        // Checks système
        $checks = [
            [
                'label'  => 'Connexion base de données',
                'ok'     => $this->checkDb(),
                'detail' => DB::connection()->getDriverName(),
            ],
            [
                'label'  => 'Queue jobs en attente',
                'ok'     => $pendingJobs < 100,
                'detail' => $pendingJobs . ' job(s)',
            ],
            [
                'label'  => 'Jobs échoués',
                'ok'     => $failedJobs === 0,
                'detail' => $failedJobs . ' job(s) échoué(s)',
            ],
            [
                'label'  => 'Connecteurs silencieux (>24h)',
                'ok'     => $silentConnectors->isEmpty(),
                'detail' => $silentConnectors->count() . ' connecteur(s)',
            ],
        ];

        return [
            'checks'           => $checks,
            'pendingJobs'      => $pendingJobs,
            'failedJobs'       => $failedJobs,
            'silentConnectors' => $silentConnectors,
            'revokedRecent'    => $revokedRecent,
        ];
    }

    private function checkDb(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
