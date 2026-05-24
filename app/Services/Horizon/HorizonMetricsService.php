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
    public function __construct(HorizonApiProxyService $horizonApi, HorizonJobsWindowFetcher $jobsWindowFetcher)
    {
        $this->jobsThroughputMetrics = new JobsThroughputMetricsCalculator($horizonApi, $jobsWindowFetcher);
        $this->workloadMetrics = new WorkloadMetricsCalculator($horizonApi, $jobsWindowFetcher);
        $this->failureMetrics = new FailureMetricsCalculator($horizonApi, $jobsWindowFetcher);
        $this->runtimeMetrics = new RuntimeMetricsCalculator($horizonApi, $jobsWindowFetcher);
        $this->queueFailureCounters = new QueueFailureCountersCalculator($horizonApi, $jobsWindowFetcher);
        $this->jobsVolumeLast24h = new JobsVolumeLast24hCalculator($horizonApi, $jobsWindowFetcher);
    }

    /**
     * Build dashboard/metrics page data from live Horizon API reads.
     *
     * @param list<int> $serviceIds
     *
     * @return array{
     *     metricsChartData: array<string, mixed>,
     *     jobsPastMinute: mixed,
     *     jobsPastHour: mixed,
     *     failedPastSevenDays: mixed,
     *     failureRate24h: array<string, mixed>,
     *     jobRuntimesLast24h: mixed,
     *     failureRateOverTime: mixed,
     *     jobsVolumeLast24h: mixed,
     *     workloadRows: mixed,
     *     supervisorsRows: mixed,
     *     workloadSummary: string,
     *     supervisorsSummary: string,
     *     waitByQueue: mixed,
     *     hasRuntimeChart: bool,
     *     hasFailureRateChart: bool,
     *     hasJobsVolumeChart: bool,
     *     hasServiceChart: bool
     * }
     */
    public function buildMetricsDashboardData(array $serviceIds): array
    {
        $throughput = $this->getThroughputTotalsForServiceIds($serviceIds);
        $jobsPastMinute = $throughput['jobsPastMinute'];
        $jobsPastHour = $throughput['jobsPastHour'];
        $failedPastSevenDays = $throughput['failedPastSevenDays'];
        $failureRate24h = $this->getFailureRate24h($serviceIds);
        $jobRuntimesLast24h = $this->getJobRuntimesLast24h($serviceIds);
        $failureRateOverTime = $this->getFailureRateOverTime($serviceIds);
        $jobsVolumeLast24h = $this->getJobsVolumeLast24h($serviceIds);
        $workloadRows = $this->getWorkloadData($serviceIds);
        $supervisorsRows = $this->getSupervisorsData($serviceIds);

        $totalQueues = \count($workloadRows);
        $totalJobs = 0;

        foreach ($workloadRows as $row) {
            $totalJobs += (int) $row['jobs'];
        }

        $workloadSummary = "$totalQueues queue(s), $totalJobs job(s) total";

        $totalSupervisors = \count($supervisorsRows);
        $onlineSupervisors = 0;

        foreach ($supervisorsRows as $row) {
            if ($row['status'] === 'online') {
                $onlineSupervisors++;
            }
        }
        $supervisorsSummary = "$totalSupervisors supervisor(s), $onlineSupervisors online";

        $waitByQueue = $this->getWaitByQueueChartData($workloadRows);

        $metricsChartData = [
            'jobsVolumeLast24h' => $jobsVolumeLast24h,
            'jobRuntimesLast24h' => $jobRuntimesLast24h,
            'failureRateOverTime' => $failureRateOverTime,
            'waitByQueue' => $waitByQueue,
        ];

        return [
            'metricsChartData' => $metricsChartData,
            'jobsPastMinute' => $jobsPastMinute,
            'jobsPastHour' => $jobsPastHour,
            'failedPastSevenDays' => $failedPastSevenDays,
            'failureRate24h' => $failureRate24h,
            'jobRuntimesLast24h' => $jobRuntimesLast24h,
            'failureRateOverTime' => $failureRateOverTime,
            'jobsVolumeLast24h' => $jobsVolumeLast24h,
            'workloadRows' => $workloadRows,
            'supervisorsRows' => $supervisorsRows,
            'workloadSummary' => $workloadSummary,
            'supervisorsSummary' => $supervisorsSummary,
            'waitByQueue' => $waitByQueue,
            'hasRuntimeChart' => true,
            'hasFailureRateChart' => true,
            'hasJobsVolumeChart' => true,
            'hasServiceChart' => ! empty($waitByQueue['queues']),
        ];
    }

    /**
     * Workload rows as queue list rows for the queues UI and SSE partials.
     *
     * @param list<int> $serviceFilterIds
     *
     * @return Collection<int, \stdClass>
     */
    public function buildQueuesCollectionForServiceFilter(array $serviceFilterIds): Collection
    {
        $workloadRows = $this->getWorkloadData($serviceFilterIds);

        if (! empty($serviceFilterIds)) {
            $allowedServiceIds = \array_fill_keys($serviceFilterIds, true);
            $workloadRows = \array_values(\array_filter(
                $workloadRows,
                static function (array $row) use ($allowedServiceIds): bool {
                    return isset($allowedServiceIds[(int) $row['service_id']]);
                },
            ));
        }

        $serviceIds = \array_values(\array_unique(\array_map(
            static fn (array $row): int => (int) $row['service_id'],
            $workloadRows,
        )));

        $servicesById = empty($serviceIds)
            ? \collect()
            : Service::whereIn('id', $serviceIds)->get()->keyBy('id');

        $queues = \collect($workloadRows)
            ->map(function (array $row) use ($servicesById) {
                /** @var Service|null $service */
                $service = $servicesById->get((int) $row['service_id']);
                $queueRow = new \stdClass;
                $queueRow->service_id = (int) $row['service_id'];
                $queueRow->queue = QueueNameNormalizer::normalize($row['queue']) ?? $row['queue'];
                $queueRow->job_count = (int) $row['jobs'];
                $queueRow->service = $service;

                return $queueRow;
            })
            ->sortBy(fn ($r) => $r->queue)
            ->values();

        return $queues;
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
     * @return array{services: list<string>, jobsPastHour: list<int>}
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
     * @return array{queues: list<string>, processed: list<int>, failed: list<int>}
     */
    public function getProcessedFailedByQueue(array $serviceScope = []): array
    {
        return $this->queueFailureCounters->getProcessedFailedByQueue($serviceScope);
    }

    /**
     * Get the supervisors data for a single service.
     *
     * @return array<int, array{service_id: int, service: string, name: string, status: string, jobs: int, processes: int|null}>
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
        if (empty($serviceIds)) {
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

        if (empty($waits)) {
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
