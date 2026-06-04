<?php

namespace App\Services\Horizon;

use App\Models\Service;
use App\Services\Horizon\Contracts\HorizonClientApi;

class MockHorizonClientService implements HorizonClientApi
{
    /**
     * The fixtures.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $fixtures;

    /**
     * The constructor.
     */
    public function __construct()
    {
        $this->fixtures = config('demo.horizon');
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
        return $this->private__paginatedJobList($service, 'completed_jobs', $query);
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
        return $this->private__paginatedJobList($service, 'failed_jobs', $query);
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
        $job = $this->private__findJobDetail((int) $service->id, $jobUuid);

        if ($job === null) {
            return [
                'success' => false,
                'message' => 'Job not found in demo catalog.',
                'status' => 404,
            ];
        }

        return [
            'success' => true,
            'data' => $job,
        ];
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
        return $this->private__success($service, 'masters');
    }

    /**
     * Get pending jobs from the Horizon HTTP API for a service.
     *
     * @param Service $service The service.
     * @param array<string, mixed> $query
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getPendingJobs(Service $service, array $query = []): array
    {
        return $this->private__paginatedJobList($service, 'pending_jobs', $query);
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
        return $this->private__success($service, 'stats');
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
        return $this->private__success($service, 'workload');
    }

    /**
     * Test connectivity with the Horizon HTTP API for a service.
     *
     * @param Service $service The service.
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function ping(Service $service): array
    {
        return ['success' => true, 'data' => ['status' => 'ok']];
    }

    /**
     * Reset the failure cooldown for a service.
     *
     * @param Service $service The service.
     */
    public function resetFailureCooldown(Service $service): void {}

    /**
     * Retry a job through the Horizon HTTP API for a service.
     *
     * @param Service $service The service.
     * @param string $jobUuid The job UUID.
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function retryJob(Service $service, string $jobUuid): array
    {
        return ['success' => true];
    }

    /**
     * Get a success response.
     *
     * @param Service $service The service.
     * @param string $key The key.
     *
     * @return array{success: bool, data?: mixed, message?: string, status?: int}
     */
    private function private__success(Service $service, string $key): array
    {
        $serviceId = (int) $service->id;
        $data = $this->fixtures[$serviceId][$key] ?? null;

        if ($data === null) {
            return [
                'success' => false,
                'message' => 'No demo Horizon fixture for this service.',
                'status' => 404,
            ];
        }

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    /**
     * Get a paginated job list response.
     *
     * @param Service $service The service.
     * @param string $key The key.
     * @param array<string, mixed> $query
     *
     * @return array{success: bool, data?: array<string, mixed>, message?: string, status?: int}
     */
    private function private__paginatedJobList(Service $service, string $key, array $query): array
    {
        $response = $this->private__success($service, $key);

        if (! $response['success']) {
            return $response;
        }

        $jobs = $response['data']['jobs'] ?? [];

        if (! \is_array($jobs)) {
            $jobs = [];
        }

        return [
            'success' => true,
            'data' => ['jobs' => $this->private__sliceJobBatch($jobs, $query)],
        ];
    }

    /**
     * Slice a job batch.
     *
     * @param list<mixed> $jobs
     * @param array<string, mixed> $query
     *
     * @return list<mixed>
     */
    private function private__sliceJobBatch(array $jobs, array $query): array
    {
        if ($jobs === []) {
            return [];
        }

        $limit = (int) ($query['limit'] ?? config('horizonhub.horizon_api_job_list_page_size'));
        $limit = \max(1, $limit);
        $startingAt = (int) ($query['starting_at'] ?? -1);

        $normalized = [];

        foreach ($jobs as $job) {
            if (! \is_array($job)) {
                continue;
            }

            $normalized[] = $job;
        }

        \usort($normalized, static function (array $a, array $b): int {
            return ((int) ($b['index'] ?? 0)) <=> ((int) ($a['index'] ?? 0));
        });

        if ($startingAt >= 0) {
            $normalized = \array_values(\array_filter(
                $normalized,
                static fn (array $job): bool => (int) ($job['index'] ?? 0) < $startingAt,
            ));
        }

        return \array_slice($normalized, 0, $limit);
    }

    /**
     * Find a job detail by UUID.
     *
     * @param int $serviceId The service ID.
     * @param string $jobUuid The job UUID.
     *
     * @return array<string, mixed>|null
     */
    private function private__findJobDetail(int $serviceId, string $jobUuid): ?array
    {
        $fixture = $this->fixtures[$serviceId] ?? [];
        $detailJobs = $fixture['jobs'] ?? [];

        if (isset($detailJobs[$jobUuid]) && \is_array($detailJobs[$jobUuid])) {
            return $detailJobs[$jobUuid];
        }

        foreach (['pending_jobs' => 'pending', 'completed_jobs' => 'completed', 'failed_jobs' => 'failed'] as $listKey => $status) {
            $jobs = $fixture[$listKey]['jobs'] ?? [];

            if (! \is_array($jobs)) {
                continue;
            }

            foreach ($jobs as $job) {
                if (! \is_array($job) || (string) ($job['id'] ?? '') !== $jobUuid) {
                    continue;
                }

                return $this->private__jobDetailFromListRow($job, $status, $serviceId);
            }
        }

        return null;
    }

    /**
     * Get a job detail from a list row.
     *
     * @param array<string, mixed> $job The job.
     * @param string $status The status.
     * @param int $serviceId The service ID.
     *
     * @param array<string, mixed> $job
     *
     * @return array<string, mixed>
     */
    private function private__jobDetailFromListRow(array $job, string $status, int $serviceId): array
    {
        $reservedAt = isset($job['reserved_at']) && \is_numeric($job['reserved_at']) ? (int) $job['reserved_at'] : null;

        return [
            'id' => (string) ($job['id'] ?? ''),
            'name' => (string) ($job['name'] ?? ''),
            'queue' => (string) ($job['queue'] ?? 'default'),
            'status' => $status,
            'connection' => 'redis',
            'attempts' => 1,
            'payload' => [
                'pushedAt' => $reservedAt ?? now()->getTimestamp(),
                'attempts' => 1,
            ],
            'reserved_at' => $reservedAt,
            'completed_at' => isset($job['completed_at']) && \is_numeric($job['completed_at']) ? (int) $job['completed_at'] : null,
            'failed_at' => isset($job['failed_at']) && \is_numeric($job['failed_at']) ? (int) $job['failed_at'] : null,
            'exception' => $status === 'failed'
                ? "RuntimeException: Mock failure on service {$serviceId}\n  at mock fixture:42"
                : null,
            'tags' => ['demo'],
        ];
    }
}
