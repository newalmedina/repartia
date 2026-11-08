<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Support\Reports;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string
    {
        return 'Pago ' . $this->record->number . ' · ' . $this->record->driver?->name;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['package_ids'] = $this->record->packages()->pluck('id')->map(fn($id) => (string) $id)->all();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn() => Reports::paymentReceiptPdf($this->record)),
            Actions\Action::make('excel')
                ->label('Descargar recibo (Excel)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn() => Reports::paymentReceiptExcel($this->record)),
            Actions\EditAction::make()->label('Editar'),
        ];
    }
}
