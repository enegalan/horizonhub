<?php

namespace App\Services\Horizon;

use App\Models\Service;
use Illuminate\Contracts\Cache\Repository as CacheContract;

class HorizonJobResolverService
{

    /**
     * The Horizon API proxy service.
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * The cache repository.
     */
    private CacheContract $cache;

    public function __construct(
        HorizonApiProxyService $horizonApi,
        CacheContract $cache
    ) {
        $this->horizonApi = $horizonApi;
        $this->cache = $cache;
    }

    /**
     * Resolve the service and job data for a job UUID.
     *
     * @return array{service: Service, job: array<string, mixed>}|null
     */
    public function getJobAndService(string $jobUuid, int $serviceId): ?array
    {
        $service = Service::query()
            ->whereKey($serviceId)
            ->whereNotNull('base_url')
            ->first();

        if ($service instanceof Service) {
            $response = $this->horizonApi->getFailedJob($service, $jobUuid);
            if (($response['success'] ?? false) && isset($response['data']) && \is_array($response['data'])) {
                return [
                    'service' => $service,
                    'job' => $response['data'],
                ];
            }
        }

        return null;
    }
}
