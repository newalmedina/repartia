<?php

namespace App\Support;

use Carbon\Carbon;

class Dates
{
    /**
     * Solo el día ("2026-11-06") de lo que envía un calendario de Filament.
     * El calendario manda fecha Y hora ("2026-11-06 10:24:00"); comparada con una columna
     * de tipo fecha, esa hora deja fuera el propio día en los filtros "desde / hasta".
     */
    public static function day($value): ?string
    {
        return filled($value) ? Carbon::parse($value)->toDateString() : null;
    }
}
