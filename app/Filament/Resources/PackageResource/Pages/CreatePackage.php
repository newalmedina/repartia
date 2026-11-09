<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Filament\Resources\PackageResource\Concerns\AsksPricingPolicy;
use App\Models\Package;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePackage extends CreateRecord
{
    use AsksPricingPolicy;

    protected static string $resource = PackageResource::class;

    protected static ?string $title = 'Nuevo pedido';

    // center_id y created_by los pone el modelo (Package::booted) con el usuario logueado

    /** "status" no es columna: se traduce a delivered/returned antes de guardar */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = array_merge($data, Package::statusAttributes($data['status'] ?? null));
        unset($data['status']);

        // Sin "Ver todos los pedidos del centro": el pedido es siempre suyo (no se fía del formulario)
        if (!PackageResource::canAssignAnyDriver()) {
            $data['user_id'] = \Illuminate\Support\Facades\Auth::id();
        }

        return $data;
    }

    /** Si va a una dirección ya facturada con otra forma de pago / precio: preguntar */
    protected function beforeCreate(): void
    {
        $this->askPricingPolicyIfNeeded();
    }

    protected function pricingDecisionSubmitted(): void
    {
        $this->create();
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = new Package($data);
        $record->pricingPolicy = $this->pricingDecision ?? Package::POLICY_KEEP;
        $this->pricingDecision = null;
        $record->save();

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
