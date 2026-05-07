<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('hh:evaluate-alerts')->everyMinute();
Schedule::command('hh:mark-stale-services-offline')->everyMinute();
