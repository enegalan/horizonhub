<?php

namespace App\Services\Horizon\Contracts;

use App\Models\Service;

interface HorizonClientApi
{
    /**
     * Get completed/processed jobs from the Horizon HTTP API for a service.
     */
    public function getCompletedJobs(Service $service, array $query = []): array;

    /**
     * Get failed jobs from the Horizon HTTP API for a service.
     */
    public function getFailedJobs(Service $service, array $query = []): array;

    /**
     * Get a single job by UUID from the Horizon HTTP API for a service.
     */
    public function getJob(Service $service, string $jobUuid): array;

    /**
     * Get Horizon masters (and their supervisors) from the Horizon HTTP API for a service.
     */
    public function getMasters(Service $service): array;

    /**
     * Get pending/processing jobs from the Horizon HTTP API for a service.
     */
    public function getPendingJobs(Service $service, array $query = []): array;

    /**
     * Get high-level dashboard statistics from the Horizon HTTP API for a service.
     */
    public function getStats(Service $service): array;

    /**
     * Get the queue workload from the Horizon HTTP API for a service.
     */
    public function getWorkload(Service $service): array;

    /**
     * Test connectivity with the Horizon HTTP API for a service.
     */
    public function ping(Service $service): array;

    /**
     * Retry a job through the Horizon HTTP API for a service.
     */
    public function retryJob(Service $service, string $jobUuid): array;
}
