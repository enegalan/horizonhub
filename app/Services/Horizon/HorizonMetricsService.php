<?php

namespace App\Services\Horizon;

use App\Models\Service;
use App\Services\Metrics\FailureMetricsCalculator;
use App\Services\Metrics\JobsThroughputMetricsCalculator;
use App\Services\Metrics\JobsVolumeLast24hCalculator;
use App\Services\Metrics\QueueFailureCountersCalculator;
use App\Services\Metrics\RuntimeMetricsCalculator;
use App\Services\Metrics\WorkloadMetricsCalculator;
use App\Support\Horizon\QueueNameNormalizer;
use Illuminate\Support\Collection;

class HorizonMetricsService
{
    /**
     * The number of top queues to return.
     *
     * @var int
     */
    private const TOP_N_QUEUES = 12;

    /**
     * The failure metrics calculator.
     */
    private FailureMetricsCalculator $failureMetrics;

    /**
     * The jobs throughput metrics calculator.
     */
    private JobsThroughputMetricsCalculator $jobsThroughputMetrics;

    /**
     * The jobs volume (last 24h) calculator.
     */
    private JobsVolumeLast24hCalculator $jobsVolumeLast24h;

    /**
     * The queue failure counters calculator.
     */
    private QueueFailureCountersCalculator $queueFailureCounters;

    /**
     * The runtime metrics calculator.
     */
    private RuntimeMetricsCalculator $runtimeMetrics;

    /**
     * The workload metrics calculator.
     */
    private WorkloadMetricsCalculator $workloadMetrics;

    /**
     * Construct the Horizon metrics service.
     */
    public function __construct(HorizonApiProxyService $horizonApi)
    {
        $this->jobsThroughputMetrics = new JobsThroughputMetricsCalculator($horizonApi);
        $this->workloadMetrics = new WorkloadMetricsCalculator($horizonApi);
        $this->failureMetrics = new FailureMetricsCalculator($horizonApi);
        $this->runtimeMetrics = new RuntimeMetricsCalculator($horizonApi);
        $this->queueFailureCounters = new QueueFailureCountersCalculator($horizonApi);
        $this->jobsVolumeLast24h = new JobsVolumeLast24hCalculator($horizonApi);
    }

    /**
     * Workload rows as queue list rows for the queues UI and SSE partials.
     *
     * @param list<int> $serviceFilterIds
     *
     * @return Collection<int, object{service_id: int, queue: string, job_count: int, service: Service|null}>
     */
    public function buildQueuesCollectionForServiceFilter(array $serviceFilterIds): Collection
    {
        $workloadRows = $this->getWorkloadData($serviceFilterIds);

        if ($serviceFilterIds !== []) {
            $allowedServiceIds = \array_fill_keys($serviceFilterIds, true);
            $workloadRows = \array_values(\array_filter(
                $workloadRows,
                static function (array $row) use ($allowedServiceIds): bool {
                    return isset($allowedServiceIds[(int) ($row['service_id'] ?? 0)]);
                },
            ));
        }

        $serviceIds = \array_values(\array_unique(\array_map(
            static fn (array $row): int => (int) $row['service_id'],
            $workloadRows,
        )));

        $servicesById = $serviceIds === []
            ? \collect()
            : Service::whereIn('id', $serviceIds)->get()->keyBy('id');

        return \collect($workloadRows)
            ->map(function (array $row) use ($servicesById) {
                $queueRaw = $row['queue'] ?? '';
                $normalizedQueue = QueueNameNormalizer::normalize($queueRaw);
                $queueLabel = $normalizedQueue ?? $queueRaw;

                return (object) [
                    'service_id' => (int) $row['service_id'],
                    'queue' => $queueLabel,
                    'job_count' => (int) $row['jobs'],
                    'service' => $servicesById->get((int) $row['service_id']),
                ];
            })
            ->sortBy(fn ($r) => $r->queue)
            ->values();
    }

    /**
     * Get the number of jobs failed in the past seven days.
     */
    public function getFailedPastSevenDays(?Service $service = null): int
    {
        return $this->jobsThroughputMetrics->getFailedPastSevenDays($service);
    }

    /**
     * Get the failure rate from 00:00 of the previous day until now.
     *
     * @return array{rate: float, processed: int, failed: int}
     */
    public function getFailureRate24h(array $serviceScope = []): array
    {
        return $this->failureMetrics->getFailureRate24h($serviceScope);
    }

    /**
     * Get the failure rate over time from 00:00 of the previous day until now.
     *
     * @return array{xAxis: list<string>, rate: list<float|null>}
     */
    public function getFailureRateOverTime(array $serviceScope = []): array
    {
        return $this->failureMetrics->getFailureRateOverTime($serviceScope);
    }

    /**
     * Get per-job runtimes over the rolling last 24 hours (completed and failed).
     *
     * @return array{points: list<array{endAtMs: int, seconds: float, name: string, service: string, status: string}>}
     */
    public function getJobRuntimesLast24h(array $serviceScope = []): array
    {
        return $this->runtimeMetrics->getJobRuntimesLast24h($serviceScope);
    }

    /**
     * Get the number of jobs processed in the past hour.
     */
    public function getJobsPastHour(?Service $service = null): int
    {
        return $this->jobsThroughputMetrics->getJobsPastHour($service);
    }

    /**
     * Get the number of jobs processed in the past hour by service.
     *
     * @return array<int, array{service_id: int, service: string, jobs: int}>
     */
    public function getJobsPastHourByService(): array
    {
        return $this->jobsThroughputMetrics->getJobsPastHourByService();
    }

    /**
     * Get the number of jobs processed in the past minute.
     */
    public function getJobsPastMinute(?Service $service = null): int
    {
        return $this->jobsThroughputMetrics->getJobsPastMinute($service);
    }

    /**
     * Get hourly completed and failed job counts over the rolling last 24 hours.
     *
     * @return array{xAxis: list<string>, completed: list<int>, failed: list<int>}
     */
    public function getJobsVolumeLast24h(array $serviceScope = []): array
    {
        return $this->jobsVolumeLast24h->getJobsVolumeLast24h($serviceScope);
    }

    /**
     * Get the processed and failed jobs by queue.
     *
     * @return array{queue: string, processed: int, failed: int}
     */
    public function getProcessedFailedByQueue(array $serviceScope = []): array
    {
        return $this->queueFailureCounters->getProcessedFailedByQueue($serviceScope);
    }

    /**
     * Get the supervisors data for a single service.
     *
     * @return array<int, array{name: string, status: string, processes: int, last_heartbeat: string, options: array<string, mixed>}>
     */
    public function getSupervisorsData(array $serviceScope = []): array
    {
        return $this->workloadMetrics->getSupervisorsData($serviceScope);
    }

    /**
     * Aggregate throughput counters for no filter (all services) or a subset by id.
     *
     * @param list<int> $serviceIds
     *
     * @return array{jobsPastMinute: int, jobsPastHour: int, failedPastSevenDays: int}
     */
    public function getThroughputTotalsForServiceIds(array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [
                'jobsPastMinute' => $this->getJobsPastMinute(null),
                'jobsPastHour' => $this->getJobsPastHour(null),
                'failedPastSevenDays' => $this->getFailedPastSevenDays(null),
            ];
        }

        $minute = 0;
        $hour = 0;
        $failed = 0;

        $services = Service::query()->whereIn('id', $serviceIds)->orderBy('name')->get();

        /** @var Service $service */
        foreach ($services as $service) {
            $minute += $this->getJobsPastMinute($service);
            $hour += $this->getJobsPastHour($service);
            $failed += $this->getFailedPastSevenDays($service);
        }

        return [
            'jobsPastMinute' => $minute,
            'jobsPastHour' => $hour,
            'failedPastSevenDays' => $failed,
        ];
    }

    /**
     * Build wait-by-queue bar chart data from workload rows (top 12 queues by max wait).
     *
     * @param array<int, array<string, mixed>> $workloadRows
     *
     * @return array{queues: list<string>, wait: list<float>}|null
     */
    public function getWaitByQueueChartData(array $workloadRows): ?array
    {
        $waits = [];

        foreach ($workloadRows as $row) {
            if (! \is_array($row)) {
                continue;
            }
            $queue = $row['queue'] ?? null;

            if (! \is_string($queue) || $queue === '') {
                continue;
            }

            if (! \array_key_exists('wait', $row) || $row['wait'] === null) {
                continue;
            }
            $w = (float) $row['wait'];

            if (! \is_finite($w)) {
                continue;
            }

            if (! isset($waits[$queue]) || $w > $waits[$queue]) {
                $waits[$queue] = $w;
            }
        }

        if ($waits === []) {
            return null;
        }
        \arsort($waits, \SORT_NUMERIC);
        $top = \array_slice($waits, 0, self::TOP_N_QUEUES, true);
        $queues = \array_keys($top);
        $wait = \array_values($top);

        return ['queues' => $queues, 'wait' => $wait];
    }

    /**
     * Get the workload data for a single service.
     *
     * @return array<int, array{service_id: int, service: string, queue: string, jobs: int, processes: int|null, wait: float|null}>
     */
    public function getWorkloadData(array $serviceScope = []): array
    {
        return $this->workloadMetrics->getWorkloadData($serviceScope);
    }

    /**
     * Get the workload for a single service.
     *
     * @return array<int, array{queue: string, jobs: int, processes: int|null, wait: float|null}>
     */
    public function getWorkloadForService(Service $service): array
    {
        return $this->workloadMetrics->getWorkloadForService($service);
    }
}
