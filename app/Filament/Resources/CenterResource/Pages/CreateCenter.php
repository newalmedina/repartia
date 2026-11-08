<?php

namespace App\Filament\Resources\CenterResource\Pages;

use App\Filament\Resources\CenterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCenter extends CreateRecord
{
    protected static string $resource = CenterResource::class;

    protected static ?string $title = 'Nuevo centro';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
