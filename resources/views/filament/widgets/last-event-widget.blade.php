<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Último evento disponible
        </x-slot>

        @php $data = $this->getViewData(); $event = $data['event']; @endphp

        @if ($event)
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <a
                        href="{{ $data['eventUrl'] }}"
                        class="text-base font-semibold text-primary-600 dark:text-primary-400 hover:underline truncate block"
                    >
                        {{ $event->name }}
                    </a>

                    @if ($event->start_date)
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $event->start_date->translatedFormat('d \d\e F \d\e Y') }}
                            @if ($event->end_date && !$event->start_date->isSameDay($event->end_date))
                                — {{ $event->end_date->translatedFormat('d \d\e F \d\e Y') }}
                            @endif
                        </p>
                    @endif

                    @if ($event->registration_deadline)
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                            Plazo de inscripción: {{ $event->registration_deadline->translatedFormat('d/m/Y') }}
                        </p>
                    @endif
                </div>

                <a
                    href="{{ $data['eventUrl'] }}"
                    class="shrink-0 inline-flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 hover:underline"
                >
                    Ver evento
                    <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5" />
                </a>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-success-50 dark:bg-success-950 border border-success-200 dark:border-success-800 p-3 text-center">
                    <p class="text-2xl font-bold text-success-700 dark:text-success-300">
                        {{ $data['confirmadas'] }}
                    </p>
                    <p class="mt-0.5 text-xs font-medium text-success-600 dark:text-success-400 uppercase tracking-wide">
                        Confirmadas
                    </p>
                </div>

                <div class="rounded-xl bg-warning-50 dark:bg-warning-950 border border-warning-200 dark:border-warning-800 p-3 text-center">
                    <p class="text-2xl font-bold text-warning-700 dark:text-warning-300">
                        {{ $data['pendientes'] }}
                    </p>
                    <p class="mt-0.5 text-xs font-medium text-warning-600 dark:text-warning-400 uppercase tracking-wide">
                        Pendientes
                    </p>
                </div>
            </div>
        @else
            <div class="py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                No hay eventos abiertos en este momento.
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
