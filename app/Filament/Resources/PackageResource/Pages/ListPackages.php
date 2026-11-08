<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Models\Package;
use App\Models\PackageCategory;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPackages extends ListRecords
{
    protected static string $resource = PackageResource::class;

    protected static ?string $title = 'Pedidos';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nuevo pedido'),
        ];
    }

    /** Pedidos que ve el usuario (su centro, y solo los suyos si no tiene "ver todos") */
    protected static function baseQuery(): Builder
    {
        return PackageResource::getEloquentQuery();
    }

    /** Pestañas con su contador al lado */
    public function getTabs(): array
    {
        $count = fn(callable $scope) => $scope(static::baseQuery())->count();

        return [
            'pending' => Tab::make('Pendientes')
                ->icon('heroicon-o-clock')
                ->badge($count(fn($q) => $q->pending()))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->pending()),
            'delivered' => Tab::make('Entregados')
                ->icon('heroicon-o-check-circle')
                ->badge($count(fn($q) => $q->delivered()))
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->delivered()),
            'returned' => Tab::make('Devueltos')
                ->icon('heroicon-o-arrow-uturn-left')
                ->badge($count(fn($q) => $q->returned()))
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->returned()),
            'all' => Tab::make('Todos')
                ->icon('heroicon-o-squares-2x2')
                ->badge($count(fn($q) => $q))
                ->badgeColor('gray'),
        ];
    }

    /** Al entrar se muestran todos (la fecha de envío ya filtra la semana en curso) */
    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }

    /**
     * Resumen con la búsqueda y los filtros que haya aplicados en la tabla
     * (sin la pestaña, para que se vea el reparto entre estados).
     */
    public function filteredSummaryCards(): array
    {
        $query = $this->applySearchToTableQuery(
            $this->applyFiltersToTableQuery(static::baseQuery())
        );

        $total      = (clone $query)->count();
        $delivered  = (clone $query)->delivered()->count();
        $pending    = (clone $query)->pending()->count();
        $returned   = (clone $query)->returned()->count();
        $commission = (float) (clone $query)->delivered()->sum('delivery_price');
        $notPaid    = (clone $query)->delivered()->where('packages.same_address', true)->count();

        $percent = fn(int $n) => $total > 0 ? round($n * 100 / $total, 1) . '% del total' : '—';

        return [
            [
                'label'       => 'Total',
                'value'       => number_format($total, 0, ',', '.'),
                'description' => 'Pedidos que cumplen los filtros',
                'color'       => 'gray',
                'icon'        => 'heroicon-o-squares-2x2',
            ],
            [
                'label'       => 'Entregados',
                'value'       => number_format($delivered, 0, ',', '.'),
                'description' => $percent($delivered),
                'color'       => 'success',
                'icon'        => 'heroicon-o-check-circle',
            ],
            [
                'label'       => 'Pendientes',
                'value'       => number_format($pending, 0, ',', '.'),
                'description' => $percent($pending),
                'color'       => 'warning',
                'icon'        => 'heroicon-o-clock',
            ],
            [
                'label'       => 'Devueltos',
                'value'       => number_format($returned, 0, ',', '.'),
                'description' => $percent($returned),
                'color'       => 'danger',
                'icon'        => 'heroicon-o-arrow-uturn-left',
            ],
            [
                'label'       => 'Comisiones cobradas',
                'value'       => number_format($commission, 2, ',', '.') . ' €',
                'description' => match (true) {
                    $delivered === 0 => 'Sin entregas',
                    $notPaid > 0     => ($delivered - $notPaid) . " cobran · {$notPaid} sin comisión (misma dirección)",
                    default          => 'Media ' . number_format($commission / $delivered, 2, ',', '.') . ' € por entrega',
                },
                'color'       => 'info',
                'icon'        => 'heroicon-o-banknotes',
            ],
        ];
    }

    /**
     * Leyenda de lo que hay aplicado en la tabla, con fechas en formato dd/mm/aaaa.
     * @return array<int, array{label: string, value: string}>
     */
    public function appliedFiltersLegend(): array
    {
        $filters = $this->tableFilters ?? [];
        $legend  = [];

        if ($range = PackageResource::dateRangeLabel($filters['shipping_date']['from'] ?? null, $filters['shipping_date']['until'] ?? null)) {
            $legend[] = ['label' => 'Fecha de envío', 'value' => $range];
        }

        if ($range = PackageResource::dateRangeLabel($filters['closed_date']['from'] ?? null, $filters['closed_date']['until'] ?? null)) {
            $legend[] = ['label' => 'Fecha de entrega', 'value' => $range];
        }

        if ($driverId = $filters['user_id']['value'] ?? null) {
            $legend[] = ['label' => 'Repartidor', 'value' => User::find($driverId)?->name ?? '—'];
        }

        if ($categoryId = $filters['package_category_id']['value'] ?? null) {
            $legend[] = ['label' => 'Categoría', 'value' => PackageCategory::find($categoryId)?->name ?? '—'];
        }

        if (!empty($filters['shared_address']['isActive'])) {
            $legend[] = ['label' => 'Paquetes', 'value' => 'misma dirección, repartidor y día'];
        }

        if (filled($search = $this->tableSearch ?? null)) {
            $legend[] = ['label' => 'Búsqueda', 'value' => '"' . $search . '"'];
        }

        return $legend;
    }

    /**
     * Tarjetas que se muestran debajo de las pestañas.
     * Respetan los permisos: si solo ve sus pedidos, los números son solo suyos.
     */
    public static function summaryCards(): array
    {
        $monthStart = now()->startOfMonth();
        $todayStart = now()->startOfDay();

        $pending          = static::baseQuery()->pending()->count();
        $unassigned       = static::baseQuery()->pending()->whereNull('user_id')->count();
        $deliveredToday   = static::baseQuery()->delivered()->where('delivered_at', '>=', $todayStart)->count();
        $deliveredMonth   = static::baseQuery()->delivered()->where('delivered_at', '>=', $monthStart)->count();
        $returnedMonth    = static::baseQuery()->returned()->where('returned_at', '>=', $monthStart)->count();
        $commissionMonth  = (float) static::baseQuery()->delivered()->where('delivered_at', '>=', $monthStart)->sum('delivery_price');

        $closedMonth = $deliveredMonth + $returnedMonth;
        $successRate = $closedMonth > 0 ? round($deliveredMonth * 100 / $closedMonth) : null;

        return [
            [
                'label'       => 'Pendientes',
                'value'       => number_format($pending, 0, ',', '.'),
                'description' => $unassigned > 0 ? "{$unassigned} sin repartidor" : 'Todos asignados',
                'color'       => 'warning',
                'icon'        => 'heroicon-o-clock',
            ],
            [
                'label'       => 'Entregados hoy',
                'value'       => number_format($deliveredToday, 0, ',', '.'),
                'description' => 'Por fecha de entrega · ' . now()->format('d/m/Y'),
                'color'       => 'success',
                'icon'        => 'heroicon-o-check-circle',
            ],
            [
                'label'       => 'Entregados este mes',
                'value'       => number_format($deliveredMonth, 0, ',', '.'),
                'description' => ($successRate !== null ? "{$successRate}% de éxito" : 'Sin cierres aún') . ' · por fecha de entrega',
                'color'       => 'primary',
                'icon'        => 'heroicon-o-truck',
            ],
            [
                'label'       => 'Devueltos este mes',
                'value'       => number_format($returnedMonth, 0, ',', '.'),
                'description' => ($closedMonth > 0 ? round($returnedMonth * 100 / $closedMonth) . '% del total cerrado' : '—') . ' · por fecha de devolución',
                'color'       => 'danger',
                'icon'        => 'heroicon-o-arrow-uturn-left',
            ],
            [
                'label'       => 'Comisiones del mes',
                'value'       => number_format($commissionMonth, 2, ',', '.') . ' €',
                'description' => 'Entregas de este mes',
                'color'       => 'info',
                'icon'        => 'heroicon-o-banknotes',
            ],
        ];
    }
}
