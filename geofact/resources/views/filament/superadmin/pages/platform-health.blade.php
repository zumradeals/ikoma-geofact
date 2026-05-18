<x-filament-panels::page>

    {{-- Checks système --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        @foreach($checks as $check)
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow p-4 flex items-start gap-3
            border-l-4 {{ $check['ok'] ? 'border-green-400' : 'border-red-500' }}">
            <div class="mt-0.5">
                @if($check['ok'])
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                @else
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                @endif
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $check['label'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $check['detail'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Queue status --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" style="color:#1e3a5f" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 10h16M4 14h8m-8 4h4"/>
                </svg>
                File d'attente (jobs)
            </h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700">
                    <p class="text-3xl font-bold" style="color:#1e3a5f">{{ $pendingJobs }}</p>
                    <p class="text-xs text-gray-500 mt-1">En attente</p>
                </div>
                <div class="text-center p-3 rounded-lg {{ $failedJobs > 0 ? 'bg-red-50 dark:bg-red-900/20' : 'bg-gray-50 dark:bg-gray-700' }}">
                    <p class="text-3xl font-bold {{ $failedJobs > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $failedJobs }}</p>
                    <p class="text-xs text-gray-500 mt-1">Échoués</p>
                </div>
            </div>
        </div>

        {{-- Connecteurs révoqués récemment --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                Connecteurs révoqués (7 derniers jours)
            </h3>
            <div class="text-center p-3">
                <p class="text-4xl font-bold {{ $revokedRecent > 0 ? 'text-orange-500' : 'text-gray-400' }}">
                    {{ $revokedRecent }}
                </p>
                <p class="text-xs text-gray-500 mt-1">révocation(s) récente(s)</p>
            </div>
        </div>
    </div>

    {{-- Connecteurs silencieux --}}
    @if($silentConnectors->isNotEmpty())
    <div class="mt-6 rounded-xl bg-white dark:bg-gray-800 shadow p-5">
        <h3 class="text-sm font-bold text-red-600 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
            Connecteurs silencieux (&gt;24h sans synchronisation)
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500">Provider</th>
                        <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500">Organisation</th>
                        <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500">Dernière sync</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($silentConnectors as $c)
                    <tr class="border-b border-gray-100 dark:border-gray-700/50">
                        <td class="py-2 px-3 font-mono text-xs">{{ $c['provider_id'] }}</td>
                        <td class="py-2 px-3">{{ $c['org'] }}</td>
                        <td class="py-2 px-3 text-red-500">{{ $c['last_sync_at'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="mt-6 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4 text-center">
        <p class="text-sm text-green-700 dark:text-green-400 font-medium">
            ✓ Tous les connecteurs actifs ont synchronisé dans les dernières 24h
        </p>
    </div>
    @endif

</x-filament-panels::page>
