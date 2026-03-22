<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Support\Horizon\ConfigHelper;
use Illuminate\Console\Command;

class MarkStaleServicesOfflineCommand extends Command
{
    protected $signature = 'horizonhub:mark-stale-services-offline';

    protected $description = 'Mark services stand-by/offline and remove dead supervisors by last_seen_at thresholds';

    public function handle(): int
    {
        $stale_minutes = ConfigHelper::get('horizonhub.stale_minutes');
        $dead_minutes = ConfigHelper::get('horizonhub.dead_service_minutes');
        $stale_threshold = \now()->subMinutes($stale_minutes);
        $dead_threshold = \now()->subMinutes($dead_minutes);

        $stand_by = Service::query()
            ->where('status', 'online')
            ->where(function ($q) use ($stale_threshold) {
                $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $stale_threshold);
            })
            ->update(['status' => 'stand_by']);
        if ($stand_by > 0) {
            $this->info("Marked {$stand_by} service(s) as stand-by (no events since {$stale_minutes} min).");
        }

        $offline = Service::query()
            ->whereIn('status', ['online', 'stand_by'])
            ->where(function ($q) use ($dead_threshold) {
                $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $dead_threshold);
            })
            ->update(['status' => 'offline']);
        if ($offline > 0) {
            $this->info("Marked {$offline} service(s) as offline (no events since {$dead_minutes} min).");
        }

        return self::SUCCESS;
    }
}
