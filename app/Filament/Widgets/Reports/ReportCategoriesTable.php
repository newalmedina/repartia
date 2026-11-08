<?php

namespace App\Filament\Widgets\Reports;

class ReportCategoriesTable extends ReportTableWidget
{
    protected static ?int $sort = 11;

    protected function report(): string
    {
        return 'categories';
    }
}
