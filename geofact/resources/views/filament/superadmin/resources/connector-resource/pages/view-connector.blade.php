<x-filament-panels::page>

@if($revealedToken)
<div class="mb-6 rounded-xl border border-yellow-400 bg-yellow-50 dark:bg-yellow-900/20 p-5">
    <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-300 mb-2">
        ⚠️ Token connecteur — copiez-le maintenant, il ne sera plus affiché.
    </p>
    <div class="flex items-center gap-3">
        <code class="flex-1 block rounded-lg bg-white dark:bg-gray-900 border border-yellow-300 dark:border-yellow-700 px-4 py-2 text-sm font-mono text-gray-800 dark:text-gray-100 break-all select-all">
            {{ $revealedToken }}
        </code>
        <button type="button"
                onclick="navigator.clipboard.writeText('{{ $revealedToken }}').then(() => this.textContent = '✓ Copié').catch(() => {})"
                class="shrink-0 px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-semibold rounded-lg transition-colors">
            Copier
        </button>
    </div>
    <p class="text-xs text-yellow-700 dark:text-yellow-400 mt-2">
        Ce token est à configurer dans l'en-tête <code class="font-mono">X-Connector-Token</code> des appels webhook ou dans votre fichier <code class="font-mono">.env</code> du boîtier GPS.
    </p>
</div>
@endif

{{ $this->infolist }}

<x-filament-actions::modals />

</x-filament-panels::page>
