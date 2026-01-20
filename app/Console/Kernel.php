<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\MakeFilamentUser;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        // Override Filament's make:filament-user command to create Admin records
        MakeFilamentUser::class,
    ];

    protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule)
    {
        //
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
