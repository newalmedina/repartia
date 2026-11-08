<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Package;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Filtros compartidos por los widgets del dashboard:
 * fecha desde/hasta y repartidor (este último solo con "ver todos los pedidos").
 */
trait UsesPackageFilters
{
    use InteractsWithPageFilters;

    protected function startDate(): Carbon
    {
        $value = $this->filters['startDate'] ?? null;

        return $value ? Carbon::parse($value)->startOfDay() : now()->startOfMonth();
    }

    protected function endDate(): Carbon
    {
        $value = $this->filters['endDate'] ?? null;

        return $value ? Carbon::parse($value)->endOfDay() : now()->endOfDay();
    }

    /** Repartidor elegido en el filtro (ignorado si no puede ver todos los pedidos) */
    protected function selectedDriverId(): ?int
    {
        if (!Auth::user()?->canViewAllPackages()) {
            return null;
        }

        $value = $this->filters['driverId'] ?? null;

        return $value ? (int) $value : null;
    }

    /** Categoría elegida (solo existe en Informes) */
    protected function selectedCategoryId(): ?int
    {
        $value = $this->filters['categoryId'] ?? null;

        return $value ? (int) $value : null;
    }

    /** Pedidos visibles para el usuario + filtros de repartidor y categoría */
    protected function packages(): Builder
    {
        return Package::query()
            ->visibleTo(Auth::user())
            ->when($this->selectedDriverId(), fn(Builder $q, int $id) => $q->where('packages.user_id', $id))
            ->when($this->selectedCategoryId(), fn(Builder $q, int $id) => $q->where('packages.package_category_id', $id));
    }

    protected function deliveredInRange(): Builder
    {
        return $this->packages()
            ->delivered()
            ->whereBetween('delivered_at', [$this->startDate(), $this->endDate()]);
    }

    protected function returnedInRange(): Builder
    {
        return $this->packages()
            ->returned()
            ->whereBetween('returned_at', [$this->startDate(), $this->endDate()]);
    }

    /** Pedidos con fecha de envío dentro del rango */
    protected function shippedInRange(): Builder
    {
        return $this->packages()
            ->whereBetween('packages.shipping_date', [$this->startDate()->toDateString(), $this->endDate()->toDateString()]);
    }

    /** [Y-m-d => total] para todos los días del rango (los días sin datos a 0) */
    protected function countPerDay(Builder $query, string $column): array
    {
        $rows = $query
            ->selectRaw("DATE({$column}) as day, COUNT(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = [];
        for ($day = $this->startDate()->copy(); $day->lte($this->endDate()); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $days[$key] = (int) ($rows[$key] ?? 0);
        }

        return $days;
    }

    protected static function euros(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' €';
    }
}
