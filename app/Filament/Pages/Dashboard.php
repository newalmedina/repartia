<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PackageResource;
use App\Filament\Widgets\CommissionsByDriverChart;
use App\Filament\Widgets\CommissionsTable;
use App\Filament\Widgets\PackagesByCategoryChart;
use App\Filament\Widgets\PackageStatsOverview;
use App\Filament\Widgets\PackageStatusChart;
use App\Filament\Widgets\PackagesTrendChart;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Escritorio';

    /** Quien no tiene permisos de pedidos solo ve el saludo */
    protected function canSeePackageStats(): bool
    {
        return PackageResource::canViewAny();
    }

    public function getSubheading(): ?string
    {
        if (!$this->canSeePackageStats()) {
            return null;
        }

        return Auth::user()?->canViewAllPackages()
            ? 'Datos de todo tu centro. Puedes filtrar por repartidor.'
            : 'Datos de tus pedidos.';
    }

    public function filtersForm(Form $form): Form
    {
        if (!$this->canSeePackageStats()) {
            return $form->schema([]);
        }

        return $form
            ->schema([
                Section::make()
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->placeholder(now()->startOfMonth()->format('d/m/Y'))
                            ->maxDate(fn(Get $get) => $get('endDate') ?: now()),
                        DatePicker::make('endDate')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->placeholder(now()->format('d/m/Y'))
                            ->minDate(fn(Get $get) => $get('startDate'))
                            ->maxDate(now()),
                        Select::make('driverId')
                            ->label('Repartidor')
                            ->placeholder('Todos')
                            ->options(fn() => PackageResource::driverQuery(\App\Models\User::query())->pluck('name', 'id'))
                            ->searchable()
                            // Solo con permiso de ver todos los pedidos del centro
                            ->visible(fn() => Auth::user()?->canViewAllPackages()),
                    ]),
            ]);
    }

    public function getWidgets(): array
    {
        if (!$this->canSeePackageStats()) {
            return [AccountWidget::class];
        }

        return [
            PackageStatsOverview::class,
            PackagesTrendChart::class,
            PackageStatusChart::class,
            PackagesByCategoryChart::class,
            CommissionsByDriverChart::class,
            CommissionsTable::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
