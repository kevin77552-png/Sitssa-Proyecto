<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('db:backup --trigger=scheduled')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->when(fn () => config('database.connections.'.config('database.default').'.driver') === 'pgsql')
    ->onFailure(fn () => Log::critical('El respaldo programado falló.'));

Schedule::command('imports:dispatch-outbox')
    ->everyMinute()
    ->withoutOverlapping()
    ->onFailure(fn () => Log::critical('Falló el despacho del outbox de importaciones.'));
