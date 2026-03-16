<?php

namespace App\Services;

use App\Models\Service;
use Carbon\Carbon;

class HorizonMetricsService {

    /**
     * The number of hours in a day.
     *
     * @var int
     */
    private const HOURS_24 = 24;

    /**
     * The number of days in a week.
     *
     * @var int
     */
    private const DAYS_7 = 7;

    /**
     * The number of top queues to return.
     *
     * @var int
     */
    private const TOP_N_QUEUES = 12;

    /**
     * The Horizon API proxy service.
     *
     * @var HorizonApiProxyService
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * Construct the Horizon metrics service.
     *
     * @param HorizonApiProxyService $horizonApi
     */
    public function __construct(HorizonApiProxyService $horizonApi) {
        $this->horizonApi = $horizonApi;
    }

    /**
     * Extract the list of queue rows from a workload API payload.
     * Handles top-level array [ {...}, {...} ] or wrapped {"data": [...]} / {"workload": [...]}.
     *
     * @param mixed $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractWorkloadQueueList(mixed $payload): array {
        if (! \is_array($payload)) {
            return [];
        }
        if (isset($payload['data']) && \is_array($payload['data'])) {
            return $payload['data'];
        }
        if (isset($payload['workload']) && \is_array($payload['workload'])) {
            return $payload['workload'];
        }
        if (isset($payload[0]) && \is_array($payload[0])) {
            return $payload;
        }
        return [];
    }

    /**
     * Extract job count from a workload queue row. Accepts length, size, pending, jobs.
     *
     * @param array<string, mixed> $row
     * @return int
     */
    private function extractQueueJobCount(array $row): int {
        $keys = ['length', 'size', 'pending', 'waiting', 'jobs', 'count'];
        foreach ($keys as $key) {
            if (isset($row[$key]) && \is_numeric($row[$key])) {
                return (int) $row[$key];
            }
        }
        return 0;
    }

    /**
     * Get job count per queue from Horizon metrics/queues API when available.
     *
     * @param Service $service
     * @return array<string, int> queue name (normalized) => count
     */
    private function getMetricsQueueCounts(Service $service): array {
        $response = $this->horizonApi->getMetricsQueues($service);
        $payload = $response['data'] ?? null;
        if (! ($response['success'] ?? false) || $payload === null || ! \is_array($payload)) {
            return [];
        }

        $list = isset($payload[0]) && \is_array($payload[0])
            ? $payload
            : (isset($payload['queues']) && \is_array($payload['queues']) ? $payload['queues'] : []);

        $byQueue = [];
        foreach ($list as $item) {
            if (! \is_array($item)) {
                continue;
            }
            $name = isset($item['name']) ? (string) $item['name'] : '';
            if ($name === '') {
                continue;
            }
            $norm = $this->normalizeQueueName($name);
            if ($norm === null) {
                $norm = $name;
            }
            $count = $this->extractQueueJobCount($item);
            if ($count > 0) {
                $byQueue[$norm] = ($byQueue[$norm] ?? 0) + $count;
            }
        }
        return $byQueue;
    }

    /**
     * Get pending job count per queue for a service via the Horizon pending-jobs API.
     * Used as fallback when workload does not return queue length.
     *
     * @param Service $service
     * @return array<string, int> queue name (normalized) => count
     */
    private function getPendingCountByQueue(Service $service): array {
        $limit = (int) \config('horizonhub.workload_pending_limit', 5000);
        $response = $this->horizonApi->getPendingJobs($service, [
            'starting_at' => -1,
            'limit' => $limit,
        ]);
        $data = $response['data'] ?? null;
        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return [];
        }

        $jobs = $data['jobs'] ?? [];
        if (! \is_array($jobs)) {
            return [];
        }

        $byQueue = [];
        foreach ($jobs as $job) {
            if (! \is_array($job)) {
                continue;
            }
            $raw = isset($job['queue']) ? (string) $job['queue'] : '';
            $queue = $this->normalizeQueueName($raw);
            if ($queue === null) {
                $queue = $raw;
            }
            if ($queue !== '') {
                $byQueue[$queue] = ($byQueue[$queue] ?? 0) + 1;
            }
        }
        return $byQueue;
    }

    /**
     * Normalize the queue name.
     *
     * @param string|null $queue
     * @return string|null
     */
    private function normalizeQueueName(?string $queue): ?string {
        if ($queue === null || $queue === '') {
            return $queue;
        }

        if (\str_starts_with($queue, 'redis.')) {
            $suffix = \substr($queue, \strlen('redis.'));

            return $suffix !== '' ? $suffix : $queue;
        }

        return $queue;
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

        return 0;
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

            return 0;
        }

        /** @var \Illuminate\Support\Collection<int, Service> $services */
        $services = Service::query()
            ->whereNotNull('base_url')
            ->get();

        if ($services->isEmpty()) {
            return 0;
        }

        $total = 0;

        foreach ($services as $svc) {
            if (! $svc->base_url) {
                continue;
            }

            $response = $this->horizonApi->getStats($svc);
            $data = $response['data'] ?? null;

            if (! (($response['success'] ?? false) && \is_array($data) && isset($data['recentJobs']))) {
                continue;
            }

            $total += (int) $data['recentJobs'];
        }

        return $total;
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

            return 0;
        }

        /** @var \Illuminate\Support\Collection<int, Service> $services */
        $services = Service::query()
            ->whereNotNull('base_url')
            ->get();

        if ($services->isEmpty()) {
            return 0;
        }

        $total = 0;

        foreach ($services as $svc) {
            if (! $svc->base_url) {
                continue;
            }

            $response = $this->horizonApi->getStats($svc);
            $data = $response['data'] ?? null;

            if (! (($response['success'] ?? false) && \is_array($data) && isset($data['failedJobs']))) {
                continue;
            }

            $total += (int) $data['failedJobs'];
        }

        return $total;
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
        $payload = $response['data'] ?? null;

        if (! ($response['success'] ?? false) || $payload === null) {
            return [];
        }

        $data = $this->extractWorkloadQueueList($payload);

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

            $jobs = $this->extractQueueJobCount($row);

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

    /**
     * Get supervisors aggregated across services (optionally filtered by service).
     *
     * @param int|null $service_id
     * @return array<int, array{
     *     service_id: int,
     *     service: string,
     *     name: string,
     *     status: string,
     *     jobs: int,
     *     processes: int|null
     * }>
     */
    public function getSupervisorsData(?int $service_id = null): array {
        $servicesQuery = Service::query()->whereNotNull('base_url');

        if ($service_id !== null) {
            $servicesQuery->where('id', $service_id);
        }

        $services = $servicesQuery->get();
        if ($services->isEmpty()) {
            return [];
        }

        $result = [];

        /** @var Service $service */
        foreach ($services as $service) {
            $workloadRows = $this->getWorkloadForService($service);
            $jobsByQueue = [];
            foreach ($workloadRows as $wr) {
                $jobsByQueue[$wr['queue']] = ($jobsByQueue[$wr['queue']] ?? 0) + $wr['jobs'];
            }

            $mastersResponse = $this->horizonApi->getMasters($service);
            $mastersData = $mastersResponse['data'] ?? null;

            if (! ($mastersResponse['success'] ?? false) || ! \is_array($mastersData)) {
                continue;
            }

            foreach ($mastersData as $master) {
                if (! \is_array($master)) {
                    continue;
                }

                $supervisors = $master['supervisors'] ?? null;
                if (! \is_array($supervisors)) {
                    continue;
                }

                foreach ($supervisors as $supervisor) {
                    if (! \is_array($supervisor)) {
                        continue;
                    }

                    $name = isset($supervisor['name']) ? (string) $supervisor['name'] : '';
                    if ($name === '') {
                        continue;
                    }

                    $processes = null;
                    if (isset($supervisor['processes']) && \is_array($supervisor['processes'])) {
                        $sum = 0;
                        foreach ($supervisor['processes'] as $value) {
                            if (\is_numeric($value)) {
                                $sum += (int) $value;
                            }
                        }
                        $processes = $sum;
                    }

                    $options = isset($supervisor['options']) && \is_array($supervisor['options']) ? $supervisor['options'] : [];
                    $queues = $options['queue'] ?? null;
                    if (! \is_array($queues)) {
                        $queues = $queues !== null && $queues !== '' ? [(string) $queues] : [];
                    } else {
                        $queues = \array_map('strval', $queues);
                    }
                    $jobs = 0;
                    foreach ($queues as $q) {
                        $jobs += $jobsByQueue[$q] ?? 0;
                    }

                    $result[] = [
                        'service_id' => (int) $service->id,
                        'service' => (string) $service->name,
                        'name' => $name,
                        'status' => $service->status,
                        'jobs' => $jobs,
                        'processes' => $processes,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Get current workload aggregated across services (optionally filtered by service).
     *
     * @param int|null $service_id
     * @return array<int, array{
     *     service_id: int,
     *     service: string,
     *     queue: string,
     *     jobs: int,
     *     processes: int|null,
     *     wait: float|null
     * }>
     */
    public function getWorkloadData(?int $service_id = null): array {
        $servicesQuery = Service::query()->whereNotNull('base_url');

        if ($service_id !== null) {
            $servicesQuery->where('id', $service_id);
        }

        $services = $servicesQuery->orderBy('name')->get();
        if ($services->isEmpty()) {
            return [];
        }

        $result = [];

        /** @var Service $service */
        foreach ($services as $service) {
            $rows = $this->getWorkloadForService($service);
            if ($rows === []) {
                continue;
            }

            foreach ($rows as $row) {
                $result[] = [
                    'service_id' => (int) $service->id,
                    'service' => (string) $service->name,
                    'queue' => $row['queue'],
                    'jobs' => $row['jobs'],
                    'processes' => $row['processes'],
                    'wait' => $row['wait'],
                ];
            }
        }

        return $result;
    }

    /**
     * Get the failure rate for the past 24 hours.
     *
     * @param int|null $service_id
     * @return array{rate: float, processed: int, failed: int}
     */
    public function getFailureRate24h(?int $service_id = null): array {
        $since = \now()->subDay();
        $sinceTimestamp = $since->getTimestamp();

        $servicesQuery = Service::query()->whereNotNull('base_url');
        if ($service_id !== null) {
            $servicesQuery->where('id', $service_id);
        }

        $services = $servicesQuery->get();
        if ($services->isEmpty()) {
            return [
                'rate' => 0.0,
                'processed' => 0,
                'failed' => 0,
            ];
        }

        $processed = 0;
        $failed = 0;

        /** @var Service $service */
        foreach ($services as $service) {
            $completedResponse = $this->horizonApi->getCompletedJobs($service, [
                'starting_at' => 0
            ]);
            $completedData = $completedResponse['data'] ?? null;

            if ($completedResponse['success'] ?? false) {
                $jobsPayload = $completedData['jobs'] ?? [];

                foreach ($jobsPayload as $job) {
                    if (! \is_array($job)) {
                        continue;
                    }

                    $completedAt = $job['completed_at'] ?? null;
                    if (! \is_numeric($completedAt)) {
                        continue;
                    }

                    if ((int) $completedAt >= $sinceTimestamp) {
                        $processed++;
                    }
                }
            }

            $failedResponse = $this->horizonApi->getFailedJobs($service, [
                'starting_at' => 0
            ]);
            $failedData = $failedResponse['data'] ?? null;

            if ($failedResponse['success'] ?? false) {
                $jobsPayload = $failedData['jobs'] ?? [];

                foreach ($jobsPayload as $job) {
                    if (! \is_array($job)) {
                        continue;
                    }

                    $failedAt = $job['failed_at'] ?? null;
                    if (! \is_numeric($failedAt)) {
                        continue;
                    }

                    if ((int) $failedAt >= $sinceTimestamp) {
                        $failed++;
                    }
                }
            }
        }

        $total = $processed + $failed;
        $rate = $total > 0 ? \round(100 * $failed / $total, 1) : 0.0;

        return [
            'rate' => $rate,
            'processed' => $processed,
            'failed' => $failed,
        ];
    }

    /**
     * Get the failure rate over time for the past 24 hours.
     *
     * @param int|null $service_id
     * @return array{xAxis: list<string>, rate: list<float|null>}
     */
    public function getFailureRateOverTime(?int $service_id = null): array {
        $since = \now()->subHours(self::HOURS_24);
        $sinceTimestamp = $since->getTimestamp();
        $bucketFormat = 'Y-m-d H:00';

        $buckets = [];
        for ($i = 0; $i < self::HOURS_24; $i++) {
            $key = \now()->subHours(self::HOURS_24 - 1 - $i)->format($bucketFormat);
            $buckets[$key] = ['processed' => 0, 'failed' => 0];
        }

        $servicesQuery = Service::query()->whereNotNull('base_url');
        if ($service_id !== null) {
            $servicesQuery->where('id', $service_id);
        }

        $services = $servicesQuery->get();
        if ($services->isEmpty()) {
            return ['xAxis' => [], 'rate' => []];
        }

        /** @var Service $service */
        foreach ($services as $service) {
            $completedResponse = $this->horizonApi->getCompletedJobs($service, [
                'starting_at' => 0
            ]);
            $completedData = $completedResponse['data'] ?? null;

            if ($completedResponse['success'] ?? false) {
                $jobsPayload = $completedData['jobs'] ?? [];

                foreach ($jobsPayload as $job) {
                    if (! \is_array($job)) {
                        continue;
                    }

                    $completedAt = $job['completed_at'] ?? $job['processed_at'] ?? null;
                    if (! \is_numeric($completedAt)) {
                        continue;
                    }

                    $ts = (int) $completedAt;
                    if ($ts < $sinceTimestamp) {
                        continue;
                    }

                    $bucket = Carbon::createFromTimestamp($ts)->format($bucketFormat);
                    if (! isset($buckets[$bucket])) {
                        continue;
                    }

                    $buckets[$bucket]['processed']++;
                }
            }

            $failedResponse = $this->horizonApi->getFailedJobs($service, [
                'starting_at' => 0
            ]);
            $failedData = $failedResponse['data'] ?? null;

            if ($failedResponse['success'] ?? false) {
                $jobsPayload =  $failedData['jobs'] ?? [];

                foreach ($jobsPayload as $job) {
                    if (! \is_array($job)) {
                        continue;
                    }

                    $failedAt = $job['failed_at'] ?? null;
                    if (! \is_numeric($failedAt)) {
                        continue;
                    }

                    $ts = (int) $failedAt;
                    if ($ts < $sinceTimestamp) {
                        continue;
                    }

                    $bucket = Carbon::createFromTimestamp($ts)->format($bucketFormat);
                    if (! isset($buckets[$bucket])) {
                        continue;
                    }

                    $buckets[$bucket]['failed']++;
                }
            }
        }

        $xAxis = [];
        $series = [];

        foreach ($buckets as $k => $v) {
            $xAxis[] = Carbon::parse($k)->format('H:i');
            $total = $v['processed'] + $v['failed'];
            if ($total > 0) {
                $series[] = \round(100 * $v['failed'] / $total, 1);
            } else {
                $series[] = null;
            }
        }

        return ['xAxis' => $xAxis, 'rate' => $series];
    }

    /**
     * Get the average runtime over time.
     *
     * @param int|null $service_id
     * @return array{xAxis: list<string>, avgSeconds: list<float|null>}
     */
    public function getAvgRuntimeOverTime(?int $service_id = null): array {
        $since = \now()->subHours(self::HOURS_24);
        $sinceTimestamp = $since->getTimestamp();
        $bucketFormat = 'Y-m-d H:00';

        $buckets = [];
        for ($i = 0; $i < self::HOURS_24; $i++) {
            $key = \now()->subHours(self::HOURS_24 - 1 - $i)->format($bucketFormat);
            $buckets[$key] = ['sum' => 0.0, 'count' => 0];
        }

        $servicesQuery = Service::query()->whereNotNull('base_url');
        if ($service_id !== null) {
            $servicesQuery->where('id', $service_id);
        }

        $services = $servicesQuery->get();
        if ($services->isEmpty()) {
            return ['xAxis' => [], 'avgSeconds' => []];
        }

        /** @var Service $service */
        foreach ($services as $service) {
            $completedResponse = $this->horizonApi->getCompletedJobs($service, [
                'starting_at' => 0
            ]);
            $completedData = $completedResponse['data'] ?? null;

            if ($completedResponse['success'] ?? false) {
                $jobsPayload = $completedData['jobs'] ?? [];

                foreach ($jobsPayload as $job) {
                    if (! \is_array($job)) {
                        continue;
                    }

                    $queuedAt = $job['reserved_at'] ?? null;
                    $completedAt = $job['completed_at'] ?? null;

                    if (! \is_numeric($queuedAt) || ! \is_numeric($completedAt)) {
                        continue;
                    }

                    $start = (int) $queuedAt;
                    $end = (int) $completedAt;

                    if ($end < $sinceTimestamp || $end <= $start) {
                        continue;
                    }

                    $runtime = (float) ($end - $start);

                    $bucket = Carbon::createFromTimestamp($end)->format($bucketFormat);
                    if (! isset($buckets[$bucket])) {
                        continue;
                    }

                    $buckets[$bucket]['sum'] += $runtime;
                    $buckets[$bucket]['count']++;
                }
            }

            $failedResponse = $this->horizonApi->getFailedJobs($service, [
                'starting_at' => 0
            ]);
            $failedData = $failedResponse['data'] ?? null;

            if ($failedResponse['success'] ?? false) {
                $jobsPayload = $failedData['jobs'] ?? [];

                foreach ($jobsPayload as $job) {
                    if (! \is_array($job)) {
                        continue;
                    }

                    $queuedAt = $job['reserved_at'] ?? null;
                    $failedAt = $job['failed_at'] ?? null;

                    if (! \is_numeric($queuedAt) || ! \is_numeric($failedAt)) {
                        continue;
                    }

                    $start = (int) $queuedAt;
                    $end = (int) $failedAt;

                    if ($end < $sinceTimestamp || $end <= $start) {
                        continue;
                    }

                    $runtime = (float) ($end - $start);

                    $bucket = Carbon::createFromTimestamp($end)->format($bucketFormat);
                    if (! isset($buckets[$bucket])) {
                        continue;
                    }

                    $buckets[$bucket]['sum'] += $runtime;
                    $buckets[$bucket]['count']++;
                }
            }
        }

        $xAxis = [];
        $series = [];

        foreach ($buckets as $k => $v) {
            $xAxis[] = Carbon::parse($k)->format('H:i');
            $series[] = $v['count'] > 0 ? \round($v['sum'] / $v['count'], 2) : null;
        }

        return ['xAxis' => $xAxis, 'avgSeconds' => $series];
    }

    /**
     * Get the processed vs failed data by queue.
     *
     * @param int|null $service_id
     * @return array{queues: list<string>, processed: list<int>, failed: list<int>}
     */
    public function getProcessedFailedByQueue(?int $service_id = null): array {
        $since = \now()->subDays(self::DAYS_7);
        $sinceTimestamp = $since->getTimestamp();

        $servicesQuery = Service::query()->whereNotNull('base_url');
        if ($service_id !== null) {
            $servicesQuery->where('id', $service_id);
        }

        $services = $servicesQuery->get();
        if ($services->isEmpty()) {
            return ['queues' => [], 'processed' => [], 'failed' => []];
        }

        $normalizedProcessed = [];
        $normalizedFailed = [];

        /** @var Service $service */
        foreach ($services as $service) {
            $completedResponse = $this->horizonApi->getCompletedJobs($service, [
                'starting_at' => 0
            ]);
            $completedData = $completedResponse['data'] ?? null;

            if ($completedResponse['success'] ?? false && \is_array($completedData)) {
                $jobsPayload = $completedData['jobs'] ?? [];

                foreach ($jobsPayload as $job) {
                    if (! \is_array($job)) {
                        continue;
                    }

                    $completedAt = $job['completed_at'] ?? null;
                    if (! \is_numeric($completedAt) || (int) $completedAt < $sinceTimestamp) {
                        continue;
                    }

                    $queueRaw = isset($job['queue']) ? (string) $job['queue'] : '';
                    $queue = $this->normalizeQueueName($queueRaw);
                    if ($queue === null) {
                        $queue = $queueRaw;
                    }

                    if (! isset($normalizedProcessed[$queue])) {
                        $normalizedProcessed[$queue] = 0;
                    }

                    $normalizedProcessed[$queue]++;
                }
            }

            $failedResponse = $this->horizonApi->getFailedJobs($service, [
                'starting_at' => 0
            ]);
            $failedData = $failedResponse['data'] ?? null;

            if ($failedResponse['success'] ?? false && \is_array($failedData)) {
                $jobsPayload = $failedData['jobs'] ?? [];

                foreach ($jobsPayload as $job) {
                    if (! \is_array($job)) {
                        continue;
                    }

                    $failedAt = $job['failed_at'] ?? null;
                    if (! \is_numeric($failedAt) || (int) $failedAt < $sinceTimestamp) {
                        continue;
                    }

                    $queueRaw = isset($job['queue']) ? (string) $job['queue'] : '';
                    $queue = $this->normalizeQueueName($queueRaw);
                    if ($queue === null) {
                        $queue = $queueRaw;
                    }

                    if (! isset($normalizedFailed[$queue])) {
                        $normalizedFailed[$queue] = 0;
                    }

                    $normalizedFailed[$queue]++;
                }
            }
        }

        $allQueues = \array_unique(\array_merge(\array_keys($normalizedProcessed), \array_keys($normalizedFailed)));
        $agg = [];
        foreach ($allQueues as $q) {
            $agg[$q] = [
                'processed' => $normalizedProcessed[$q] ?? 0,
                'failed' => $normalizedFailed[$q] ?? 0,
            ];
        }
        \uasort($agg, static fn ($a, $b) => ($b['processed'] + $b['failed']) <=> ($a['processed'] + $a['failed']));
        $agg = \array_slice($agg, 0, self::TOP_N_QUEUES, true);
        $queues = \array_keys($agg);
        $processed = [];
        $failed = [];
        foreach ($agg as $v) {
            $processed[] = $v['processed'];
            $failed[] = $v['failed'];
        }

        return ['queues' => $queues, 'processed' => $processed, 'failed' => $failed];
    }

    /**
     * Get jobs past hour per service using Horizon HTTP API stats.
     *
     * @return array{services: list<string>, jobsPastHour: list<int>}
     */
    public function getJobsPastHourByService(): array {
        /** @var \Illuminate\Support\Collection<int, Service> $services */
        $services = Service::query()
            ->whereNotNull('base_url')
            ->orderBy('name')
            ->get(['id', 'name', 'base_url']);

        if ($services->isEmpty()) {
            return ['services' => [], 'jobsPastHour' => []];
        }

        $names = [];
        $values = [];

        /** @var Service $service */
        foreach ($services as $service) {
            if (! $service->base_url) {
                continue;
            }

            $response = $this->horizonApi->getStats($service);
            $data = $response['data'] ?? null;

            if (! (($response['success'] ?? false) && \is_array($data) && isset($data['recentJobs']))) {
                continue;
            }

            $names[] = (string) $service->name;
            $values[] = (int) $data['recentJobs'];
        }

        return ['services' => $names, 'jobsPastHour' => $values];
    }
}
