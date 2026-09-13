<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define las tareas programadas de la aplicación.
     */
    protected function schedule(Schedule $schedule)
    {
        //
    }

    /**
     * Registra los comandos para la aplicación.
     */
    protected function commands()
    {
        // Carga automática de todos los comandos en app/Console/Commands
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
