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

// An import the operator walked away from leaves its unpacked package on disk,
// which for a package with images is not small. Sweep the stale ones nightly.
Schedule::call(function () {
    app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)->pruneStaleSessions(24);
})->dailyAt('03:30')->name('facility-migration-session-prune')->withoutOverlapping();
