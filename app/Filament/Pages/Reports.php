<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PackageResource;
use App\Filament\Widgets\CommissionsByDriverChart;
use App\Filament\Widgets\PackagesByCategoryChart;
use App\Filament\Widgets\PackageStatsOverview;
use App\Filament\Widgets\PackageStatusChart;
use App\Filament\Widgets\PackagesTrendChart;
use App\Filament\Widgets\Reports\ReportCategoriesTable;
use App\Filament\Widgets\Reports\ReportDriversTable;
use App\Filament\Widgets\Reports\ReportPaymentsTable;
use App\Models\PackageCategory;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Reports as ReportData;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Support\Facades\Auth;

/**
 * Informes con gráficas, tablas y descarga a Excel.
 * Permiso: "Ver y descargar informes".
 */
class Reports extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'informes';

    protected static ?string $title = 'Informes';

    protected static ?string $navigationLabel = 'Informes';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Informes';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission(Permissions::REPORTS_VIEW);
    }

    public function getSubheading(): ?string
    {
        return 'Elige el periodo y los filtros; las gráficas, las tablas y el Excel usan los mismos datos.';
    }

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Section::make()
                ->columns(['default' => 1, 'sm' => 2, 'xl' => 4])
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Desde')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->firstDayOfWeek(1)
                        ->placeholder(now()->startOfMonth()->format('d/m/Y'))
                        ->maxDate(fn(Get $get) => $get('endDate') ?: now()),
                    DatePicker::make('endDate')
                        ->label('Hasta')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->firstDayOfWeek(1)
                        ->placeholder(now()->format('d/m/Y'))
                        ->minDate(fn(Get $get) => $get('startDate')),
                    Select::make('driverId')
                        ->label('Repartidor')
                        ->placeholder('Todos')
                        ->options(fn() => PackageResource::driverQuery(User::query())->pluck('name', 'id'))
                        ->searchable()
                        ->visible(fn() => Auth::user()?->canViewAllPackages()),
                    Select::make('categoryId')
                        ->label('Categoría')
                        ->placeholder('Todas')
                        ->options(fn() => PackageCategory::query()
                            ->where('center_id', Auth::user()?->center_id ?? -1)
                            ->orderBy('name')
                            ->pluck('name', 'id')),
                ]),
        ]);
    }

    /** Botón "Descargar Excel" con un informe por opción */
    protected function getHeaderActions(): array
    {
        $actions = collect(ReportData::catalog())
            ->map(fn(array $item, string $key) => Action::make('excel_' . $key)
                ->label($item[0])
                ->icon('heroicon-o-table-cells')
                ->action(fn() => ReportData::make($this->filters ?? [])->excel($key)))
            ->values()
            ->all();

        return [
            ActionGroup::make($actions)
                ->label('Descargar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->button()
                ->color('success'),
        ];
    }

    public function getWidgets(): array
    {
        return [
            PackageStatsOverview::class,
            PackagesTrendChart::class,
            PackageStatusChart::class,
            PackagesByCategoryChart::class,
            CommissionsByDriverChart::class,
            ReportDriversTable::class,
            ReportCategoriesTable::class,
            ReportPaymentsTable::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
