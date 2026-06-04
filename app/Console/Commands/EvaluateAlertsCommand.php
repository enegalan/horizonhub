<?php

namespace App\Console\Commands;

use App\Services\Alerts\Engine\AlertEngine;
use Illuminate\Console\Command;

class EvaluateAlertsCommand extends Command
{
    protected $signature = 'hh:evaluate-alerts';

    protected $description = 'Evaluate Horizon Hub alert rules (worker offline, queue blocked, etc.)';

    public function handle(AlertEngine $engine): int
    {
        if (config('horizonhub.mock')) {
            return self::SUCCESS;
        }

        $engine->evaluateScheduled();

        return self::SUCCESS;
    }
}
