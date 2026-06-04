<?php

use Illuminate\Support\Facades\Schedule;

if (! config('horizonhub.mock')) {
    Schedule::command('hh:evaluate-alerts')->everyMinute();
    Schedule::command('hh:mark-stale-services-offline')->everyMinute();
}
