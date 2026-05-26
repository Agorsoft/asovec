<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-end gap-3">
                <x-filament::button type="submit">
                    Guardar permisos
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
