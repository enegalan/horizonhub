<?php

namespace App\Services\Jobs;

use App\Models\Service;
use App\Services\Horizon\HorizonClientService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class JobServiceResolver
{
    /**
     * The Horizon API client.
     */
    private HorizonClientService $horizonApi;

    /**
     * The constructor.
     *
     * @param HorizonClientService $horizonApi The horizon API client.
     */
    public function __construct(HorizonClientService $horizonApi)
    {
        $this->horizonApi = $horizonApi;
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

        $cachedServiceId = (int) Cache::get($cacheKey, 0);

        if ($cachedServiceId > 0) {
            $service = Service::query()
                ->enabled()
                ->find($cachedServiceId);

            if ($service !== null) {
                $resolved = $this->private__fetchFromService($service, $jobUuid);

                if ($resolved !== null) {
                    return $resolved;
                }
            }

            Cache::forget($cacheKey);
        }

        /** @var Collection<int, Service> $services */
        $services = Service::query()
            ->enabled()
            ->orderBy('name')
            ->get();

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
