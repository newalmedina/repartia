<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CenterResource;
use App\Models\Center;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Cada usuario edita aquí los datos de SU centro.
 * El super admin gestiona todos los centros desde el módulo "Centros".
 */
class MyCenter extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Administración usuarios';
    protected static ?string $navigationLabel = 'Mi centro';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'mi-centro';
    protected static ?string $title = 'Mi centro';

    protected static string $view = 'filament.pages.my-center';

    public ?array $data = [];

    protected function getCenter(): ?Center
    {
        return Auth::user()?->center;
    }

    /** Necesita centro asignado y el permiso "Editar mi centro" */
    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user?->center_id !== null
            && $user->hasPermission(Permissions::CENTER_EDIT);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill($this->getCenter()->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(CenterResource::centerSchema(forOwnCenter: true))
            ->model($this->getCenter())
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar cambios')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $center = $this->getCenter();

        // getState() valida y solo devuelve los campos visibles (nunca "active")
        $center->update($this->form->getState());

        Notification::make()
            ->title('Datos del centro guardados')
            ->success()
            ->send();

        // Recarga para que se actualice el logo del panel
        $this->redirect(static::getUrl());
    }
}
