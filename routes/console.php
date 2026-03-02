<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon-hub:evaluate-alerts')->everyMinute();
Schedule::command('horizon-hub:mark-stale-services-offline')->everyMinute();
