<?php

namespace App\Services\Horizon;

use App\Models\Service;
use App\Services\Horizon\Concerns\HorizonClientApi;
use App\Services\Horizon\Concerns\HorizonClientCache;
use App\Services\Horizon\Contracts\HorizonClientApi as HorizonClientApiContract;

class HorizonClientService implements HorizonClientApiContract
{
    /**
     * The Horizon client API.
     */
    private HorizonClientApi $api;

    /**
     * The cache for Horizon API calls.
     */
    private HorizonClientCache $cache;

    /**
     * The constructor.
     *
     */
    public function __construct()
    {
        $this->cache = new HorizonClientCache;
        $this->api = new HorizonClientApi($this->cache);
    }

    /**
     * Get completed jobs from the Horizon HTTP API for a service.
     *
     * @param Service $service The service.
     * @param array<string, mixed> $query
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getCompletedJobs(Service $service, array $query = []): array
    {
        return $this->api->getCompletedJobs($service, $query);
    }

    /**
     * Get failed jobs from the Horizon HTTP API for a service.
     *
     * @param Service $service The service.
     * @param array<string, mixed> $query
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getFailedJobs(Service $service, array $query = []): array
    {
        return $this->api->getFailedJobs($service, $query);
    }

    /**
     * Get a single job by UUID from the Horizon HTTP API for a service.
     *
     * @param Service $service The service.
     * @param string $jobUuid The job UUID.
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getJob(Service $service, string $jobUuid): array
    {
        return $this->api->getJob($service, $jobUuid);
    }

    /**
     * Get Horizon masters (and their supervisors) from the Horizon HTTP API for a service.
     *
     * @param Service $service The service.
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getMasters(Service $service): array
    {
        return $this->api->getMasters($service);
    }

    /**
     * Get pending/processing jobs from the Horizon HTTP API for a service.
     *
     * @param Service $service The service.
     * @param array<string, mixed> $query
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getPendingJobs(Service $service, array $query = []): array
    {
        return $this->api->getPendingJobs($service, $query);
    }

    /**
     * Get high-level dashboard statistics from the Horizon HTTP API for a service.
     *
     * @param Service $service The service.
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getStats(Service $service): array
    {
        return $this->api->getStats($service);
    }

    /**
     * Get the queue workload from the Horizon HTTP API for a service.
     *
     * @param Service $service The service.
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getWorkload(Service $service): array
    {
        return $this->api->getWorkload($service);
    }

    /**
     * Test connectivity with the Horizon HTTP API for a service.
     *
     * @param Service $service The service.
     *
     * @return array{success: bool, message?: string, status?: int}
     */
    public function ping(Service $service): array
    {
        return $this->api->ping($service);
    }

    /**
     * Reset the failure cooldown for a service.
     *
     * @param Service $service The service.
     */
    public function resetFailureCooldown(Service $service): void
    {
        $this->cache->forgetFailureCooldown($service);
    }

    /**
     * Retry a job through the Horizon HTTP API.
     *
     * @param Service $service The service.
     * @param string $jobUuid The job UUID.
     *
     * @return array{success: bool, message?: string, status?: int}
     */
    public function retryJob(Service $service, string $jobUuid): array
    {
        return $this->api->retryJob($service, $jobUuid);
    }
}
