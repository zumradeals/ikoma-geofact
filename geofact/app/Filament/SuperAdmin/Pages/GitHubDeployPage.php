<?php

namespace App\Filament\SuperAdmin\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class GitHubDeployPage extends Page
{
    protected static ?string $title        = 'Déploiement GitHub';
    protected static ?int    $navigationSort = 99;
    protected string         $view         = 'filament.superadmin.pages.github-deploy';

    public static function getNavigationIcon(): string  { return 'heroicon-o-arrow-down-tray'; }
    public static function getNavigationGroup(): ?string { return 'Système'; }

    // ── État Livewire ─────────────────────────────────────────────────────────
    public string $githubToken    = '';
    public string $currentSha     = '';
    public string $lastSha        = '';
    public array  $pendingFiles   = [];
    public array  $deployLog      = [];
    public bool   $isDeploying    = false;
    public string $statusMessage  = '';

    private const CONFIG_FILE  = 'deploy-config.json';
    private const REPO         = 'zumradeals/ikoma-geofact';
    private const BRANCH       = 'master';
    private const SUBFOLDER    = 'geofact/';

    // ── Initialisation ────────────────────────────────────────────────────────
    public function mount(): void
    {
        $cfg = $this->loadConfig();
        $this->githubToken = $cfg['github_token'] ?? '';
        $this->lastSha     = $cfg['last_deployed_sha'] ?? '';

        if ($this->githubToken) {
            $this->refresh();
        }
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function saveToken(): void
    {
        $cfg = $this->loadConfig();
        $cfg['github_token'] = trim($this->githubToken);
        $this->saveConfig($cfg);

        Notification::make()->title('Token enregistré.')->success()->send();
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->pendingFiles  = [];
        $this->statusMessage = '';

        if (empty($this->githubToken)) {
            $this->statusMessage = 'Renseignez votre token GitHub.';
            return;
        }

        // Commit HEAD sur master
        $res = Http::withToken($this->githubToken)
            ->timeout(10)
            ->get("https://api.github.com/repos/" . self::REPO . "/commits/" . self::BRANCH);

        if (! $res->successful()) {
            $this->statusMessage = 'Erreur GitHub API : ' . $res->status() . ' — vérifiez le token.';
            return;
        }

        $this->currentSha = $res->json('sha') ?? '';

        if (empty($this->currentSha)) {
            $this->statusMessage = 'Impossible de récupérer le commit HEAD.';
            return;
        }

        if (empty($this->lastSha)) {
            $this->statusMessage = 'Premier déploiement — tous les fichiers seront copiés.';
            $this->pendingFiles  = ['(premier déploiement — cliquez Déployer)'];
            return;
        }

        if ($this->lastSha === $this->currentSha) {
            $this->statusMessage = 'Serveur à jour ✓';
            return;
        }

        // Fichiers modifiés entre lastSha et currentSha
        $cmp = Http::withToken($this->githubToken)
            ->timeout(15)
            ->get("https://api.github.com/repos/" . self::REPO . "/compare/{$this->lastSha}...{$this->currentSha}");

        if (! $cmp->successful()) {
            $this->statusMessage = 'Impossible de comparer les commits.';
            return;
        }

        $this->pendingFiles = collect($cmp->json('files') ?? [])
            ->filter(fn ($f) => str_starts_with($f['filename'] ?? '', self::SUBFOLDER))
            ->filter(fn ($f) => ($f['status'] ?? '') !== 'removed')
            ->pluck('filename')
            ->values()
            ->toArray();

        $count = count($this->pendingFiles);
        $this->statusMessage = $count > 0
            ? "{$count} fichier(s) à déployer."
            : 'Serveur à jour ✓';
    }

    public function deploy(): void
    {
        if (empty($this->githubToken) || empty($this->currentSha)) {
            Notification::make()->title('Token ou SHA manquant.')->danger()->send();
            return;
        }

        $this->isDeploying = true;
        $this->deployLog   = [];

        $cfg     = $this->loadConfig();
        $lastSha = $cfg['last_deployed_sha'] ?? null;

        // Si premier déploiement ou liste vide, on recharge les fichiers
        if (empty($lastSha)) {
            $this->deployLog[] = '⚡ Premier déploiement — récupération de la liste complète...';
            $tree = Http::withToken($this->githubToken)
                ->timeout(20)
                ->get("https://api.github.com/repos/" . self::REPO . "/git/trees/{$this->currentSha}?recursive=1");

            $files = collect($tree->json('tree') ?? [])
                ->filter(fn ($f) => ($f['type'] ?? '') === 'blob')
                ->filter(fn ($f) => str_starts_with($f['path'] ?? '', self::SUBFOLDER))
                ->pluck('path')
                ->toArray();
        } else {
            $files = $this->pendingFiles;
            // Retire les entrées purement indicatives
            $files = array_filter($files, fn ($f) => ! str_starts_with($f, '('));
        }

        $ok   = 0;
        $fail = 0;

        foreach ($files as $repoPath) {
            $rawUrl   = "https://raw.githubusercontent.com/" . self::REPO . "/{$this->currentSha}/{$repoPath}";
            $localRel = substr($repoPath, strlen(self::SUBFOLDER));
            $localAbs = base_path($localRel);

            $dir = dirname($localAbs);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $response = Http::withToken($this->githubToken)->timeout(15)->get($rawUrl);

            if ($response->successful()) {
                file_put_contents($localAbs, $response->body());
                $this->deployLog[] = "✓ {$localRel}";
                $ok++;
            } else {
                $this->deployLog[] = "✗ FAIL {$localRel} (HTTP {$response->status()})";
                $fail++;
            }
        }

        // Vide le cache Laravel
        try {
            Artisan::call('optimize:clear');
            $this->deployLog[] = '✓ Cache vidé (optimize:clear)';
        } catch (\Throwable $e) {
            $this->deployLog[] = '✗ optimize:clear échoué : ' . $e->getMessage();
        }

        // Enregistre le SHA déployé
        $cfg['last_deployed_sha'] = $this->currentSha;
        $cfg['last_deployed_at']  = now()->toDateTimeString();
        $this->saveConfig($cfg);
        $this->lastSha = $this->currentSha;

        $this->isDeploying    = false;
        $this->pendingFiles   = [];
        $this->statusMessage  = "Déploiement terminé — {$ok} fichier(s) ✓" . ($fail ? ", {$fail} échec(s)" : '');

        Notification::make()
            ->title("Déployé : {$ok} fichier(s)" . ($fail ? " ({$fail} échecs)" : ''))
            ->color($fail ? 'warning' : 'success')
            ->send();
    }

    // ── Config persistence ────────────────────────────────────────────────────

    private function loadConfig(): array
    {
        $path = storage_path('app/' . self::CONFIG_FILE);
        if (! file_exists($path)) return [];
        return json_decode(file_get_contents($path), true) ?? [];
    }

    private function saveConfig(array $cfg): void
    {
        $path = storage_path('app/' . self::CONFIG_FILE);
        file_put_contents($path, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
