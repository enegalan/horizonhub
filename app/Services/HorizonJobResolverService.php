<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Support\Collection;

class HorizonJobResolverService
{
    /**
     * The cache prefix for the job service mapping.
     *
     * @var string
     */
    private const CACHE_PREFIX = 'horizonhub:job_service:';

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
    public function getJobAndService(string $jobUuid): ?array
    {
        $cacheKey = self::CACHE_PREFIX.$jobUuid;
        $ttl = (int) \config('horizonhub.job_resolver_cache_ttl');

        $serviceId = $this->cache->get($cacheKey);
        if ($serviceId !== null) {
            $service = Service::find((int) $serviceId);
            if ($service && $service->base_url) {
                $response = $this->horizonApi->getFailedJob($service, $jobUuid);
                if (($response['success'] ?? false) && isset($response['data']) && \is_array($response['data'])) {
                    return [
                        'service' => $service,
                        'job' => $response['data'],
                    ];
                }
                $this->cache->forget($cacheKey);
            }
        }

        /** @var Collection<int, Service> $services */
        $services = Service::query()->whereNotNull('base_url')->get();

        foreach ($services as $service) {
            $response = $this->horizonApi->getFailedJob($service, $jobUuid);
            if (! ($response['success'] ?? false)) {
                continue;
            }
            $data = $response['data'] ?? null;
            if (! \is_array($data)) {
                continue;
            }
            $this->cache->put($cacheKey, $service->id, $ttl);

            return [
                'service' => $service,
                'job' => $data,
            ];
        }

        return null;
    }

    /**
     * Resolve only the service that owns the job (when job data is not needed).
     */
    public function getServiceForJob(string $jobUuid): ?Service
    {
        $resolved = $this->getJobAndService($jobUuid);

        return $resolved !== null ? $resolved['service'] : null;
    }

    /**
     * Resolve only the job data for a job UUID.
     *
     * @return array<string, mixed>|null
     */
    public function getJobData(string $jobUuid): ?array
    {
        $resolved = $this->getJobAndService($jobUuid);

        return $resolved !== null ? $resolved['job'] : null;
    }

    /**
     * Clear the cache for a job UUID.
     */
    public function clearCache(string $jobUuid): void
    {
        $this->cache->forget(self::CACHE_PREFIX.$jobUuid);
    }
}
