<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesPackageFilters;
use Filament\Widgets\ChartWidget;

class CommissionsByDriverChart extends ChartWidget
{
    use UsesPackageFilters;

    protected static ?string $heading = 'Comisiones a pagar por repartidor (€)';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $rows = $this->deliveredInRange()
            ->join('users', 'users.id', '=', 'packages.user_id')
            ->selectRaw('users.name as driver, SUM(packages.delivery_price) as total')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get();

        return [
            'datasets' => [
                [
                    'label'           => 'Comisión (€)',
                    'data'            => $rows->map(fn($r) => round((float) $r->total, 2))->all(),
                    'backgroundColor' => '#0ea5e9',
                    'borderRadius'    => 6,
                ],
            ],
            'labels' => $rows->pluck('driver')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales'  => ['y' => ['beginAtZero' => true]],
        ];
    }
}
