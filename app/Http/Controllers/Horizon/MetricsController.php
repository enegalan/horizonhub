<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use App\Services\HorizonMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

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
        ]);
    }

    /**
     * Get the summary data for the metrics dashboard.
     */
    public function dataSummary(ServiceRequest $request): JsonResponse
    {
        $serviceIds = $request->getServiceIds();
        $scope = $request->getServiceIds();
        $throughput = $this->metrics->getThroughputTotalsForServiceIds($serviceIds);

        return \response()->json([
            'jobsPastMinute' => $throughput['jobsPastMinute'],
            'jobsPastHour' => $throughput['jobsPastHour'],
            'failedPastSevenDays' => $throughput['failedPastSevenDays'],
            'failureRate24h' => $this->metrics->getFailureRate24h($scope),
        ]);
    }

    /**
     * Get per-job runtime data for the metrics dashboard (rolling last 24 hours).
     */
    public function dataJobRuntimesLast24h(ServiceRequest $request): JsonResponse
    {
        $scope = $request->getServiceIds();

        return \response()->json($this->metrics->getJobRuntimesLast24h($scope));
    }

    /**
     * Get the failure rate over time data for the metrics dashboard.
     */
    public function dataFailureRateOverTime(ServiceRequest $request): JsonResponse
    {
        $scope = $request->getServiceIds();

        return \response()->json($this->metrics->getFailureRateOverTime($scope));
    }

    /**
     * Get hourly job counts over the rolling last 24 hours for the metrics dashboard.
     */
    public function dataJobsVolumeLast24h(ServiceRequest $request): JsonResponse
    {
        $scope = $request->getServiceIds();

        return \response()->json($this->metrics->getJobsVolumeLast24h($scope));
    }

    /**
     * Get the supervisors data for the metrics dashboard.
     */
    public function dataSupervisors(ServiceRequest $request): JsonResponse
    {
        $scope = $request->getServiceIds();

        return \response()->json([
            'supervisors' => $this->metrics->getSupervisorsData($scope),
        ]);
    }

    /**
     * Get the current workload data for the metrics dashboard.
     */
    public function dataWorkload(ServiceRequest $request): JsonResponse
    {
        $scope = $request->getServiceIds();

        return \response()->json([
            'workload' => $this->metrics->getWorkloadData($scope),
        ]);
    }
}
