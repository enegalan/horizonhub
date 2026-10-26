<?php

namespace App\Services\Horizon;

use App\Models\Service;
use App\Support\PathBuilder;

class HorizonClientApiService
{
    /**
     * Get one batch by ID from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     * @param string $batchId The batch ID.
     *
     * @return array The response data.
     */
    public static function getBatch(Service $service, string $batchId): array
    {
        return HorizonClientHttpService::call($service, PathBuilder::batch($batchId), 'get');
    }

    /**
     * Get recent batches from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     * @param array $query The query parameters.
     *
     * @return array The response data.
     */
    public static function getBatches(Service $service, array $query = []): array
    {
        return HorizonClientHttpService::call($service, PathBuilder::batches($query), 'get');
    }

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
        return HorizonClientHttpService::call($service, PathBuilder::completedJobs($query), 'get');
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
        return HorizonClientHttpService::call($service, PathBuilder::failedJobs($query), 'get');
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
        return HorizonClientHttpService::call($service, PathBuilder::job($jobUuid), 'get');
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
        return HorizonClientHttpService::call($service, PathBuilder::masters(), 'get');
    }

    /**
     * Get a single queue's metrics history from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     * @param string $queue The queue name as returned by the metrics API.
     *
     * @return array The response data.
     */
    public static function getMetricsQueue(Service $service, string $queue): array
    {
        return HorizonClientHttpService::call($service, PathBuilder::metricsQueue($queue), 'get');
    }

    /**
     * Get the queue names with metrics history from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     *
     * @return array The response data.
     */
    public static function getMetricsQueues(Service $service): array
    {
        return HorizonClientHttpService::call($service, PathBuilder::metricsQueues(), 'get');
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
        return HorizonClientHttpService::call($service, PathBuilder::pendingJobs($query), 'get');
    }

    /**
     * Get silenced jobs from the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     *
     * @return array The response data.
     */
    public static function getSilencedJobs(Service $service): array
    {
        return HorizonClientHttpService::call($service, PathBuilder::silencedJobs(), 'get');
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
        return HorizonClientHttpService::call($service, PathBuilder::ping(), 'get');
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
        return HorizonClientHttpService::call($service, PathBuilder::workload(), 'get');
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
        return HorizonClientHttpService::call($service, PathBuilder::ping(), 'get', allowWhenDisabled: true, bypassFailureCooldown: true);
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
        return HorizonClientHttpService::call($service, PathBuilder::retryJob($jobUuid), 'post', withDashboardSession: true);
    }
}
