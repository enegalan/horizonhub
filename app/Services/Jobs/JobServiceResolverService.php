<?php

namespace App\Services\Jobs;

use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class JobServiceResolverService
{
    /**
     * Resolve the service that is hosting a job by its UUID, caching the result for future lookups.
     *
     * @return array{service: Service, data: array<string, mixed>}|null
     */
    public static function resolve(string $jobUuid): ?array
    {
        $jobUuid = \trim($jobUuid);

        if (blank($jobUuid)) {
            return null;
        }

        $cacheKey = self::private__cacheKey($jobUuid);

        $cachedServiceId = (int) Cache::get($cacheKey, 0);

        if ($cachedServiceId > 0) {
            $service = Service::enabled()->find($cachedServiceId);

            if ($service !== null) {
                $resolved = self::private__fetchFromService($service, $jobUuid);

                if ($resolved !== null) {
                    return $resolved;
                }
            }

            Cache::forget($cacheKey);
        }

        /** @var Collection<int, Service> $services */
        $services = Service::enabled()
            ->orderBy('name')
            ->get();

        foreach ($services as $service) {
            $resolved = self::private__fetchFromService($service, $jobUuid);

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
    private static function private__cacheKey(string $jobUuid): string
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
    private static function private__fetchFromService(Service $service, string $jobUuid): ?array
    {
        $response = HorizonClientApiService::getJob($service, $jobUuid);

        if (! $response['success'] || empty($response['data'])) {
            return null;
        }

        return [
            'service' => $service,
            'data' => $response['data'],
        ];
    }
}
