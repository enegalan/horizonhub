<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\HorizonMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricsController extends Controller {

    private HorizonMetricsService $metrics;

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
        $service_id = $this->resolveServiceId($request);
        $service = $service_id !== null ? Service::find($service_id) : null;

        return $this->jsonOrFail(function () use ($service, $service_id): array {
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
        $service_id = $this->resolveServiceId($request);
        return $this->jsonOrFail(function () use ($service_id): array {
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
        $service_id = $this->resolveServiceId($request);
        return $this->jsonOrFail(function () use ($service_id): array {
            return $this->metrics->getFailureRateOverTime($service_id);
        });
    }

    /**
     * Get the processed vs failed data by queue for the metrics dashboard.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dataByQueue(Request $request): JsonResponse {
        $service_id = $this->resolveServiceId($request);
        return $this->jsonOrFail(function () use ($service_id): array {
            return ['queues' => [], 'processed' => [], 'failed' => []];
        });
    }

    /**
     * Get the supervisors data for the metrics dashboard.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dataSupervisors(Request $request): JsonResponse {
        $service_id = $this->resolveServiceId($request);

        return $this->jsonOrFail(function () use ($service_id): array {
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
        $service_id = $this->resolveServiceId($request);

        return $this->jsonOrFail(function () use ($service_id): array {
            return [
                'workload' => $this->metrics->getWorkloadData($service_id),
            ];
        });
    }

    /**
     * Return a JSON response or fail with an error.
     *
     * @param callable(): array $fn
     * @return JsonResponse
     */
    private function jsonOrFail(callable $fn): JsonResponse {
        try {
            return \response()->json($fn());
        } catch (\Throwable $e) {
            \Log::error('MetricsController failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return \response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Resolve optional service_id from request. Returns null for "all services".
     *
     * @param Request $request
     * @return int|null
     */
    private function resolveServiceId(Request $request): ?int {
        $raw = $request->query('service_id');
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = \filter_var($raw, \FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            return null;
        }
        return Service::where('id', $id)->exists() ? $id : null;
    }
}
