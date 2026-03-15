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
     * @return View
     */
    public function index(): View {
        $services = Service::orderBy('name')->get(['id', 'name']);

        return \view('horizon.metrics.index', [
            'jobsPastMinute' => null,
            'jobsPastHour' => null,
            'failedPastSevenDays' => null,
            'processedPast24Hours' => null,
            'failuresTable' => null,
            'failureRate24h' => null,
            'header' => 'Horizon Hub – Metrics',
            'services' => $services,
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
                'processedPast24Hours' => $this->metrics->getProcessedPast24Hours($service),
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
