<?php

namespace App\Filament\Resources\DailyClosingResource\Pages;

use App\Filament\Resources\DailyClosingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDailyClosing extends ViewRecord
{
    protected static string $resource = DailyClosingResource::class;

    public function getTitle(): string
    {
        return 'Cuadre · ' . $this->record->driver?->name . ' · ' . $this->record->date->format('d/m/Y');
    }

    public function getSubheading(): ?string
    {
        return $this->record->isClosed() ? 'Cerrado: confirmado por el repartidor y por el administrador.' : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            DailyClosingResource::confirmAction(Actions\Action::class, 'driver')
                ->after(fn() => $this->redirect(DailyClosingResource::getUrl('view', ['record' => $this->record]))),
            DailyClosingResource::confirmAction(Actions\Action::class, 'admin')
                ->after(fn() => $this->redirect(DailyClosingResource::getUrl('view', ['record' => $this->record]))),
            Actions\EditAction::make()->label('Editar'),
        ];
    }
}
