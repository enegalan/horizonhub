<?php

namespace App\Console\Commands;

use App\Contracts\HorizonHubStore;
use Illuminate\Console\Command;

class MarkStaleServicesOfflineCommand extends Command
{
    protected $signature = 'hh:mark-stale-services-offline';

    protected $description = 'Mark services stand-by or offline when last_seen_at exceeds configured thresholds';

    public function handle(HorizonHubStore $store): int
    {
        if (config('horizonhub.mock')) {
            return self::SUCCESS;
        }

        $staleMinutes = (int) config('horizonhub.stale_service_minutes');
        $deadMinutes = (int) config('horizonhub.dead_service_minutes');

        $store->markServicesStaleOffline($staleMinutes, $deadMinutes);

        $this->info("Stale service check completed (thresholds: {$staleMinutes} / {$deadMinutes} min).");

        return self::SUCCESS;
    }
}
