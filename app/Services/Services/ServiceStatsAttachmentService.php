<?php

namespace App\Services\Services;

use App\Models\Service;
use App\Services\Horizon\HorizonClientService;
use App\Support\Horizon\HorizonStatsReader;

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

            $data = HorizonStatsReader::dataFromResponse($horizonApi->getStats($service));

            $service->horizon_failed_jobs_count = HorizonStatsReader::failedJobs($data);
            $service->horizon_jobs_count = HorizonStatsReader::recentJobs($data);
            $service->horizon_status = HorizonStatsReader::status($data);
        }
    }
}
