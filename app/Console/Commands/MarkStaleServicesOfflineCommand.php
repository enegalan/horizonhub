<?php

namespace App\Console\Commands;

use App\Models\Service;
use Illuminate\Console\Command;

class MarkStaleServicesOfflineCommand extends Command {
    protected $signature = 'horizon-hub:mark-stale-services-offline';

    protected $description = 'Mark services as offline when last_seen_at is older than configured threshold';

    public function handle(): int {
        $minutes = config('horizonhub.stale_minutes', 5);
        $threshold = now()->subMinutes($minutes);

        $updated = Service::query()
            ->where('status', 'online')
            ->where(function ($q) use ($threshold) {
                $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $threshold);
            })
            ->update(array('status' => 'offline'));

        if ($updated > 0) {
            $this->info("Marked {$updated} service(s) as offline (no events since {$minutes} min).");
        }

        return self::SUCCESS;
    }
}
