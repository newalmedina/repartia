<?php

namespace App\Filament\Resources\DailyClosingResource\Pages;

use App\Filament\Resources\DailyClosingResource;
use App\Models\DailyClosing;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDailyClosing extends CreateRecord
{
    protected static string $resource = DailyClosingResource::class;

    protected static ?string $title = 'Abrir cuadre de jornada';

    /** Sin permiso de administrador: el cuadre es siempre suyo y de hoy (no se fía del formulario) */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!DailyClosingResource::isAdministrator()) {
            $data['user_id'] = Auth::id();
            $data['date']    = today()->toDateString();
            unset($data['assigned_count']);
        }

        return $data;
    }

    /** El cuadre anterior de ese repartidor tiene que estar cerrado */
    protected function beforeCreate(): void
    {
        $userId = DailyClosingResource::isAdministrator() ? ($this->data['user_id'] ?? null) : Auth::id();
        $date   = DailyClosingResource::isAdministrator() ? ($this->data['date'] ?? null) : today()->toDateString();

        if ($reason = DailyClosing::openingBlockedReason($userId ? (int) $userId : null, $date)) {
            Notification::make()
                ->title('No se puede abrir el cuadre')
                ->body($reason)
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
