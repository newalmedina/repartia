<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * Tareas programadas. En producción las lanza el cron del contenedor cada minuto:
 *   * * * * * www-data php /var/www/html/artisan schedule:run
 */
class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Borra los enlaces de "recuperar contraseña" caducados
        $schedule->command('auth:clear-resets')->everyFifteenMinutes();

        // Tokens de la API (app de Flutter) caducados hace más de 24 h
        $schedule->command('sanctum:prune-expired --hours=24')->daily();

        // Trabajos fallidos de la cola con más de 7 días
        $schedule->command('queue:prune-failed --hours=168')->daily();

        // Reinicia el worker de la cola cada noche (libera memoria y carga código nuevo)
        $schedule->command('queue:restart')->dailyAt('04:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
