<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected static ?string $title = 'Nuevo pago';

    /** Pedidos marcados (no es columna: se asocian después de crear el pago) */
    protected array $packageIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->packageIds = $data['package_ids'] ?? [];
        unset($data['package_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncPackages($this->packageIds);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
