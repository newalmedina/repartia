<?php

namespace App\Filament\Widgets\Reports;

class ReportDriversTable extends ReportTableWidget
{
    protected static ?int $sort = 10;

    protected function report(): string
    {
        return 'drivers';
    }
}
