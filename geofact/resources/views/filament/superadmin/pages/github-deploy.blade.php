<x-filament-panels::page>

{{-- Token GitHub --}}
<div class="rounded-xl bg-white dark:bg-gray-800 shadow mb-6 p-6">
    <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200 mb-4">Token GitHub</h2>
    <div class="flex gap-3 items-end">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Personal Access Token
                <span class="text-xs text-gray-400 font-normal ml-1">(scope : contents:read)</span>
            </label>
            <input type="password"
                   wire:model="githubToken"
                   placeholder="ghp_xxxxxxxxxxxxxxxxxxxx"
                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 focus:border-orange-400 font-mono" />
        </div>
        <button wire:click="saveToken"
                class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-lg transition-colors whitespace-nowrap">
            Enregistrer
        </button>
    </div>
    <p class="text-xs text-gray-400 mt-2">
        Générez un token sur <strong>github.com → Settings → Developer settings → Personal access tokens → Fine-grained tokens</strong>.
        Permission requise : <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">Contents → Read-only</code> sur le dépôt <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">ikoma-geofact</code>.
    </p>
</div>

{{-- Statut --}}
@if($githubToken)
<div class="rounded-xl bg-white dark:bg-gray-800 shadow mb-6">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">État du déploiement</h2>
            @if($statusMessage)
            <p class="text-sm mt-1 {{ str_contains($statusMessage, '✓') ? 'text-green-600 dark:text-green-400' : (str_contains($statusMessage, 'Erreur') || str_contains($statusMessage, 'FAIL') ? 'text-red-500' : 'text-orange-500') }}">
                {{ $statusMessage }}
            </p>
            @endif
        </div>
        <button wire:click="refresh"
                class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <svg wire:loading.class="animate-spin" wire:target="refresh" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Actualiser
        </button>
    </div>

    <div class="px-6 py-4 grid grid-cols-2 gap-4 text-sm">
        <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Serveur (déployé)</p>
            <code class="text-gray-800 dark:text-gray-200 font-mono text-xs">
                {{ $lastSha ? substr($lastSha, 0, 10) : '—' }}
            </code>
        </div>
        <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">GitHub master (HEAD)</p>
            <code class="{{ $currentSha && $currentSha !== $lastSha ? 'text-orange-500 font-bold' : 'text-gray-800 dark:text-gray-200' }} font-mono text-xs">
                {{ $currentSha ? substr($currentSha, 0, 10) : '—' }}
            </code>
        </div>
    </div>

    {{-- Fichiers en attente --}}
    @if(!empty($pendingFiles) && !str_starts_with(($pendingFiles[0] ?? ''), '('))
    <div class="px-6 pb-4">
        <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Fichiers à déployer</p>
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 max-h-48 overflow-y-auto">
            @foreach($pendingFiles as $file)
            <p class="text-xs font-mono text-gray-600 dark:text-gray-400 py-0.5">{{ $file }}</p>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Bouton déployer --}}
    @if(!empty($pendingFiles) || (!$lastSha && $currentSha))
    <div class="px-6 pb-5">
        <button wire:click="deploy"
                wire:loading.attr="disabled"
                wire:target="deploy"
                class="w-full py-3 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
            <span wire:loading.remove wire:target="deploy">⬇ Déployer maintenant</span>
            <span wire:loading wire:target="deploy" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                Déploiement en cours…
            </span>
        </button>
    </div>
    @elseif($currentSha && $currentSha === $lastSha)
    <div class="px-6 pb-5">
        <div class="w-full py-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 font-semibold rounded-lg text-center text-sm">
            ✓ Serveur à jour — aucun déploiement nécessaire
        </div>
    </div>
    @endif
</div>
@endif

{{-- Log de déploiement --}}
@if(!empty($deployLog))
<div class="rounded-xl bg-gray-900 shadow p-5">
    <p class="text-xs text-gray-400 uppercase tracking-wide mb-3 font-semibold">Journal de déploiement</p>
    <div class="space-y-0.5 max-h-80 overflow-y-auto font-mono text-xs">
        @foreach($deployLog as $line)
        <p class="{{ str_starts_with($line, '✓') ? 'text-green-400' : (str_starts_with($line, '✗') ? 'text-red-400' : 'text-gray-300') }}">
            {{ $line }}
        </p>
        @endforeach
    </div>
</div>
@endif

</x-filament-panels::page>
