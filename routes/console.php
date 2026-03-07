<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('horizonhub:evaluate-alerts')->everyMinute();
Schedule::command('horizonhub:mark-stale-services-offline')->everyMinute();
