{{-- Bloques de tarjetas encima del listado de pedidos (contraíbles; recuerdan su estado) --}}
<div class="space-y-4 p-4 sm:p-6">
    <x-filament::section
        collapsible
        persist-collapsed
        id="packages-summary-month"
        icon="heroicon-o-calendar-days"
        compact
    >
        <x-slot name="heading">Este mes</x-slot>

        @include('filament.packages.partials.cards', ['cards' => $cards])
    </x-filament::section>

    @if (! empty($filtered))
        <x-filament::section
            collapsible
            persist-collapsed
            id="packages-summary-filtered"
            icon="heroicon-o-funnel"
            compact
        >
            <x-slot name="heading">Resumen según filtros</x-slot>

            {{-- Leyenda de filtros aplicados --}}
            <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                @forelse ($applied as $item)
                    <x-filament::badge color="primary" icon="heroicon-m-funnel">
                        {{ $item['label'] }}: <strong>{{ $item['value'] }}</strong>
                    </x-filament::badge>
                @empty
                    <span class="text-gray-500 dark:text-gray-400">
                        Sin filtros: se muestran todos los pedidos que puedes ver.
                    </span>
                @endforelse

                <span class="text-xs text-gray-400 dark:text-gray-500">
                    · No tiene en cuenta la pestaña seleccionada.
                </span>
            </div>

            @include('filament.packages.partials.cards', ['cards' => $filtered])
        </x-filament::section>
    @endif
</div>
