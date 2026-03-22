<?php

namespace App\Http\Controllers\Stream;

use App\Http\Controllers\StreamController;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Services\Horizon\HorizonMetricsService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetricsStreamController extends StreamController
{
    /**
     * The Horizon metrics service.
     */
    private HorizonMetricsService $metrics;

    /**
     * Create a new metrics stream controller.
     */
    public function __construct(HorizonMetricsService $metrics)
    {
        $this->metrics = $metrics;
    }

    /**
     * Open the metrics dashboard stream (SSE).
     *
     * Behaviour:
     * - Emits a "metrics" SSE event every config interval seconds.
     * - Each event carries a JSON payload with:
     *   - "summary": counters for jobs past minute/hour, failed past 7 days,
     *     and 24h failure rate.
     *   - "jobRuntimesLast24h": per-job runtime points for the runtime scatter
     *     chart (rolling 24h).
     *   - "failureRateOverTime": time-series data used to draw the failure
     *     rate chart.
     *   - "jobsVolumeLast24h": hourly completed/failed counts for the jobs
     *     volume chart (rolling 24h).
     *   - "workload": current queue workload snapshot.
     *   - "supervisors": current supervisors snapshot.
     * - If valid "service_id" / "service_id[]" query values are present, metrics
     *   are scoped to those services; otherwise they are aggregated across all.
     */
    public function stream(ServiceRequest $request): StreamedResponse
    {
        $serviceIds = $request->getServiceIds();
        $throughput = $this->metrics->getThroughputTotalsForServiceIds($serviceIds);

        return $this->runStream(function () use ($serviceIds, $throughput): array {
            try {
                return [
                    'summary' => [
                        'jobsPastMinute' => $throughput['jobsPastMinute'],
                        'jobsPastHour' => $throughput['jobsPastHour'],
                        'failedPastSevenDays' => $throughput['failedPastSevenDays'],
                        'failureRate24h' => $this->metrics->getFailureRate24h($serviceIds),
                    ],
                    'jobRuntimesLast24h' => $this->metrics->getJobRuntimesLast24h($serviceIds),
                    'failureRateOverTime' => $this->metrics->getFailureRateOverTime($serviceIds),
                    'jobsVolumeLast24h' => $this->metrics->getJobsVolumeLast24h($serviceIds),
                    'workload' => $this->metrics->getWorkloadData($serviceIds),
                    'supervisors' => $this->metrics->getSupervisorsData($serviceIds),
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
