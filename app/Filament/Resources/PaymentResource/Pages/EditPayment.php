<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected static ?string $title = 'Editar pago';

    protected array $packageIds = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()->label('Ver'),
            Actions\DeleteAction::make()
                ->label('Eliminar')
                ->modalDescription('Los pedidos de este pago volverán a quedar sin pagar. ¿Continuar?'),
        ];
    }

    /** Marca los pedidos que ya están en este pago */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['package_ids'] = $this->record->packages()->pluck('id')->map(fn($id) => (string) $id)->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->packageIds = $data['package_ids'] ?? [];
        unset($data['package_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncPackages($this->packageIds);
        $this->record->refresh();
        $this->fillForm();
    }
}
