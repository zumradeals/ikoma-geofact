<x-filament-panels::page>

    <div class="max-w-xl">
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow p-6">
            <h2 class="text-base font-semibold text-gray-700 dark:text-gray-300 mb-5">Informations organisation</h2>

            <form wire:submit="save">
                {{ $this->form }}

                <div class="mt-6 flex justify-end">
                    <button type="submit"
                        class="px-5 py-2 rounded-lg text-sm font-semibold text-white"
                        style="background:#1e3a5f">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

</x-filament-panels::page>
