<?php

namespace App\Filament\Widgets\Reports;

use App\Support\Reports;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Tabla de un informe (mismos datos que el Excel).
 * Cada subclase indica qué informe muestra.
 */
abstract class ReportTableWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static string $view = 'filament.widgets.report-table';

    protected int | string | array $columnSpan = 'full';

    /** Clave del informe en Reports::catalog() */
    abstract protected function report(): string;

    protected function getViewData(): array
    {
        $reports = Reports::make($this->filters ?? []);
        [$title, $method] = Reports::catalog()[$this->report()];
        $result = $reports->{$method}();
        [$headings, $rows] = $result;

        return [
            'title'    => $title,
            'period'   => $reports->periodLabel(),
            'headings' => $headings,
            'rows'     => $rows,
            // Enlaces por fila según permisos (solo algunos informes, p. ej. Pagos)
            'links'    => $result[2] ?? null,
            'report'   => $this->report(),
        ];
    }
}
