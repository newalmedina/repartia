<?php

namespace App\Filament\Resources\PaymentResource\RelationManagers;

use App\Models\Package;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Pedidos incluidos en el pago (solo lectura) */
class PackagesRelationManager extends RelationManager
{
    protected static string $relationship = 'packages';

    protected static ?string $title = 'Pedidos pagados';

    protected static ?string $icon = 'heroicon-o-cube';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_number')
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Nº pedido')
                    ->weight('bold')
                    ->description(fn(Package $record) => $record->recipient_name)
                    ->searchable(),
                Tables\Columns\TextColumn::make('delivered_at')
                    ->label('Entregado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('address')
                    ->label('Dirección')
                    ->description(fn(Package $record) => trim($record->postal_code . ' ' . $record->municipality))
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('delivery_price')
                    ->label('Comisión')
                    ->money('EUR')
                    ->description(fn(Package $record) => $record->same_address ? 'Misma dirección' : null)
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total')->money('EUR')),
            ])
            ->defaultSort('delivered_at');
    }
}
