<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit">Actualizar perfil</x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
