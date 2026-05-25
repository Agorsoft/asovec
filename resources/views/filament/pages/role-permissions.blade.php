<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-end gap-3">
                {{ ($this->getFormActions()[0])() }}
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
