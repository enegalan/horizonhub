<?php

namespace App\Services\Queues;

use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Services\Metrics\Calculators\WorkloadMetricsCalculator;
use App\Support\Horizon\ClientResponse;
use App\Support\Horizon\MasterReader;
use App\Support\Horizon\MetricsHistoryReader;
use App\Support\NumberFormatter;
use App\Support\Queues\QueueNameNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class QueueOverviewService
{
    /**
     * The workload metrics calculator.
     */
    private WorkloadMetricsCalculator $workloadMetrics;

    /**
     * The constructor.
     */
    public function __construct(WorkloadMetricsCalculator $workloadMetrics)
    {
        $this->workloadMetrics = $workloadMetrics;
    }

    /**
     * Build the queue overview used by the queues index.
     *
     * @param list<int> $serviceIds
     *
     * @return array{
     *     services: Collection<int, Service>,
     *     rows: list<array<string, mixed>>,
     *     masterGroups: list<array<string, mixed>>,
     *     totals: array{queueCount: int, totalJobs: int}
     * }
     */
    public function build(array $serviceIds = [], bool $includeThroughput = true): array
    {
        $services = Service::getServices($serviceIds, true, true);
        $rows = [];
        $masterGroups = [];

        /** @var Service $service */
        foreach ($services as $service) {
            $groups = $this->private__groups($service);
            $connections = $this->private__connectionsByQueue($groups);

            foreach ($groups as $group) {
                $masterGroups[] = [
                    ...$group,
                    'service_id' => (int) $service->id,
                    'service' => (string) $service->name,
                ];
            }

            foreach ($this->workloadMetrics->getWorkloadForService($service) as $workload) {
                $queue = (string) $workload['queue'];
                $rows[] = [
                    'service_id' => (int) $service->id,
                    'service' => $service,
                    'service_name' => (string) $service->name,
                    'queue' => $queue,
                    'connection' => $connections[$queue] ?? '',
                    'jobs' => (int) $workload['jobs'],
                    'processes' => $workload['processes'],
                    'wait' => $workload['wait'],
                    'throughput' => null,
                ];
            }
        }

        $uniqueQueues = \array_values(\array_unique(\array_column($rows, 'queue')));

        if ($includeThroughput && $uniqueQueues !== [] && $this->private__isWithinLimit(\count($uniqueQueues))) {
            foreach ($rows as $index => $row) {
                $summary = $this->private__queueSummary($row['service'], (string) $row['queue']);
                $rows[$index]['throughput'] = $summary['throughput'] ?? null;
            }
        }

        return [
            'services' => $services,
            'rows' => $rows,
            'masterGroups' => $masterGroups,
            'totals' => [
                'queueCount' => \count($uniqueQueues),
                'totalJobs' => (int) \array_sum(\array_column($rows, 'jobs')),
            ],
        ];
    }

    /**
     * Build the queue detail data for one aggregated queue name.
     *
     * @param list<int> $serviceIds
     *
     * @return array{
     *     queue: string,
     *     services: Collection<int, Service>,
     *     rows: list<array<string, mixed>>,
     *     metrics: array<string, mixed>|null,
     *     silencedJobs: int|null,
     *     totals: array{pendingJobs: int, processes: int}
     * }
     */
    public function forQueue(array $serviceIds, string $queue): array
    {
        $queue = QueueNameNormalizer::normalize($queue);
        $services = Service::getServices($serviceIds, true, true);
        $metricsEnabled = $this->private__isWithinLimit(1);
        $rows = [];
        $summaries = [];
        $silencedJobs = 0;
        $silencedAvailable = true;

        /** @var Service $service */
        foreach ($services as $service) {
            $groups = $this->private__groups($service);
            $connections = $this->private__connectionsByQueue($groups);
            $workloadRow = null;

            foreach ($this->workloadMetrics->getWorkloadForService($service) as $candidate) {
                if ((string) $candidate['queue'] === $queue) {
                    $workloadRow = $candidate;
                    break;
                }
            }

            $summary = $metricsEnabled ? $this->private__queueSummary($service, $queue) : null;

            if ($workloadRow === null && ! isset($connections[$queue]) && $summary === null) {
                continue;
            }

            $rows[] = [
                'service_id' => (int) $service->id,
                'service' => $service,
                'service_name' => (string) $service->name,
                'queue' => $queue,
                'connection' => $connections[$queue] ?? '',
                'jobs' => (int) ($workloadRow['jobs'] ?? 0),
                'processes' => $workloadRow['processes'] ?? null,
                'wait' => $workloadRow['wait'] ?? null,
                'throughput' => $summary['throughput'] ?? null,
            ];

            if ($summary !== null) {
                $summaries[] = $summary;
            }

            $silencedData = ClientResponse::data(HorizonClientApiService::getSilencedJobs($service));
            $silencedJobsList = \is_array($silencedData) ? ($silencedData['jobs'] ?? null) : null;

            if (! \is_array($silencedJobsList)
                || NumberFormatter::nonNegativeInt($silencedData['total'] ?? null) > \count($silencedJobsList)
            ) {
                $silencedAvailable = false;
            } else {
                foreach ($silencedJobsList as $job) {
                    if (! \is_array($job)) {
                        continue;
                    }

                    $jobQueue = (string) ($job['queue'] ?? '');

                    if (QueueNameNormalizer::normalize($jobQueue) === $queue) {
                        $silencedJobs++;
                    }
                }
            }
        }

        return [
            'queue' => $queue,
            'services' => $services,
            'rows' => $rows,
            'metrics' => MetricsHistoryReader::combine($summaries),
            'silencedJobs' => $silencedAvailable ? $silencedJobs : null,
            'totals' => [
                'pendingJobs' => (int) \array_sum(\array_column($rows, 'jobs')),
                'processes' => (int) \array_sum(\array_column($rows, 'processes')),
            ],
        ];
    }

    /**
     * Map overview rows to the stdClass rows used by the queues table.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return Collection<int, \stdClass>
     */
    public function queueRowsCollection(array $rows): Collection
    {
        return \collect($rows)
            ->map(static function (array $row): \stdClass {
                $queueRow = new \stdClass;
                $queueRow->service_id = $row['service_id'];
                $queueRow->service = $row['service'];
                $queueRow->service_name = $row['service_name'];
                $queueRow->queue = $row['queue'];
                $queueRow->connection = $row['connection'];
                $queueRow->job_count = $row['jobs'];
                $queueRow->processes = $row['processes'];
                $queueRow->wait = $row['wait'];
                $queueRow->throughput = $row['throughput'];

                return $queueRow;
            })
            ->sortBy(static fn (\stdClass $row): string => $row->queue . '|' . $row->service_name)
            ->values();
    }

    /**
     * Build a normalized queue to connection map from master groups.
     *
     * @param list<array<string, mixed>> $groups
     *
     * @return array<string, string>
     */
    private function private__connectionsByQueue(array $groups): array
    {
        $connections = [];

        foreach ($groups as $group) {
            foreach ($group['supervisors'] as $supervisor) {
                $connection = (string) $supervisor['connection'];

                foreach ($supervisor['queueNames'] as $queueName) {
                    $connections[$queueName][$connection !== '' ? $connection : '—'] = true;
                }
            }
        }

        return \array_map(
            static fn (array $names): string => \implode(', ', \array_keys($names)),
            $connections,
        );
    }

    /**
     * Read master groups for one service.
     *
     * @return list<array<string, mixed>>
     */
    private function private__groups(Service $service): array
    {
        $mastersData = ClientResponse::data(HorizonClientApiService::getMasters($service));

        return $mastersData !== null ? MasterReader::groupsFromMastersPayload($mastersData) : [];
    }

    /**
     * Determine whether queue metrics should be fetched for this many queues.
     */
    private function private__isWithinLimit(int $queueCount): bool
    {
        $max = (int) config('horizonhub.queue_metrics_max_queues');

        return $max > 0 && $queueCount <= $max;
    }

    /**
     * Get the metrics history for one queue on a service.
     *
     * @param string $queue The queue name as returned by the metrics API.
     *
     * @return array{
     *     throughput: float|null,
     *     runtime: float|null,
     *     wait: float|null,
     *     series: array{time: list<int>, throughput: list<float>, runtime: list<float>, wait: list<float>}
     * }|null
     */
    private function private__metricsForService(Service $service, string $queue): ?array
    {
        return $this->private__remember($service, 'metrics:' . $queue, static function () use ($service, $queue): ?array {
            return MetricsHistoryReader::summarize(
                ClientResponse::data(HorizonClientApiService::getMetricsQueue($service, $queue)),
            );
        });
    }

    /**
     * Get the queue names that have metrics history for a service.
     *
     * @return list<string>|null Null when the upstream response is unavailable.
     */
    private function private__namesForService(Service $service): ?array
    {
        return $this->private__remember($service, 'names', static function () use ($service): ?array {
            $data = ClientResponse::data(HorizonClientApiService::getMetricsQueues($service));

            if ($data === null) {
                return null;
            }

            if (isset($data['queues']) && \is_array($data['queues'])) {
                $data = $data['queues'];
            }

            $names = [];

            foreach ($data as $name) {
                if (\is_string($name) && $name !== '') {
                    $names[] = $name;
                }
            }

            return \array_values(\array_unique($names));
        });
    }

    /**
     * Read one service/queue metrics summary, resolving the raw queue name.
     *
     * @return array<string, mixed>|null
     */
    private function private__queueSummary(Service $service, string $queue): ?array
    {
        $names = $this->private__namesForService($service);

        if ($names === null) {
            return null;
        }

        foreach ($names as $rawName) {
            $normalized = QueueNameNormalizer::normalize($rawName);

            if ($normalized === $queue) {
                return $this->private__metricsForService($service, $rawName);
            }
        }

        return null;
    }

    /**
     * Read and cache one upstream metrics value.
     *
     * Failed upstream reads are not cached so the next request can retry.
     *
     * @template T of array
     *
     * @param callable(): (T|null) $callback
     *
     * @return T|null
     */
    private function private__remember(Service $service, string $suffix, callable $callback): ?array
    {
        $ttl = (int) config('horizonhub.queue_metrics_cache_seconds');

        if ($ttl <= 0) {
            return $callback();
        }

        $key = 'horizonhub:queue-metrics:' . ($service->getKey() ?? 'new') . ':' . \sha1($suffix);
        $cached = Cache::get($key);

        if (\is_array($cached)) {
            /** @var T $cached */
            return $cached;
        }

        $value = $callback();

        if ($value !== null) {
            Cache::put($key, $value, $ttl);
        }

        return $value;
    }
}
