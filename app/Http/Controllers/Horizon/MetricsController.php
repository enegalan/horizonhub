<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use App\Services\HorizonMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class MetricsController extends Controller {

    /**
     * The Horizon metrics service.
     *
     * @var HorizonMetricsService
     */
    private HorizonMetricsService $metrics;

    /**
     * Construct the metrics controller.
     *
     * @param HorizonMetricsService $metrics
     */
    public function __construct(HorizonMetricsService $metrics) {
        $this->metrics = $metrics;
    }

    /**
     * Show the metrics dashboard.
     *
     * @param ServiceRequest $request
     * @return View
     */
    public function index(ServiceRequest $request): View {
        $services = Service::orderBy('name')->get(['id', 'name']);
        $serviceIdForMetrics = $request->getServiceId();
        $serviceFilter = $serviceIdForMetrics !== null ? (string) $serviceIdForMetrics : '';
        $serviceModel = $serviceIdForMetrics !== null ? Service::find($serviceIdForMetrics) : null;
        $jobsPastMinute = $this->metrics->getJobsPastMinute($serviceModel);
        $jobsPastHour = $this->metrics->getJobsPastHour($serviceModel);
        $failedPastSevenDays = $this->metrics->getFailedPastSevenDays($serviceModel);
        $failureRate24h = $this->metrics->getFailureRate24h($serviceIdForMetrics);
        $avgRuntimeOverTime = $this->metrics->getAvgRuntimeOverTime($serviceIdForMetrics);
        $failureRateOverTime = $this->metrics->getFailureRateOverTime($serviceIdForMetrics);
        $workloadRows = $this->metrics->getWorkloadData($serviceIdForMetrics);
        $supervisorsRows = $this->metrics->getSupervisorsData($serviceIdForMetrics);

        $totalQueues = \is_array($workloadRows) ? \count($workloadRows) : 0;
        $totalJobs = 0;
        if (\is_array($workloadRows)) {
            foreach ($workloadRows as $row) {
                if (!\is_array($row)) {
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
                if (!\is_array($row)) {
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
            'avgRuntimeOverTime' => $avgRuntimeOverTime,
            'failureRateOverTime' => $failureRateOverTime,
            'workloadRows' => $workloadRows,
            'supervisorsRows' => $supervisorsRows,
            'workloadSummary' => $workloadSummary,
            'supervisorsSummary' => $supervisorsSummary,
            'header' => 'Horizon Hub – Metrics',
            'services' => $services,
            'serviceFilter' => $serviceFilter,
        ]);
    }

    /**
     * Get the summary data for the metrics dashboard.
     *
     * @param ServiceRequest $request
     * @return JsonResponse
     */
    public function dataSummary(ServiceRequest $request): JsonResponse {
        $serviceId = $request->getServiceId();
        $service = $serviceId !== null ? Service::find($serviceId) : null;
        return \response()->json([
            'jobsPastMinute' => $this->metrics->getJobsPastMinute($service),
            'jobsPastHour' => $this->metrics->getJobsPastHour($service),
            'failedPastSevenDays' => $this->metrics->getFailedPastSevenDays($service),
            'failureRate24h' => $this->metrics->getFailureRate24h($serviceId),
        ]);
    }

    /**
     * Get the average runtime data for the metrics dashboard.
     *
     * @param ServiceRequest $request
     * @return JsonResponse
     */
    public function dataAvgRuntime(ServiceRequest $request): JsonResponse {
        $serviceId = $request->getServiceId();

        return \response()->json($this->metrics->getAvgRuntimeOverTime($serviceId));
    }

    /**
     * Get the failure rate over time data for the metrics dashboard.
     *
     * @param ServiceRequest $request
     * @return JsonResponse
     */
    public function dataFailureRateOverTime(ServiceRequest $request): JsonResponse {
        $serviceId = $request->getServiceId();

        return \response()->json($this->metrics->getFailureRateOverTime($serviceId));
    }

    /**
     * Get the supervisors data for the metrics dashboard.
     *
     * @param ServiceRequest $request
     * @return JsonResponse
     */
    public function dataSupervisors(ServiceRequest $request): JsonResponse {
        $serviceId = $request->getServiceId();
        return \response()->json([
            'supervisors' => $this->metrics->getSupervisorsData($serviceId),
        ]);
    }

    /**
     * Get the current workload data for the metrics dashboard.
     *
     * @param ServiceRequest $request
     * @return JsonResponse
     */
    public function dataWorkload(ServiceRequest $request): JsonResponse {
        $serviceId = $request->getServiceId();
        return \response()->json([
            'workload' => $this->metrics->getWorkloadData($serviceId),
        ]);
    }
}
