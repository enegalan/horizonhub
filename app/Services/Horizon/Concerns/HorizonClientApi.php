<?php

namespace App\Services\Horizon\Concerns;

use App\Models\Service;
use App\Services\Horizon\Contracts\HorizonClientApi as HorizonClientApiContract;

class HorizonClientApi implements HorizonClientApiContract
{
    /**
     * The HTTP client.
     */
    private HorizonHttpClient $http;

    /**
     * The constructor.
     *
     * @param HorizonClientCache $cache The cache instance.
     */
    public function __construct(HorizonClientCache $cache)
    {
        $this->http = new HorizonHttpClient($cache);
    }

    /**
     * Get completed/processed jobs from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     * @param array $query The query parameters.
     *
     * @return array The response data.
     */
    public function getCompletedJobs(Service $service, array $query = []): array
    {
        $path = (string) config('horizonhub.horizon_paths.completed_jobs');

        return $this->http->call($service, "$path?" . \http_build_query($this->private__buildJobListQuery($query)), 'get');
    }

    /**
     * Get failed jobs from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     * @param array $query The query parameters.
     *
     * @return array The response data.
     */
    public function getFailedJobs(Service $service, array $query = []): array
    {
        $path = (string) config('horizonhub.horizon_paths.failed_jobs');

        return $this->http->call($service, "$path?" . \http_build_query($this->private__buildJobListQuery($query)), 'get');
    }

    /**
     * Get a single job by UUID from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     * @param string $jobUuid The job UUID.
     *
     * @return array The response data.
     */
    public function getJob(Service $service, string $jobUuid): array
    {
        $relativePath = \str_replace('{id}', $jobUuid, (string) config('horizonhub.horizon_paths.job'));

        return $this->http->call($service, $relativePath, 'get');
    }

    /**
     * Get Horizon masters (and their supervisors) from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     *
     * @return array The response data.
     */
    public function getMasters(Service $service): array
    {
        $relativePath = (string) config('horizonhub.horizon_paths.masters');

        return $this->http->call($service, $relativePath, 'get');
    }

    /**
     * Get pending/processing jobs from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     * @param array $query The query parameters.
     *
     * @return array The response data.
     */
    public function getPendingJobs(Service $service, array $query = []): array
    {
        $path = (string) config('horizonhub.horizon_paths.pending_jobs');

        return $this->http->call($service, "$path?" . \http_build_query($this->private__buildJobListQuery($query)), 'get');
    }

    /**
     * Get high-level dashboard statistics from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     *
     * @return array The response data.
     */
    public function getStats(Service $service): array
    {
        $relativePath = (string) config('horizonhub.horizon_paths.ping');

        return $this->http->call($service, $relativePath, 'get');
    }

    /**
     * Get the queue workload from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     *
     * @return array The response data.
     */
    public function getWorkload(Service $service): array
    {
        $relativePath = (string) config('horizonhub.horizon_paths.workload');

        $result = $this->http->call($service, $relativePath, 'get');

        // TO-EVALUATE: is this still needed?
        if (! $result['success'] && \in_array((int) ($result['status'] ?? 0), (array) config('horizonhub.horizon_http_auth_statuses'), true)) {
            $result = $this->http->call($service, $relativePath, 'get', withDashboardSession: true);
        }

        return $result;
    }

    /**
     * Test connectivity with the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     *
     * @return array The response data.
     */
    public function ping(Service $service): array
    {
        $relativePath = (string) config('horizonhub.horizon_paths.ping');

        return $this->http->call($service, $relativePath, 'get', allowWhenDisabled: true, bypassFailureCooldown: true);
    }

    /**
     * Retry a job through the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     * @param string $jobUuid The job UUID.
     *
     * @return array The response data.
     */
    public function retryJob(Service $service, string $jobUuid): array
    {
        $relativePath = \str_replace('{id}', $jobUuid, (string) config('horizonhub.horizon_paths.retry'));

        return $this->http->call($service, $relativePath, 'post', withDashboardSession: true);
    }

    /**
     * Build the job list query.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array{starting_at: int, limit: int}
     */
    private function private__buildJobListQuery(array $overrides = []): array
    {
        return \array_merge([
            'starting_at' => 0,
            'limit' => (int) config('horizonhub.horizon_api_job_list_page_size'),
        ], $overrides);
    }
}
