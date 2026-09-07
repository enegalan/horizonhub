<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('hh:evaluate-alerts')->everyMinute()->withoutOverlapping();
Schedule::command('hh:mark-stale-services-offline')->everyMinute()->withoutOverlapping();
