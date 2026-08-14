<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Close out trips and shifts whose time has passed. The app also does this
// lazily off list requests, so this only matters once the backend runs
// somewhere with `schedule:run` on a cron — at which point it keeps records
// tidy even on a day nobody opens the admin panel.
Schedule::command('work:close-past')->everyTenMinutes();
