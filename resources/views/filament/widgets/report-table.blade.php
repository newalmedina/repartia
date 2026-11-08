<x-filament-widgets::widget>
    <x-filament::section collapsible persist-collapsed :id="'report-' . $report">
        <x-slot name="heading">{{ $title }}</x-slot>
        <x-slot name="description">{{ $period }} · {{ count($rows) }} filas</x-slot>

        @if (empty($rows))
            <p class="text-sm text-gray-500 dark:text-gray-400">No hay datos en el periodo con estos filtros.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            @foreach ($headings as $heading)
                                <th class="whitespace-nowrap px-3 py-2 font-semibold text-gray-950 dark:text-white">{{ $heading }}</th>
                            @endforeach
                            @if ($links)
                                <th class="whitespace-nowrap px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $i => $row)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                                @foreach ($row as $cell)
                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $cell }}</td>
                                @endforeach

                                {{-- Ver / editar según los permisos del usuario --}}
                                @if ($links)
                                    <td class="whitespace-nowrap px-3 py-2">
                                        <div class="flex items-center justify-end gap-1">
                                            @if ($links[$i]['view'] ?? null)
                                                <x-filament::icon-button
                                                    tag="a"
                                                    :href="$links[$i]['view']"
                                                    icon="heroicon-o-eye"
                                                    color="gray"
                                                    label="Ver"
                                                    tooltip="Ver"
                                                />
                                            @endif
                                            @if ($links[$i]['edit'] ?? null)
                                                <x-filament::icon-button
                                                    tag="a"
                                                    :href="$links[$i]['edit']"
                                                    icon="heroicon-o-pencil-square"
                                                    color="primary"
                                                    label="Editar"
                                                    tooltip="Editar"
                                                />
                                            @endif
                                            @if ($links[$i]['pdf'] ?? null)
                                                <x-filament::icon-button
                                                    tag="a"
                                                    :href="$links[$i]['pdf']"
                                                    icon="heroicon-o-document-arrow-down"
                                                    color="danger"
                                                    label="PDF"
                                                    tooltip="Descargar recibo en PDF"
                                                />
                                            @endif
                                            @if (! ($links[$i]['view'] ?? null) && ! ($links[$i]['edit'] ?? null))
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
