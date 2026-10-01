<?php

namespace App\Services\Metrics;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Services\Jobs\JobsWindowFetcherService;
use App\Services\Metrics\Calculators\FailureMetricsCalculator;
use App\Services\Metrics\Calculators\JobsThroughputMetricsCalculator;
use App\Services\Metrics\Calculators\JobsVolumeLast24hCalculator;
use App\Services\Metrics\Calculators\RuntimeMetricsCalculator;
use App\Services\Metrics\Calculators\WorkloadMetricsCalculator;
use App\Support\Queues\QueueNameNormalizer;
use Illuminate\Support\Collection;

class MetricsDataService
{
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
     * The jobs window fetcher.
     */
    private JobsWindowFetcherService $jobsWindowFetcher;

    /**
     * The runtime metrics calculator.
     */
    private RuntimeMetricsCalculator $runtimeMetrics;

    /**
     * The workload metrics calculator.
     */
    private WorkloadMetricsCalculator $workloadMetrics;

    /**
     * The constructor.
     *
     * @param FailureMetricsCalculator $failureMetrics The failure metrics calculator.
     * @param JobsThroughputMetricsCalculator $jobsThroughputMetrics The jobs throughput metrics calculator.
     * @param JobsVolumeLast24hCalculator $jobsVolumeLast24h The jobs volume (last 24h) calculator.
     * @param RuntimeMetricsCalculator $runtimeMetrics The runtime metrics calculator.
     * @param WorkloadMetricsCalculator $workloadMetrics The workload metrics calculator.
     * @param JobsWindowFetcherService $jobsWindowFetcher The jobs window fetcher.
     */
    public function __construct(
        FailureMetricsCalculator $failureMetrics,
        JobsThroughputMetricsCalculator $jobsThroughputMetrics,
        JobsVolumeLast24hCalculator $jobsVolumeLast24h,
        RuntimeMetricsCalculator $runtimeMetrics,
        WorkloadMetricsCalculator $workloadMetrics,
        JobsWindowFetcherService $jobsWindowFetcher,
    ) {
        $this->failureMetrics = $failureMetrics;
        $this->jobsThroughputMetrics = $jobsThroughputMetrics;
        $this->jobsVolumeLast24h = $jobsVolumeLast24h;
        $this->runtimeMetrics = $runtimeMetrics;
        $this->workloadMetrics = $workloadMetrics;
        $this->jobsWindowFetcher = $jobsWindowFetcher;
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
     *     hasServiceChart: bool
     * }
     */
    public function buildMetricsDashboardData(array $serviceIds): array
    {
        return $this->jobsWindowFetcher->runWithMemo(function () use ($serviceIds): array {
            $throughput = $this->getThroughputTotalsForServiceIds($serviceIds);
            $jobsPastMinute = $throughput['jobsPastMinute'];
            $jobsPastHour = $throughput['jobsPastHour'];
            $failedPastSevenDays = $throughput['failedPastSevenDays'];
            $failureRate24h = $this->failureMetrics->getFailureRate24h($serviceIds);
            $jobRuntimesLast24h = $this->runtimeMetrics->getJobRuntimesLast24h($serviceIds);
            $failureRateOverTime = $this->failureMetrics->getFailureRateOverTime($serviceIds);
            $jobsVolumeLast24h = $this->jobsVolumeLast24h->getJobsVolumeLast24h($serviceIds);
            $workloadRows = $this->workloadMetrics->getWorkloadData($serviceIds);
            $supervisorsRows = $this->workloadMetrics->getSupervisorsData($serviceIds);

            $totalQueues = \count($workloadRows);
            $totalJobs = 0;

            foreach ($workloadRows as $row) {
                $totalJobs += (int) $row['jobs'];
            }

            $workloadSummary = "$totalQueues queue(s), $totalJobs job(s) total";

            $totalSupervisors = \count($supervisorsRows);
            $onlineSupervisors = 0;

            foreach ($supervisorsRows as $row) {
                if ($row['status'] === ServiceStatus::Online) {
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
                'hasServiceChart' => ! empty($waitByQueue['queues']),
            ];
        });
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
        $workloadRows = $this->workloadMetrics->getWorkloadData($serviceFilterIds);

        if (! empty($serviceFilterIds)) {
            $allowedServiceIds = \array_fill_keys($serviceFilterIds, true);
            $workloadRows = \array_values(\array_filter(
                $workloadRows,
                static function (array $row) use ($allowedServiceIds): bool {
                    return isset($allowedServiceIds[(int) $row['service_id']]);
                },
            ));
        }

        $servicesById = Service::getServices($serviceFilterIds, false)->keyBy('id');

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
     * Aggregate throughput counters for no filter (all services) or a subset by id.
     *
     * @param list<int> $serviceIds
     *
     * @return array{jobsPastMinute: int, jobsPastHour: int, failedPastSevenDays: int}
     */
    public function getThroughputTotalsForServiceIds(array $serviceIds): array
    {
        if (empty($serviceIds)) {
            return $this->jobsThroughputMetrics->getThroughputTotals(null);
        }

        $services = Service::getServices($serviceIds, true, true);

        return $this->jobsThroughputMetrics->getThroughputTotals($services);
    }

    /**
     * Build wait-by-queue bar chart data from workload rows.
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
        $top = \array_slice($waits, 0, config('horizonhub.top_n_queues'), true);
        $queues = \array_keys($top);
        $wait = \array_values($top);

        return ['queues' => $queues, 'wait' => $wait];
    }
}
