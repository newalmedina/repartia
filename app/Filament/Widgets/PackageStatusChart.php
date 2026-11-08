<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesPackageFilters;
use Filament\Widgets\ChartWidget;

class PackageStatusChart extends ChartWidget
{
    use UsesPackageFilters;

    protected static ?string $heading = 'Estado de los pedidos enviados en el periodo';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $pending   = $this->shippedInRange()->pending()->count();
        $delivered = $this->shippedInRange()->delivered()->count();
        $returned  = $this->shippedInRange()->returned()->count();

        return [
            'datasets' => [
                [
                    'data'            => [$delivered, $returned, $pending],
                    'backgroundColor' => ['#10b981', '#ef4444', '#f59e0b'],
                    'borderWidth'     => 0,
                ],
            ],
            'labels' => ['Entregados', 'Devueltos', 'Pendientes'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales'  => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
