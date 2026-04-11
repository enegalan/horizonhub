<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use App\Services\Horizon\HorizonMetricsService;
use Illuminate\Contracts\View\View;

class MetricsController extends Controller
{
    /**
     * The Horizon metrics service.
     */
    private HorizonMetricsService $metrics;

    /**
     * Construct the metrics controller.
     */
    public function __construct(HorizonMetricsService $metrics)
    {
        $this->metrics = $metrics;
    }

    /**
     * Show the metrics dashboard.
     */
    public function index(ServiceRequest $request): View
    {
        $services = Service::orderBy('name')->get(['id', 'name']);
        $serviceIds = $request->getServiceIds();
        $scope = $request->getServiceIds();
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

        $totalQueues = \is_array($workloadRows) ? \count($workloadRows) : 0;
        $totalJobs = 0;
        if (\is_array($workloadRows)) {
            foreach ($workloadRows as $row) {
                if (! \is_array($row)) {
                    continue;
                }
                $totalJobs += isset($row['jobs']) && \is_numeric($row['jobs']) ? (int) $row['jobs'] : 0;
            }
        }

        $workloadSummary = "$totalQueues queue(s), $totalJobs job(s) total";

        $totalSupervisors = \is_array($supervisorsRows) ? \count($supervisorsRows) : 0;
        $onlineSupervisors = 0;
        if (\is_array($supervisorsRows)) {
            foreach ($supervisorsRows as $row) {
                if (! \is_array($row)) {
                    continue;
                }
                if (($row['status'] ?? null) === 'online') {
                    $onlineSupervisors++;
                }
            }
        }
        $supervisorsSummary = "$totalSupervisors supervisor(s), $onlineSupervisors online";

        $waitByQueue = $this->metrics->getWaitByQueueChartData(\is_array($workloadRows) ? $workloadRows : []);

        return \view('horizon.metrics.index', [
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
            'header' => 'Horizon Hub – Metrics',
            'services' => $services,
            'serviceIds' => $serviceIds,
            'waitByQueue' => $waitByQueue,
        ]);
    }
}
