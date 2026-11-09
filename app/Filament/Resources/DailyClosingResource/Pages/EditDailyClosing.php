<?php

namespace App\Filament\Resources\DailyClosingResource\Pages;

use App\Filament\Resources\DailyClosingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDailyClosing extends EditRecord
{
    protected static string $resource = DailyClosingResource::class;

    protected static ?string $title = 'Cuadre de jornada';

    protected function getHeaderActions(): array
    {
        return [
            DailyClosingResource::confirmAction(Actions\Action::class, 'driver')
                ->after(fn() => $this->redirect(DailyClosingResource::getUrl('view', ['record' => $this->record]))),
            DailyClosingResource::confirmAction(Actions\Action::class, 'admin')
                ->after(fn() => $this->redirect(DailyClosingResource::getUrl('view', ['record' => $this->record]))),
            Actions\DeleteAction::make()->label('Eliminar'),
        ];
    }

    /** Tras confirmar o guardar, se refresca lo que se ve */
    protected function afterSave(): void
    {
        $this->record->refresh();
        $this->fillForm();
    }
}
