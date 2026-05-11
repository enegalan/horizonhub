<?php

namespace App\Services\Horizon;

class MetricsDashboardDataService
{
    /**
     * The Horizon metrics service.
     */
    private HorizonMetricsService $metrics;

    /**
     * Construct the metrics dashboard data service.
     */
    public function __construct(HorizonMetricsService $metrics)
    {
        $this->metrics = $metrics;
    }

    /**
     * Build the metrics dashboard data.
     *
     * @param list<int> $serviceIds The service IDs.
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
    public function build(array $serviceIds): array
    {
        $scope = $serviceIds;
        $throughput = $this->metrics->getThroughputTotalsForServiceIds($serviceIds);
        $jobsPastMinute = $throughput['jobsPastMinute'];
        $jobsPastHour = $throughput['jobsPastHour'];
        $failedPastSevenDays = $throughput['failedPastSevenDays'];
        $failureRate24h = $this->metrics->getFailureRate24h($scope);
        $jobRuntimesLast24h = $this->metrics->getJobRuntimesLast24h($scope);
        $failureRateOverTime = $this->metrics->getFailureRateOverTime($scope);
        $jobsVolumeLast24h = $this->metrics->getJobsVolumeLast24h($scope);
        $workloadRows = $this->metrics->getWorkloadData($scope);
        $supervisorsRows = $this->metrics->getSupervisorsData($scope);

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

        $waitByQueue = $this->metrics->getWaitByQueueChartData($workloadRows);

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
            'hasServiceChart' => $waitByQueue !== null && $waitByQueue['queues'] !== [],
        ];
    }
}
