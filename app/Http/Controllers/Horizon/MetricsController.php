<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\HorizonMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View {
        $services = Service::orderBy('name')->get(['id', 'name']);
        $serviceId = $request->query('service_id');
        $serviceFilter = $serviceId !== null && $serviceId !== '' ? (string) $serviceId : '';

        $serviceIdForMetrics = null;
        if ( !empty($serviceId) && \is_numeric($serviceId)) {
            $serviceIdForMetrics = (int) $serviceId;
        }

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
     * @param Request $request
     * @return JsonResponse
     */
    public function dataSummary(Request $request): JsonResponse {
        $service_id = $request->query('service_id');
        $service = $service_id !== null ? Service::find($service_id) : null;

        return \response()->json(function () use ($service, $service_id): array {
            return [
                'jobsPastMinute' => $this->metrics->getJobsPastMinute($service),
                'jobsPastHour' => $this->metrics->getJobsPastHour($service),
                'failedPastSevenDays' => $this->metrics->getFailedPastSevenDays($service),
                'failureRate24h' => $this->metrics->getFailureRate24h($service_id),
            ];
        });
    }

    /**
     * Get the average runtime data for the metrics dashboard.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dataAvgRuntime(Request $request): JsonResponse {
        $service_id = $request->query('service_id');
        if (!Service::find($service_id)) {
            $service_id = null;
        }
        return \response()->json(function () use ($service_id): array {
            return $this->metrics->getAvgRuntimeOverTime($service_id);
        });
    }

    /**
     * Get the failure rate over time data for the metrics dashboard.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dataFailureRateOverTime(Request $request): JsonResponse {
        $service_id = $request->query('service_id');
        if (!Service::find($service_id)) {
            $service_id = null;
        }
        return \response()->json(function () use ($service_id): array {
            return $this->metrics->getFailureRateOverTime($service_id);
        });
    }

    /**
     * Get the supervisors data for the metrics dashboard.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dataSupervisors(Request $request): JsonResponse {
        $service_id = $request->query('service_id');
        if (!Service::find($service_id)) {
            $service_id = null;
        }
        return \response()->json(function () use ($service_id): array {
            return [
                'supervisors' => $this->metrics->getSupervisorsData($service_id),
            ];
        });
    }

    /**
     * Get the current workload data for the metrics dashboard.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dataWorkload(Request $request): JsonResponse {
        $service_id = $request->query('service_id');
        if (!Service::find($service_id)) {
            $service_id = null;
        }
        return \response()->json(function () use ($service_id): array {
            return [
                'workload' => $this->metrics->getWorkloadData($service_id),
            ];
        });
    }
}
