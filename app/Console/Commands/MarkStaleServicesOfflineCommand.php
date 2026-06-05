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
        $store->markServicesStaleOffline();

        $this->info("Stale service check completed (thresholds: {$staleMinutes} / {$deadMinutes} min).");

        return self::SUCCESS;
    }
}
