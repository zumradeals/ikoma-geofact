<x-filament-panels::page>

@if($wialonError)
<div class="mb-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4">
    <p class="text-sm text-red-700 dark:text-red-300">
        <strong>Erreur Wialon :</strong> {{ $wialonError }}
    </p>
</div>
@else
<div class="mb-4 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="text-sm text-green-700 dark:text-green-300 font-semibold">Wialon connecte - {{ count($units) }} unite(s) disponible(s)</p>
        @if($lastSyncAt)
            <p class="text-xs text-green-600 dark:text-green-400 mt-0.5">
                Dernier sync cron : {{ \Carbon\Carbon::parse($lastSyncAt)->diffForHumans() }}
                @if(\Carbon\Carbon::parse($lastSyncAt)->diffInMinutes() > 5)
                    <span class="ml-1 px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300 text-xs font-semibold">Cron arrete</span>
                @endif
            </p>
        @else
            <p class="text-xs text-yellow-600 dark:text-yellow-400 mt-0.5">Aucun sync automatique detecte - le cron cPanel n'est peut-etre pas configure.</p>
        @endif
    </div>
    <button type="button"
            wire:click="triggerSync"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-orange-300 text-white text-sm font-semibold rounded-xl shadow transition-colors">
        <span wire:loading.remove wire:target="triggerSync">Sync maintenant</span>
        <span wire:loading wire:target="triggerSync">Sync en cours...</span>
    </button>
</div>
@endif

@if(!empty($units))
<div class="rounded-xl bg-white dark:bg-gray-800 shadow mb-6">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">Unites Wialon</h2>
        <p class="text-sm text-gray-500 mt-1">
            La position officielle vient de la base IKOMA. Wialon live est affiche seulement comme diagnostic fournisseur.
        </p>
    </div>
    <div class="divide-y divide-gray-100 dark:divide-gray-700">
        @foreach($units as $unit)
        @php
            $existing = $mappings->get($unit['id']);
            $isActive = $existing?->status === 'active';
            $official = $unit['official_position'] ?? null;
            $gap = $unit['sync_gap_seconds'] ?? null;
        @endphp
        <div class="px-6 py-4 flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-800 dark:text-gray-200 truncate">{{ $unit['name'] }}</p>
                <p class="text-xs text-gray-500 font-mono">ID Wialon : {{ $unit['id'] }}</p>

                @if($official)
                <p class="text-xs text-green-700 dark:text-green-300 mt-0.5 font-semibold">
                    Position officielle IKOMA : {{ $official['lat'] ?? '-' }}, {{ $official['lon'] ?? '-' }}
                    @if(!empty($official['speed'])) - {{ $official['speed'] }} km/h @endif
                    @if(!empty($official['ts'])) - {{ \Carbon\Carbon::createFromTimestamp($official['ts'])->diffForHumans() }} @endif
                    @if(!empty($official['freshness'])) - {{ $official['freshness'] }} @endif
                </p>
                @else
                <p class="text-xs text-red-600 dark:text-red-400 mt-0.5 font-semibold">
                    Position officielle IKOMA : non disponible
                </p>
                @endif

                @if(!empty($unit['last_pos']))
                <p class="text-xs text-gray-500 mt-0.5">
                    Diagnostic Wialon live : {{ $unit['last_pos']['lat'] ?? '-' }}, {{ $unit['last_pos']['lon'] ?? '-' }}
                    @if(!empty($unit['last_pos']['speed'])) - {{ $unit['last_pos']['speed'] }} km/h @endif
                    @if(!empty($unit['last_pos']['ts'])) - {{ \Carbon\Carbon::createFromTimestamp($unit['last_pos']['ts'])->diffForHumans() }} @endif
                    @if($gap !== null && $gap > 300)
                        <span class="ml-1 px-1.5 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 text-xs font-semibold">
                            IKOMA en retard de {{ round($gap / 60) }} min
                        </span>
                    @endif
                </p>
                @endif
            </div>

            <div class="text-sm text-gray-500 min-w-[160px]">
                @if($existing?->vehicle)
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-xs font-medium">
                        {{ $existing->vehicle->name }}
                    </span>
                @else
                    <span class="text-gray-400 italic text-xs">Non mappe</span>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if(!$existing)
                <div class="flex flex-col gap-2">
                    <button type="button"
                            wire:click="autoMapUnit({{ $unit['id'] }}, '{{ addslashes($unit['name']) }}')"
                            wire:confirm="Creer automatiquement ce vehicule et le mapper ?"
                            class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                        Auto-creer et mapper
                    </button>
                    <div class="flex items-center gap-2">
                        <select wire:model="selectedVehicles.{{ $unit['id'] }}"
                                class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                            <option value="">Vehicule existant</option>
                            @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->name }} {{ $vehicle->plate ? '('.$vehicle->plate.')' : '' }}</option>
                            @endforeach
                        </select>
                        <button type="button"
                                wire:click="mapUnit({{ $unit['id'] }}, '{{ addslashes($unit['name']) }}')"
                                class="px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                            Mapper
                        </button>
                    </div>
                </div>
                @else
                <button wire:click="toggleMapping('{{ $existing->id }}')"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors
                               {{ $isActive ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 hover:bg-green-200' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 hover:bg-gray-200' }}">
                    {{ $isActive ? 'Actif' : 'Inactif' }}
                </button>
                <button wire:click="removeMapping('{{ $existing->id }}')"
                        wire:confirm="Supprimer ce mapping ?"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg bg-red-50 dark:bg-red-900/20 text-red-600 hover:bg-red-100 transition-colors">
                    Retirer
                </button>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

@if($mappings->isNotEmpty())
<div class="rounded-xl bg-white dark:bg-gray-800 shadow">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">Synchronisations actives</h2>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <th class="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Unite Wialon</th>
                <th class="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Vehicule IKOMA</th>
                <th class="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Dernier message</th>
                <th class="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Statut</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach($mappings as $mapping)
            @php
                $lastTs = $mapping->last_message_ts
                    ? \Carbon\Carbon::createFromTimestamp($mapping->last_message_ts)
                    : null;
                $isStale = $lastTs && $lastTs->diffInHours() > 1;
            @endphp
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                <td class="py-3 px-4 font-medium text-gray-800 dark:text-gray-200">{{ $mapping->wialon_unit_name }}</td>
                <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ $mapping->vehicle?->name ?? '-' }}</td>
                <td class="py-3 px-4 text-gray-500">
                    @if($lastTs)
                        <span class="{{ $isStale ? 'text-yellow-600 dark:text-yellow-400' : 'text-green-600 dark:text-green-400' }}">
                            {{ $lastTs->diffForHumans() }}
                        </span>
                        <span class="text-xs text-gray-400 block">{{ $lastTs->format('d/m H:i') }}</span>
                    @else
                        <span class="text-gray-400 italic">Jamais synchronise</span>
                    @endif
                </td>
                <td class="py-3 px-4">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold
                        {{ $mapping->status === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                        {{ $mapping->status === 'active' ? 'Actif' : 'Inactif' }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@if(empty($units) && $mappings->isEmpty() && !$wialonError)
<div class="text-center py-12 text-gray-500">
    <p class="text-base">Aucune unite Wialon disponible.</p>
    <p class="text-sm mt-1">Verifiez que votre token Wialon est configure et que des unites existent dans votre compte.</p>
</div>
@endif

</x-filament-panels::page>
