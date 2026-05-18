<x-filament-panels::page>

    <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        Période : <strong>{{ $period }}</strong> — basé sur les KPIs différés (DF)
    </div>

    @if($scoreboard->isEmpty())
    <div class="rounded-xl bg-white dark:bg-gray-800 shadow p-8 text-center">
        <p class="text-gray-500">Aucun score calculé pour la période. Relancez le scheduler KPI DF.</p>
    </div>
    @else
    <div class="rounded-xl bg-white dark:bg-gray-800 shadow overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700" style="background:#1e3a5f">
                    <th class="text-left py-3 px-4 text-xs font-semibold text-white uppercase">#</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-white uppercase">Conducteur</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-white uppercase">Permis</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-white uppercase">Période</th>
                    <th class="text-right py-3 px-4 text-xs font-semibold text-white uppercase">Score /100</th>
                    <th class="text-center py-3 px-4 text-xs font-semibold text-white uppercase">Jauge</th>
                </tr>
            </thead>
            <tbody>
                @foreach($scoreboard as $row)
                @php
                    $score = $row['score'];
                    $color = $score >= 80 ? '#16a34a' : ($score >= 60 ? '#f59e0b' : '#dc2626');
                    $medal = match($row['rank']) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $row['rank'] };
                @endphp
                <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <td class="py-3 px-4 text-center text-lg">{{ $medal }}</td>
                    <td class="py-3 px-4 font-semibold text-gray-800 dark:text-gray-200">{{ $row['name'] }}</td>
                    <td class="py-3 px-4 text-sm text-gray-500 font-mono">{{ $row['license'] }}</td>
                    <td class="py-3 px-4 text-xs text-gray-500">{{ $row['period'] }}</td>
                    <td class="py-3 px-4 text-right font-bold text-xl" style="color:{{ $color }}">
                        {{ number_format($score, 1) }}
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all"
                                     style="width:{{ max(0, min(100, $score)) }}%; background:{{ $color }}"></div>
                            </div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</x-filament-panels::page>
