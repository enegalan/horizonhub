<?php

namespace App\Services;

use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;

class HorizonMetricsService {
    private HorizonApiProxyService $horizonApi;

    public function __construct(HorizonApiProxyService $horizonApi) {
        $this->horizonApi = $horizonApi;
    }

    /**
     * Get the number of jobs processed in the past minute.
     *
     * @param Service|null $service
     * @return int
     */
    public function getJobsPastMinute(?Service $service = null): int {
        if ($service !== null && $service->base_url) {
            $response = $this->horizonApi->getStats($service);
            $data = $response['data'] ?? null;

            if (($response['success'] ?? false) && \is_array($data) && isset($data['jobsPerMinute'])) {
                return (int) \round((float) $data['jobsPerMinute']);
            }
        }

        return HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', \now()->subMinute())
            ->when($service !== null, static fn ($q) => $q->where('service_id', $service->id))
            ->count();
    }

    /**
     * Get the number of jobs processed in the past hour.
     *
     * @param Service|null $service
     * @return int
     */
    public function getJobsPastHour(?Service $service = null): int {
        if ($service !== null && $service->base_url) {
            $response = $this->horizonApi->getStats($service);
            $data = $response['data'] ?? null;

            if (($response['success'] ?? false) && \is_array($data) && isset($data['recentJobs'])) {
                return (int) $data['recentJobs'];
            }
        }

        return HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', \now()->subHour())
            ->when($service !== null, static fn ($q) => $q->where('service_id', $service->id))
            ->count();
    }

    /**
     * Get the number of failed jobs in the past seven days.
     *
     * @param Service|null $service
     * @return int
     */
    public function getFailedPastSevenDays(?Service $service = null): int {
        if ($service !== null && $service->base_url) {
            $response = $this->horizonApi->getStats($service);
            $data = $response['data'] ?? null;

            if (($response['success'] ?? false) && \is_array($data) && isset($data['failedJobs'])) {
                return (int) $data['failedJobs'];
            }
        }

        return HorizonFailedJob::where('failed_at', '>=', \now()->subDays(7))
            ->when($service !== null, static fn ($q) => $q->where('service_id', $service->id))
            ->count();
    }

    /**
     * Get the number of processed jobs in the past 24 hours.
     *
     * @param Service|null $service
     * @return int
     */
    public function getProcessedPast24Hours(?Service $service = null): int {
        // Laravel Horizon API does not expose this metric, so we need to calculate it locally.
        return HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', \now()->subDay())
            ->when($service !== null, static fn ($q) => $q->where('service_id', $service->id))
            ->count();
    }

    /**
     * Get the current workload rows for a single service.
     *
     * @param Service $service
     * @return array<int, array{queue: string, jobs: int, processes: int|null, wait: float|null}>
     */
    public function getWorkloadForService(Service $service): array {
        if (! $service->base_url) {
            return [];
        }

        $response = $this->horizonApi->getWorkload($service);
        $data = $response['data'] ?? null;

        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return [];
        }

        $rows = [];

        foreach ($data as $row) {
            if (! \is_array($row)) {
                continue;
            }

            $queueName = '';
            if (isset($row['name']) && (string) $row['name'] !== '') {
                $queueName = (string) $row['name'];
            }

            if ($queueName === '') {
                continue;
            }

            $jobs = 0;
            if (isset($row['length']) && \is_numeric($row['length'])) {
                $jobs = (int) $row['length'];
            }

            $processes = null;
            if (isset($row['processes']) && \is_numeric($row['processes'])) {
                $processes = (int) $row['processes'];
            }

            $wait = null;
            if (isset($row['wait']) && \is_numeric($row['wait'])) {
                $wait = (float) $row['wait'];
            }

            $rows[] = [
                'queue' => $queueName,
                'jobs' => $jobs,
                'processes' => $processes,
                'wait' => $wait,
            ];
        }

        \usort($rows, static fn (array $a, array $b): int => \strcmp($a['queue'], $b['queue']));

        return $rows;
    }
}
