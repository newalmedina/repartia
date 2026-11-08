<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

/**
 * Vista de solo lectura: la usan quienes no pueden editar
 * y los pedidos cerrados hace más de 24 h.
 */
class ViewPackage extends ViewRecord
{
    protected static string $resource = PackageResource::class;

    protected static ?string $title = 'Ver pedido';

    public function getSubheading(): ?string
    {
        if ($this->record->isPaid()) {
            return 'Este pedido está pagado al repartidor (' . $this->record->payment?->number . ') y no se puede modificar.';
        }

        return $this->record->isLockedFor(auth()->user())
            ? 'Este pedido se cerró hace más de 24 h y ya no se puede modificar.'
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn() => PackageResource::canEdit($this->record)),
        ];
    }
}
