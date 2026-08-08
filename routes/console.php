<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule membership expiration check to run daily after 12:00
Schedule::command('memberships:deactivate-expired')
    ->dailyAt('12:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
