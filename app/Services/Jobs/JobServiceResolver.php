<?php

namespace App\Services\Jobs;

use App\Contracts\HorizonHubStore;
use App\Models\Service;
use App\Services\Horizon\Contracts\HorizonClientApi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class JobServiceResolver
{
    /**
     * The Horizon API client.
     */
    private HorizonClientApi $horizonApi;

    /**
     * The horizon hub store.
     */
    private HorizonHubStore $store;

    /**
     * The constructor.
     *
     * @param HorizonClientApi $horizonApi The horizon API client.
     * @param HorizonHubStore $store The horizon hub store.
     */
    public function __construct(HorizonClientApi $horizonApi, HorizonHubStore $store)
    {
        $this->horizonApi = $horizonApi;
        $this->store = $store;
    }

    /**
     * Resolve the service that is hosting a job by its UUID, caching the result for future lookups.
     *
     * @return array{service: Service, data: array<string, mixed>}|null
     */
    public function resolve(string $jobUuid): ?array
    {
        $jobUuid = \trim($jobUuid);

        if (blank($jobUuid)) {
            return null;
        }

        $cacheKey = $this->private__cacheKey($jobUuid);

        if (config('horizonhub.mock')) {
            $indexedServiceId = (int) (config('demo.job_service_index')[$jobUuid] ?? 0);

            if ($indexedServiceId > 0) {
                $service = $this->store->findEnabledService($indexedServiceId);

                if ($service !== null) {
                    $resolved = $this->private__fetchFromService($service, $jobUuid);

                    if ($resolved !== null) {
                        Cache::forever($cacheKey, $indexedServiceId);

                        return $resolved;
                    }
                }
            }
        }

        $cachedServiceId = (int) Cache::get($cacheKey, 0);

        if ($cachedServiceId > 0) {
            $service = $this->store->findEnabledService($cachedServiceId);

            if ($service !== null) {
                $resolved = $this->private__fetchFromService($service, $jobUuid);

                if ($resolved !== null) {
                    return $resolved;
                }
            }

            Cache::forget($cacheKey);
        }

        /** @var Collection<int, Service> $services */
        $services = $this->store->enabledServicesOrdered();

        foreach ($services as $service) {
            $resolved = $this->private__fetchFromService($service, $jobUuid);

            if ($resolved === null) {
                continue;
            }

            Cache::forever($cacheKey, (int) $service->id);

            return $resolved;
        }

        return null;
    }

    /**
     * Build the cache key to store the service ID that is hosting a job by its UUID.
     *
     * @param string $jobUuid The job UUID.
     */
    private function private__cacheKey(string $jobUuid): string
    {
        return "horizonhub:job-service:$jobUuid";
    }

    /**
     * Fetch the job data from the service.
     *
     * @param Service $service The service.
     * @param string $jobUuid The job UUID.
     *
     * @return array{service: Service, data: array<string, mixed>}|null
     */
    private function private__fetchFromService(Service $service, string $jobUuid): ?array
    {
        $response = $this->horizonApi->getJob($service, $jobUuid);

        if (! $response['success'] || empty($response['data'])) {
            return null;
        }

        return [
            'service' => $service,
            'data' => $response['data'],
        ];
    }
}
