<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesPackageFilters;
use Filament\Widgets\ChartWidget;

class PackagesByCategoryChart extends ChartWidget
{
    use UsesPackageFilters;

    protected static ?string $heading = 'Entregados y devueltos por categoría';

    protected static ?int $sort = 4;

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $byCategory = fn($query) => $query
            ->leftJoin('package_categories', 'package_categories.id', '=', 'packages.package_category_id')
            ->selectRaw("COALESCE(package_categories.name, 'Sin categoría') as category, COUNT(*) as total")
            ->groupBy('category')
            ->pluck('total', 'category');

        $delivered = $byCategory($this->deliveredInRange());
        $returned  = $byCategory($this->returnedInRange());

        $labels = $delivered->keys()->merge($returned->keys())->unique()->sort()->values();

        return [
            'datasets' => [
                [
                    'label'           => 'Entregados',
                    'data'            => $labels->map(fn($l) => (int) ($delivered[$l] ?? 0))->all(),
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label'           => 'Devueltos',
                    'data'            => $labels->map(fn($l) => (int) ($returned[$l] ?? 0))->all(),
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => $labels->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
