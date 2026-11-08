<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Package;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Editar usuario';

    /** Qué hacer con los pedidos entregados sin pagar: none | amount | mode | both */
    public ?string $pricingDecision = null;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
            ->url($this->getResource()::getUrl('index'))
            ->color('gray');
    }

    /** Cambios de precio / forma de pago respecto a lo guardado */
    protected function pricingChanges(): array
    {
        $changes = [];

        if (array_key_exists('price_per_package', $this->data ?? [])) {
            $old = round((float) $this->record->price_per_package, 2);
            $new = round((float) $this->data['price_per_package'], 2);
            if ($old !== $new) {
                $changes['amount'] = [$old, $new];
            }
        }

        if (array_key_exists('payment_mode', $this->data ?? [])
            && $this->data['payment_mode'] !== $this->record->payment_mode) {
            $changes['mode'] = [$this->record->payment_mode, $this->data['payment_mode']];
        }

        return $changes;
    }

    /**
     * Si cambia el precio o la forma de pago y hay pedidos entregados sin pagar,
     * se pregunta qué hacer antes de guardar.
     */
    protected function beforeSave(): void
    {
        if ($this->pricingDecision !== null || !$this->pricingChanges()) {
            return;
        }

        if (Package::unpaidDeliveredOf($this->record->id)->exists()) {
            $this->mountAction('pricingChange');
            $this->halt();
        }
    }

    public function pricingChangeAction(): Action
    {
        return Action::make('pricingChange')
            ->modalHeading('Este repartidor tiene pedidos ya facturados')
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalWidth('lg')
            ->modalDescription(function () {
                $changes = $this->pricingChanges();
                $query   = Package::unpaidDeliveredOf($this->record->id);
                $count   = (clone $query)->count();
                $amount  = (float) (clone $query)->sum('delivery_price');
                $euros   = fn($v) => number_format((float) $v, 2, ',', '.') . ' €';
                $modes   = User::paymentModeOptions();

                $lines = [
                    "Tiene <strong>{$count} pedidos entregados sin pagar</strong>, facturados con un total de <strong>{$euros($amount)}</strong>.",
                ];
                if (isset($changes['amount'])) {
                    $lines[] = "El precio por paquete ha cambiado de <strong>{$euros($changes['amount'][0])}</strong> a <strong>{$euros($changes['amount'][1])}</strong>.";
                }
                if (isset($changes['mode'])) {
                    $lines[] = 'La forma de pago ha cambiado de <strong>' . ($modes[$changes['mode'][0]] ?? $changes['mode'][0])
                        . '</strong> a <strong>' . ($modes[$changes['mode'][1]] ?? $changes['mode'][1]) . '</strong>.';
                }
                $lines[] = '¿Quieres modificar esos pedidos? Los pedidos ya pagados no se modifican nunca.';

                return new HtmlString(implode('<br><br>', $lines));
            })
            ->form(function () {
                $changes = $this->pricingChanges();

                $options = ['none' => 'Solo guardar (los pedidos se quedan como están)'];
                if (isset($changes['mode'])) {
                    $options['mode'] = 'Modificar forma de cobro (recalcula qué entregas cobran)';
                }
                if (isset($changes['amount'])) {
                    $options['amount'] = 'Modificar importe de cobro (aplica el nuevo precio)';
                }
                if (isset($changes['mode'], $changes['amount'])) {
                    $options['both'] = 'Modificar ambos';
                }

                return [
                    Radio::make('apply')
                        ->label('¿Qué hago con los pedidos sin pagar?')
                        ->options($options)
                        ->default('none')
                        ->required(),
                ];
            })
            ->modalSubmitActionLabel('Guardar')
            ->action(function (array $data) {
                $this->pricingDecision = $data['apply'];
                $this->save();
            });
    }

    /** Aplica la decisión a los pedidos entregados y sin pagar */
    protected function afterSave(): void
    {
        $decision = $this->pricingDecision;
        $this->pricingDecision = null;

        if (!in_array($decision, ['amount', 'mode', 'both'], true)) {
            return;
        }

        $count = Package::applyDriverPricing(
            $this->record->fresh(),
            amount: in_array($decision, ['amount', 'both'], true),
            mode: in_array($decision, ['mode', 'both'], true),
        );

        Notification::make()
            ->title("Se han reajustado {$count} pedidos sin pagar")
            ->success()
            ->send();
    }
}
