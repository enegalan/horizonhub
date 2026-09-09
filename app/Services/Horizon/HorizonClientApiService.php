<?php

namespace App\Services\Horizon;

use App\Models\Service;

class HorizonClientApiService
{
    /**
     * Get completed/processed jobs from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     * @param array $query The query parameters.
     *
     * @return array The response data.
     */
    public static function getCompletedJobs(Service $service, array $query = []): array
    {
        $relativePath = (string) config('horizonhub.horizon_paths.completed_jobs');

        return HorizonClientHttpService::call($service, "$relativePath?" . \http_build_query(self::private__buildJobListQuery($query)), 'get');
    }

    /**
     * Get failed jobs from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     * @param array $query The query parameters.
     *
     * @return array The response data.
     */
    public static function getFailedJobs(Service $service, array $query = []): array
    {
        $relativePath = (string) config('horizonhub.horizon_paths.failed_jobs');

        return HorizonClientHttpService::call($service, "$relativePath?" . \http_build_query(self::private__buildJobListQuery($query)), 'get');
    }

    /**
     * Get a single job by UUID from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     * @param string $jobUuid The job UUID.
     *
     * @return array The response data.
     */
    public static function getJob(Service $service, string $jobUuid): array
    {
        $relativePath = \str_replace('{id}', $jobUuid, (string) config('horizonhub.horizon_paths.job'));

        return HorizonClientHttpService::call($service, $relativePath, 'get');
    }

    /**
     * Get Horizon masters (and their supervisors) from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     *
     * @return array The response data.
     */
    public static function getMasters(Service $service): array
    {
        $relativePath = (string) config('horizonhub.horizon_paths.masters');

        return HorizonClientHttpService::call($service, $relativePath, 'get');
    }

    /**
     * Get pending/processing jobs from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     * @param array $query The query parameters.
     *
     * @return array The response data.
     */
    public static function getPendingJobs(Service $service, array $query = []): array
    {
        $relativePath = (string) config('horizonhub.horizon_paths.pending_jobs');

        return HorizonClientHttpService::call($service, "$relativePath?" . \http_build_query(self::private__buildJobListQuery($query)), 'get');
    }

    /**
     * Get high-level dashboard statistics from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     *
     * @return array The response data.
     */
    public static function getStats(Service $service): array
    {
        $relativePath = (string) config('horizonhub.horizon_paths.ping');

        return HorizonClientHttpService::call($service, $relativePath, 'get');
    }

    /**
     * Get the queue workload from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     *
     * @return array The response data.
     */
    public static function getWorkload(Service $service): array
    {
        $relativePath = (string) config('horizonhub.horizon_paths.workload');

        return HorizonClientHttpService::call($service, $relativePath, 'get');
    }

    /**
     * Test connectivity with the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     *
     * @return array The response data.
     */
    public static function ping(Service $service): array
    {
        $relativePath = (string) config('horizonhub.horizon_paths.ping');

        return HorizonClientHttpService::call($service, $relativePath, 'get', allowWhenDisabled: true, bypassFailureCooldown: true);
    }

    /**
     * Retry a job through the Horizon HTTP API.
     *
     * @param Service $service The service instance.
     * @param string $jobUuid The job UUID.
     *
     * @return array The response data.
     */
    public static function retryJob(Service $service, string $jobUuid): array
    {
        $relativePath = \str_replace('{id}', $jobUuid, (string) config('horizonhub.horizon_paths.retry'));

        return HorizonClientHttpService::call($service, $relativePath, 'post', withDashboardSession: true);
    }

    /**
     * Build the job list query.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array{starting_at: int, limit: int}
     */
    private static function private__buildJobListQuery(array $overrides = []): array
    {
        return \array_merge([
            'starting_at' => 0,
            'limit' => (int) config('horizonhub.horizon_api_job_list_page_size'),
        ], $overrides);
    }
}
