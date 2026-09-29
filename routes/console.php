<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bitacora:reconcile-activity-logs')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('drivers:sync-expired')
    ->dailyAt('00:05')
    ->withoutOverlapping();

Schedule::command('vehicle-assignments:sync-expired')
    ->dailyAt('00:10')
    ->withoutOverlapping();

Schedule::command('contracts:send-expiration-alerts')
    ->cron('0 8 1,15 * *')
    ->withoutOverlapping();

Schedule::command('operations:send-daily-closing')
    ->dailyAt('20:00')
    ->timezone('America/La_Paz')
    ->withoutOverlapping();

Schedule::command('contracts:cancel-expired-pickups')
    ->hourly()
    ->withoutOverlapping();

Schedule::call(static function (): void {
    DB::table('window_load_metrics')->where('measured_at', '<', now()->subDays(90))->delete();
})->dailyAt('03:20')->name('prune-window-load-metrics')->withoutOverlapping();
