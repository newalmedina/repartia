<?php

namespace App\Filament\Resources\DailyClosingResource\Pages;

use App\Filament\Resources\DailyClosingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDailyClosings extends ListRecords
{
    protected static string $resource = DailyClosingResource::class;

    protected static ?string $title = 'Cuadres de jornada';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Abrir cuadre'),
        ];
    }
}
