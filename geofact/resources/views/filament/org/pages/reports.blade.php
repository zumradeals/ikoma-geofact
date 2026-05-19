<x-filament-panels::page>
    <div wire:poll.5000ms="$refresh" style="display:none"></div>

    @if($reports->isEmpty())
    <div class="rounded-xl bg-white dark:bg-gray-800 shadow p-12 text-center">
        <svg class="mx-auto h-10 w-10 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Aucun rapport généré</p>
        <p class="text-xs text-gray-400 mt-1">Cliquez sur "Générer rapport flotte" pour créer votre premier rapport.</p>
    </div>
    @else
    <div class="rounded-xl bg-white dark:bg-gray-800 shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-gray-700">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Rapport</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Période</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Statut</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Résumé IA</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                @foreach($reports as $report)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900 dark:text-white text-sm">{{ $report->title }}</div>
                        <div class="text-xs text-gray-400 mt-0.5">{{ $report->created_at->diffForHumans() }}</div>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                        {{ $report->period_from->format('d/m/Y') }} → {{ $report->period_to->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3">
                        @if($report->status === 'ready')
                            <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500 inline-block"></span> Prêt
                            </span>
                        @elseif(in_array($report->status, ['generating', 'pending']))
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                <span class="animate-spin h-3 w-3 border border-blue-500 border-t-transparent rounded-full inline-block"></span> En cours…
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700"
                                  title="{{ $report->error_message }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-red-500 inline-block"></span> Erreur
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500 max-w-xs">
                        @if($report->ai_summary)
                            <span title="{{ $report->ai_summary }}">{{ Str::limit($report->ai_summary, 90) }}</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($report->status === 'ready')
                            <button wire:click="download('{{ $report->id }}')"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#1e3a5f] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#162d4a] transition-colors">
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                PDF
                            </button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</x-filament-panels::page>
