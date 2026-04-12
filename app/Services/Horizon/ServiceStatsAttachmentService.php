<?php

namespace App\Services\Horizon;

use App\Models\Service;
use Illuminate\Support\Collection;

class ServiceStatsAttachmentService
{
    /**
     * Mutates each service with horizon_failed_jobs_count, horizon_jobs_count, horizon_status.
     *
     * @param  iterable<int, Service>|Collection<int, Service>  $services
     */
    public function attachHorizonStats(iterable $services, HorizonApiProxyService $horizonApi): void
    {
        foreach ($services as $service) {
            if (! $service instanceof Service) {
                continue;
            }

            if (! $service->base_url) {
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
