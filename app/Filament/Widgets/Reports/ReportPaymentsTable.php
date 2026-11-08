<?php

namespace App\Filament\Widgets\Reports;

class ReportPaymentsTable extends ReportTableWidget
{
    protected static ?int $sort = 12;

    protected function report(): string
    {
        return 'payments';
    }
}
