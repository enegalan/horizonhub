<?php

namespace App\Console\Commands;

use App\Models\HorizonSupervisorState;
use App\Models\Service;
use Illuminate\Console\Command;

class MarkStaleServicesOfflineCommand extends Command {
    protected $signature = 'horizon-hub:mark-stale-services-offline';

    protected $description = 'Mark services stand-by/offline and remove dead supervisors by last_seen_at thresholds';

    public function handle(): int {
        $stale_minutes = config('horizonhub.stale_minutes', 5);
        $dead_minutes = config('horizonhub.dead_service_minutes', 15);
        $stale_threshold = now()->subMinutes($stale_minutes);
        $dead_threshold = now()->subMinutes($dead_minutes);

        $stand_by = Service::query()
            ->where('status', 'online')
            ->where(function ($q) use ($stale_threshold) {
                $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $stale_threshold);
            })
            ->update(array('status' => 'stand_by'));
        if ($stand_by > 0) {
            $this->info("Marked {$stand_by} service(s) as stand-by (no events since {$stale_minutes} min).");
        }

        $offline = Service::query()
            ->whereIn('status', array('online', 'stand_by'))
            ->where(function ($q) use ($dead_threshold) {
                $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $dead_threshold);
            })
            ->update(array('status' => 'offline'));
        if ($offline > 0) {
            $this->info("Marked {$offline} service(s) as offline (no events since {$dead_minutes} min).");
        }

        $deleted = HorizonSupervisorState::query()
            ->where('last_seen_at', '<', $dead_threshold)
            ->delete();
        if ($deleted > 0) {
            $this->info("Removed {$deleted} supervisor(s) with no signal since {$dead_minutes} min.");
        }

        return self::SUCCESS;
    }
}
