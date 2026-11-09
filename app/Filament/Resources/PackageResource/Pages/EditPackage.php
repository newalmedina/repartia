<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Filament\Resources\PackageResource\Concerns\AsksPricingPolicy;
use App\Models\Package;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPackage extends EditRecord
{
    use AsksPricingPolicy;

    protected static string $resource = PackageResource::class;

    protected static ?string $title = 'Editar pedido';

    /** Eliminar: solo con permiso y si no han pasado 24 h desde la entrega/devolución */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Eliminar')
                ->visible(fn() => PackageResource::canDelete($this->record)),
        ];
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
            ->url($this->getResource()::getUrl('index'))
            ->color('gray');
    }

    /** "status" no es columna: se traduce a delivered/returned antes de guardar */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = array_merge($data, Package::statusAttributes($data['status'] ?? null));
        unset($data['status']);

        // Sin "Ver todos los pedidos del centro": el pedido es siempre suyo (no se fía del formulario)
        if (!PackageResource::canAssignAnyDriver()) {
            $data['user_id'] = \Illuminate\Support\Facades\Auth::id();
        }

        return $data;
    }

    /**
     * Comprobación extra al guardar: si entre que se abrió el formulario y se guarda
     * ya han pasado las 24 h, no se permite.
     */
    protected function beforeSave(): void
    {
        if ($this->record->fresh()->isLockedFor(auth()->user())) {
            \Filament\Notifications\Notification::make()
                ->title('Este pedido ya está cerrado y no se puede modificar')
                ->danger()
                ->send();

            $this->halt();
        }

        // Importes calculados con otra forma de pago / precio en esa dirección: preguntar
        $this->askPricingPolicyIfNeeded();
    }

    protected function pricingDecisionSubmitted(): void
    {
        $this->save();
    }

    /** Guarda aplicando la decisión del aviso (por defecto, mantener) */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->pricingPolicy = $this->pricingDecision ?? Package::POLICY_KEEP;
        $this->pricingDecision = null;

        $record->update($data);

        return $record;
    }

    // Tras guardar, refresca fechas y comisión calculadas por el modelo
    protected function afterSave(): void
    {
        $this->record->refresh();
        $this->fillForm();
    }
}
