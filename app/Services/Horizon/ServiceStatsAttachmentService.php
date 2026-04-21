<?php

namespace App\Services\Horizon;

use App\Models\Service;
use Illuminate\Support\Collection;

class ServiceStatsAttachmentService
{
    /**
     * Attach the Horizon stats to the services.
     *
     * @param iterable<int, Service>|Collection<int, Service> $services The services.
     * @param HorizonApiProxyService $horizonApi The horizon API proxy service.
     */
    public function attachHorizonStats(iterable $services, HorizonApiProxyService $horizonApi): void
    {
        foreach ($services as $service) {
            if (! $service instanceof Service) {
                continue;
            }

            if (! $service->getBaseUrl()) {
                $service->horizon_failed_jobs_count = 0;
                $service->horizon_jobs_count = 0;
                $service->horizon_status = null;

                continue;
            }

            $response = $horizonApi->getStats($service);

            $data = null;

            if (($response['success'] ?? false) && isset($response['data']) && \is_array($response['data'])) {
                $data = $response['data'];
            }
            $snap = [
                'failedJobs' => $data && isset($data['failedJobs']) ? (int) $data['failedJobs'] : 0,
                'recentJobs' => $data && isset($data['recentJobs']) ? (int) $data['recentJobs'] : 0,
                'status' => $data && isset($data['status']) && (string) $data['status'] !== '' ? (string) $data['status'] : null,
            ];

            $service->horizon_failed_jobs_count = $snap['failedJobs'];
            $service->horizon_jobs_count = $snap['recentJobs'];
            $service->horizon_status = $snap['status'];
        }
    }
}
