<?php

namespace App\Filament\Resources\PackageResource\Concerns;

use App\Filament\Resources\PackageResource;
use App\Models\Package;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;

/**
 * Antes de guardar un pedido entregado: si en su dirección (mismo repartidor y día)
 * hay importes calculados con otra forma de pago u otro precio, pregunta qué hacer.
 */
trait AsksPricingPolicy
{
    /** keep | mode | amount | both (null = aún no se ha preguntado) */
    public ?string $pricingDecision = null;

    /** Diferencias en la dirección a la que va el pedido según lo que hay en el formulario */
    protected function targetPricingStaleness(): array
    {
        $data = $this->data ?? [];

        if (($data['status'] ?? null) !== Package::STATUS_DELIVERED || empty($data['user_id'])) {
            return [];
        }

        $date = !empty($data['delivered_at']) ? Carbon::parse($data['delivered_at'])->toDateString() : now()->toDateString();
        $key  = Package::makeAddressKey($data['address'] ?? null, $data['postal_code'] ?? null, $data['municipality'] ?? null);

        return Package::pricingStaleness((int) $data['user_id'], $date, $key);
    }

    /** Si hay importes antiguos y no se ha decidido, abre el aviso y detiene el guardado */
    protected function askPricingPolicyIfNeeded(): void
    {
        if ($this->pricingDecision !== null || !$this->targetPricingStaleness()) {
            return;
        }

        $this->mountAction('pricingPolicy');
        $this->halt();
    }

    public function pricingPolicyAction(): Action
    {
        return Action::make('pricingPolicy')
            ->modalHeading('Este pedido está en una dirección ya facturada')
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalWidth('lg')
            ->modalDescription(fn() => PackageResource::pricingPolicyDescription($this->targetPricingStaleness()))
            ->form(fn() => [
                Radio::make('policy')
                    ->label('¿Qué hago?')
                    ->options(PackageResource::pricingPolicyOptions($this->targetPricingStaleness()))
                    ->default(Package::POLICY_KEEP)
                    ->required(),
            ])
            ->modalSubmitActionLabel('Guardar')
            ->action(function (array $data) {
                $this->pricingDecision = $data['policy'];
                $this->pricingDecisionSubmitted();
            });
    }

    /** Vuelve a lanzar el guardado tras elegir (create() o save()) */
    abstract protected function pricingDecisionSubmitted(): void;
}
