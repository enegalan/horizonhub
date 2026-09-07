<?php

namespace App\Services\Services;

use App\Models\Service;
use App\Services\Horizon\HorizonClientService;
use App\Support\Horizon\ClientResponse;
use App\Support\Horizon\StatsReader;

class ServiceStatsAttachmentService
{
    /**
     * Attach the Horizon stats to the services.
     *
     * @param iterable<int, Service> $services The services.
     * @param HorizonClientService $horizonApi The horizon API client.
     */
    public function attachHorizonStats(iterable $services, HorizonClientService $horizonApi): void
    {
        foreach ($services as $service) {
            if (! $service->enabled) {
                $service->horizon_failed_jobs_count = 0;
                $service->horizon_jobs_count = 0;
                $service->horizon_status = null;

                continue;
            }

            $stats = StatsReader::summary(ClientResponse::data($horizonApi->getStats($service)));

            $service->horizon_failed_jobs_count = $stats['failedJobs'];
            $service->horizon_jobs_count = $stats['recentJobs'];
            $service->horizon_status = $stats['status'];
        }
    }
}
