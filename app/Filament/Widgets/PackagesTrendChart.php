<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesPackageFilters;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class PackagesTrendChart extends ChartWidget
{
    use UsesPackageFilters;

    protected static ?string $heading = 'Entregados y devueltos por día';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $delivered = $this->countPerDay($this->deliveredInRange(), 'delivered_at');
        $returned  = $this->countPerDay($this->returnedInRange(), 'returned_at');

        return [
            'datasets' => [
                [
                    'label'           => 'Entregados',
                    'data'            => array_values($delivered),
                    'borderColor'     => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'fill'            => true,
                    'tension'         => 0.3,
                ],
                [
                    'label'           => 'Devueltos',
                    'data'            => array_values($returned),
                    'borderColor'     => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.15)',
                    'fill'            => true,
                    'tension'         => 0.3,
                ],
            ],
            'labels' => array_map(fn($day) => Carbon::parse($day)->format('d/m'), array_keys($delivered)),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
