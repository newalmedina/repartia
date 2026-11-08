<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesPackageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PackageStatsOverview extends StatsOverviewWidget
{
    use UsesPackageFilters;

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $delivered  = $this->deliveredInRange()->count();
        $returned   = $this->returnedInRange()->count();
        $shipped    = $this->shippedInRange()->count();
        $pending    = $this->packages()->pending()->count();
        $commission = (float) $this->deliveredInRange()->sum('delivery_price');

        $closed      = $delivered + $returned;
        $successRate = $closed > 0 ? round($delivered * 100 / $closed, 1) : 0;

        $deliveredPerDay = array_values($this->countPerDay($this->deliveredInRange(), 'delivered_at'));
        $returnedPerDay  = array_values($this->countPerDay($this->returnedInRange(), 'returned_at'));
        $shippedPerDay   = array_values($this->countPerDay($this->shippedInRange(), 'packages.shipping_date'));

        return [
            Stat::make('Entregados', number_format($delivered, 0, ',', '.'))
                ->description('En el periodo')
                ->descriptionIcon('heroicon-m-check-circle')
                ->chart($deliveredPerDay)
                ->color('success'),
            Stat::make('Devueltos', number_format($returned, 0, ',', '.'))
                ->description($closed > 0 ? round($returned * 100 / $closed, 1) . '% de los cerrados' : 'En el periodo')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->chart($returnedPerDay)
                ->color('danger'),
            Stat::make('Comisiones a pagar', static::euros($commission))
                ->description($delivered > 0 ? 'Media ' . static::euros($commission / $delivered) . ' por entrega' : 'Sin entregas')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),
            Stat::make('Tasa de entrega', str_replace('.', ',', (string) $successRate) . '%')
                ->description('Entregados / (entregados + devueltos)')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($successRate >= 90 ? 'success' : ($successRate >= 75 ? 'warning' : 'danger')),
            Stat::make('Pedidos enviados', number_format($shipped, 0, ',', '.'))
                ->description('Con fecha de envío en el periodo')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->chart($shippedPerDay)
                ->color('primary'),
            Stat::make('Pendientes ahora', number_format($pending, 0, ',', '.'))
                ->description('Sin entregar ni devolver')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
