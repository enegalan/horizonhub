<?php

namespace App\Console\Commands;

use App\Services\AlertEngine;
use Illuminate\Console\Command;

class EvaluateAlertsCommand extends Command {
    protected $signature = 'horizonhub:evaluate-alerts';

    protected $description = 'Evaluate Horizon Hub alert rules (worker offline, queue blocked, etc.)';

    public function handle(AlertEngine $engine): int {
        $engine->evaluateScheduled();
        return self::SUCCESS;
    }
}
