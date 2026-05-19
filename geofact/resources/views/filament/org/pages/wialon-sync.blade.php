<x-filament-panels::page>

{{-- Bannière erreur token --}}
@if($wialonError)
<div class="mb-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4">
    <p class="text-sm text-red-700 dark:text-red-300">
        <strong>Erreur Wialon :</strong> {{ $wialonError }}
    </p>
</div>
@else
<div class="mb-4 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4">
    <p class="text-sm text-green-700 dark:text-green-300">
        <strong>Wialon connecté</strong> — {{ count($units) }} unité(s) disponible(s). Synchronisation automatique toutes les minutes.
    </p>
</div>
@endif

{{-- Unités Wialon disponibles --}}
@if(!empty($units))
<div class="rounded-xl bg-white dark:bg-gray-800 shadow mb-6">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">Unités Wialon</h2>
        <p class="text-sm text-gray-500 mt-1">Mappez chaque unité GPS Wialon vers un véhicule IKOMA.</p>
    </div>
    <div class="divide-y divide-gray-100 dark:divide-gray-700">
        @foreach($units as $unit)
        @php
            $existing = $mappings->get($unit['id']);
            $isActive = $existing?->status === 'active';
        @endphp
        <div class="px-6 py-4 flex flex-wrap items-center gap-4">
            {{-- Info unité --}}
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-800 dark:text-gray-200 truncate">{{ $unit['name'] }}</p>
                <p class="text-xs text-gray-500 font-mono">ID Wialon : {{ $unit['id'] }}</p>
                @if(!empty($unit['last_pos']))
                <p class="text-xs text-gray-500 mt-0.5">
                    Dernière position : {{ $unit['last_pos']['lat'] ?? '—' }}, {{ $unit['last_pos']['lon'] ?? '—' }}
                    @if(!empty($unit['last_pos']['speed'])) · {{ $unit['last_pos']['speed'] }} km/h @endif
                    @if(!empty($unit['last_pos']['ts'])) · {{ \Carbon\Carbon::createFromTimestamp($unit['last_pos']['ts'])->diffForHumans() }} @endif
                </p>
                @endif
            </div>

            {{-- Véhicule mappé --}}
            <div class="text-sm text-gray-500 min-w-[160px]">
                @if($existing?->vehicle)
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-xs font-medium">
                        {{ $existing->vehicle->name }}
                    </span>
                @else
                    <span class="text-gray-400 italic text-xs">Non mappé</span>
                @endif
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-2">
                @if(!$existing)
                {{-- Formulaire de mapping --}}
                <div class="flex flex-col gap-2">
                    {{-- Auto-créer et mapper en 1 clic --}}
                    <button type="button"
                            wire:click="autoMapUnit({{ $unit['id'] }}, '{{ addslashes($unit['name']) }}')"
                            wire:confirm="Créer automatiquement un véhicule depuis « {{ $unit['name'] }} » et le mapper ?"
                            class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                        ⚡ Auto-créer et mapper
                    </button>
                    {{-- Ou choisir un véhicule existant --}}
                    <div class="flex items-center gap-2">
                        <select wire:model="selectedVehicles.{{ $unit['id'] }}"
                                class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                            <option value="">— Véhicule existant —</option>
                            @foreach($vehicles as $v)
                            <option value="{{ $v->id }}">{{ $v->name }} {{ $v->plate ? '('.$v->plate.')' : '' }}</option>
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
                {{-- Toggle + Supprimer --}}
                <button wire:click="toggleMapping('{{ $existing->id }}')"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors
                               {{ $isActive ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 hover:bg-green-200' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 hover:bg-gray-200' }}">
                    {{ $isActive ? '✓ Actif' : '⏸ Inactif' }}
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

{{-- Mappings actifs --}}
@if($mappings->isNotEmpty())
<div class="rounded-xl bg-white dark:bg-gray-800 shadow">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">Synchronisations actives</h2>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <th class="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Unité Wialon</th>
                <th class="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Véhicule IKOMA</th>
                <th class="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Dernier message</th>
                <th class="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Statut</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach($mappings as $mapping)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                <td class="py-3 px-4 font-medium text-gray-800 dark:text-gray-200">{{ $mapping->wialon_unit_name }}</td>
                <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ $mapping->vehicle?->name ?? '—' }}</td>
                <td class="py-3 px-4 text-gray-500">
                    {{ $mapping->last_message_ts ? \Carbon\Carbon::createFromTimestamp($mapping->last_message_ts)->diffForHumans() : 'Jamais' }}
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

{{-- État vide --}}
@if(empty($units) && $mappings->isEmpty() && !$wialonError)
<div class="text-center py-12 text-gray-500">
    <p class="text-base">Aucune unité Wialon disponible.</p>
    <p class="text-sm mt-1">Vérifiez que votre token Wialon est configuré et que des unités existent dans votre compte.</p>
</div>
@endif

</x-filament-panels::page>
