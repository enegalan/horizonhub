<?php

namespace App\Http\Controllers\Stream;

use App\Http\Controllers\StreamController;
use App\Models\Service;
use App\Services\HorizonMetricsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetricsStreamController extends StreamController {

    /**
     * The Horizon metrics service.
     *
     * @var HorizonMetricsService
     */
    private HorizonMetricsService $metrics;

    /**
     * Create a new metrics stream controller.
     *
     * @param HorizonMetricsService $metrics
     */
    public function __construct(HorizonMetricsService $metrics) {
        $this->metrics = $metrics;
    }

    /**
     * Open the metrics dashboard stream (SSE).
     *
     * Behaviour:
     * - Emits a "metrics" SSE event every config interval seconds.
     * - Each event carries a JSON payload with:
     *   - "summary": counters for jobs past minute/hour, failed past 7 days,
     *     processed past 24 hours and 24h failure rate.
     *   - "avgRuntimeOverTime": time-series data used to draw the average
     *     runtime chart.
     *   - "failureRateOverTime": time-series data used to draw the failure
     *     rate chart.
     *   - "workload": current queue workload snapshot.
     *   - "supervisors": current supervisors snapshot.
     * - If a valid "service_id" query is present, all metrics are scoped to
     *   that service; otherwise they are aggregated across services.
     *
     * @param Request $request
     * @return StreamedResponse
     */
    public function stream(Request $request): StreamedResponse {
        $serviceId = $request->query('service_id');
        $service = !empty($serviceId) ? Service::find($serviceId) : null;

        return $this->runStream(function () use ($serviceId, $service): array {
            try {
                return [
                    'summary' => [
                        'jobsPastMinute' => $this->metrics->getJobsPastMinute($service),
                        'jobsPastHour' => $this->metrics->getJobsPastHour($service),
                        'failedPastSevenDays' => $this->metrics->getFailedPastSevenDays($service),
                        'processedPast24Hours' => $this->metrics->getProcessedPast24Hours($service),
                        'failureRate24h' => $this->metrics->getFailureRate24h($serviceId),
                    ],
                    'avgRuntimeOverTime' => $this->metrics->getAvgRuntimeOverTime($serviceId),
                    'failureRateOverTime' => $this->metrics->getFailureRateOverTime($serviceId),
                    'workload' => $this->metrics->getWorkloadData($serviceId),
                    'supervisors' => $this->metrics->getSupervisorsData($serviceId),
                ];
            } catch (\Throwable $e) {
                \Log::error('MetricsStreamController stream failed', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return ['error' => 'Stream error'];
            }
        }, 'metrics');
    }
}
