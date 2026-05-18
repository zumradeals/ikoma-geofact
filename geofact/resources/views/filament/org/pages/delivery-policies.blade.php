<x-filament-panels::page>

    <div class="mb-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-4">
        <p class="text-sm text-blue-700 dark:text-blue-300">
            <strong>Note :</strong> Les politiques système (SP) sont non désactivables — elles s'appliquent toujours
            avant ces politiques client (SP-01 : CRITICAL → Email + WhatsApp obligatoire).
        </p>
    </div>

    {{ $this->table }}

</x-filament-panels::page>
