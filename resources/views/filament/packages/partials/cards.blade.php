<div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
    @foreach ($cards as $card)
        <div
            class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 @if ($loop->last) col-span-2 md:col-span-1 @endif"
        >
            <div class="flex items-center gap-x-2">
                <x-filament::icon
                    :icon="$card['icon']"
                    class="h-5 w-5 shrink-0"
                    style="color: rgb(var(--{{ $card['color'] }}-500));"
                />
                <span class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ $card['label'] }}
                </span>
            </div>

            <div class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                {{ $card['value'] }}
            </div>

            <div class="mt-1 text-xs" style="color: rgb(var(--{{ $card['color'] }}-600));">
                {{ $card['description'] }}
            </div>
        </div>
    @endforeach
</div>
