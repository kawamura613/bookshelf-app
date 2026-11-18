<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * 読書計画の失効処理とリマインダー通知を毎日20時に実行する。
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('reading-plans:run-daily')->daily()->at('20:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
