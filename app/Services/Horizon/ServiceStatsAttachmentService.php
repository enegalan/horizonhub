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
            if (! $service->enabled) {
                $service->horizon_failed_jobs_count = 0;
                $service->horizon_jobs_count = 0;
                $service->horizon_status = null;

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

            if ($response['success'] && isset($response['data'])) {
                $data = $response['data'];
            }

            $service->horizon_failed_jobs_count = $data && isset($data['failedJobs']) ? (int) $data['failedJobs'] : 0;
            $service->horizon_jobs_count = $data && isset($data['recentJobs']) ? (int) $data['recentJobs'] : 0;
            $service->horizon_status = $data && isset($data['status']) && (string) $data['status'] !== ''
                ? (string) $data['status']
                : null;
        }
    }

    /**
     * Summarize service availability for list stats.
     *
     * @param Collection<int, Service> $services
     *
     * @return array{total: int, online: int, offline: int}
     */
    public function buildListSummaryCounts(Collection $services): array
    {
        $enabledServices = $services->where('enabled', true);

        return [
            'total' => $services->count(),
            'online' => $enabledServices->where('status', 'online')->count(),
            'offline' => $enabledServices->whereIn('status', ['offline', 'stand_by'])->count(),
        ];
    }
}
