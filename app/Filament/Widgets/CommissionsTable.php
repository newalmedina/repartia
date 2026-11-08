<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesPackageFilters;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Resumen de comisiones a pagar por repartidor en el periodo filtrado.
 */
class CommissionsTable extends TableWidget
{
    use UsesPackageFilters;

    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Comisiones a pagar';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => $this->driversQuery())
            ->heading('Comisiones a pagar del ' . $this->startDate()->format('d-m-Y') . ' al ' . $this->endDate()->format('d-m-Y'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Repartidor')
                    ->weight('bold')
                    ->description(fn(User $record) => $record->phone),
                Tables\Columns\TextColumn::make('delivered_count')
                    ->label('Entregados')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_count')
                    ->label('Cobrados')
                    ->badge()
                    ->color('info')
                    ->tooltip('Entregas que generan pago (con pago por dirección y día, una por dirección y día)')
                    ->sortable(),
                Tables\Columns\TextColumn::make('returned_count')
                    ->label('Devueltos')
                    ->badge()
                    ->color('danger')
                    ->sortable()
                    ->visibleFrom('sm'),
                Tables\Columns\TextColumn::make('price_per_package')
                    ->label('€/paquete actual')
                    ->money('EUR')
                    ->description(fn(User $record) => User::paymentModeOptions()[$record->payment_mode] ?? null)
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('commission_total')
                    ->label('A pagar')
                    ->money('EUR')
                    ->weight('bold')
                    ->color('info')
                    ->default(0)
                    ->sortable(),
            ])
            ->defaultSort('commission_total', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Sin entregas en el periodo');
    }

    protected function driversQuery(): Builder
    {
        $user  = Auth::user();
        $range = [$this->startDate(), $this->endDate()];

        return User::query()
            ->where('users.center_id', $user?->center_id ?? -1)
            // Sin "ver todos los pedidos" solo se ve a sí mismo
            ->when(!$user?->canViewAllPackages(), fn(Builder $q) => $q->where('users.id', $user?->id ?? -1))
            ->when($this->selectedDriverId(), fn(Builder $q, int $id) => $q->where('users.id', $id))
            // Solo usuarios que hayan tenido actividad en el periodo
            ->where(fn(Builder $q) => $q
                ->whereHas('packages', fn(Builder $p) => $p->where('delivered', true)->whereBetween('delivered_at', $range))
                ->orWhereHas('packages', fn(Builder $p) => $p->where('returned', true)->whereBetween('returned_at', $range)))
            ->withCount([
                'packages as delivered_count' => fn(Builder $p) => $p->where('delivered', true)->whereBetween('delivered_at', $range),
                'packages as returned_count'  => fn(Builder $p) => $p->where('returned', true)->whereBetween('returned_at', $range),
                'packages as paid_count'      => fn(Builder $p) => $p->where('delivered', true)->where('delivery_price', '>', 0)->whereBetween('delivered_at', $range),
            ])
            ->withSum([
                'packages as commission_total' => fn(Builder $p) => $p->where('delivered', true)->whereBetween('delivered_at', $range),
            ], 'delivery_price');
    }
}
