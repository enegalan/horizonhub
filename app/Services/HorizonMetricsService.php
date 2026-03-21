<?php

namespace App\Services;

use App\Models\Service;
use App\Services\Metrics\FailureMetricsCalculator;
use App\Services\Metrics\JobsThroughputMetricsCalculator;
use App\Services\Metrics\QueueFailureCountersCalculator;
use App\Services\Metrics\RuntimeMetricsCalculator;
use App\Services\Metrics\WorkloadMetricsCalculator;

class HorizonMetricsService
{
    /**
     * The jobs throughput metrics calculator.
     */
    private JobsThroughputMetricsCalculator $jobsThroughputMetrics;

    /**
     * The workload metrics calculator.
     */
    private WorkloadMetricsCalculator $workloadMetrics;

    /**
     * The failure metrics calculator.
     */
    private FailureMetricsCalculator $failureMetrics;

    /**
     * The runtime metrics calculator.
     */
    private RuntimeMetricsCalculator $runtimeMetrics;

    /**
     * The queue failure counters calculator.
     */
    private QueueFailureCountersCalculator $queueFailureCounters;

    /**
     * Construct the Horizon metrics service.
     */
    public function __construct(HorizonApiProxyService $horizonApi)
    {
        $this->jobsThroughputMetrics = new JobsThroughputMetricsCalculator($horizonApi);
        $this->workloadMetrics = new WorkloadMetricsCalculator($horizonApi);
        $this->failureMetrics = new FailureMetricsCalculator($horizonApi);
        $this->runtimeMetrics = new RuntimeMetricsCalculator($horizonApi);
        $this->queueFailureCounters = new QueueFailureCountersCalculator($horizonApi);
    }

    /**
     * Get the number of jobs processed in the past minute.
     */
    public function getJobsPastMinute(?Service $service = null): int
    {
        return $this->jobsThroughputMetrics->getJobsPastMinute($service);
    }

    /**
     * Get the number of jobs processed in the past hour.
     */
    public function getJobsPastHour(?Service $service = null): int
    {
        return $this->jobsThroughputMetrics->getJobsPastHour($service);
    }

    /**
     * Get the number of jobs failed in the past seven days.
     */
    public function getFailedPastSevenDays(?Service $service = null): int
    {
        return $this->jobsThroughputMetrics->getFailedPastSevenDays($service);
    }

    /**
     * Get the workload for a single service.
     *
     * @return array<int, array{queue: string, jobs: int, processes: int|null, wait: float|null}>
     */
    public function getWorkloadForService(Service $service): array
    {
        return $this->workloadMetrics->getWorkloadForService($service);
    }

    /**
     * Get the supervisors data for a single service.
     *
     * @return array<int, array{name: string, status: string, processes: int, last_heartbeat: string, options: array<string, mixed>}>
     */
    public function getSupervisorsData(?int $service_id = null): array
    {
        return $this->workloadMetrics->getSupervisorsData($service_id);
    }

    /**
     * Get the workload data for a single service.
     *
     * @return array<int, array{service_id: int, service: string, queue: string, jobs: int, processes: int|null, wait: float|null}>
     */
    public function getWorkloadData(?int $service_id = null): array
    {
        return $this->workloadMetrics->getWorkloadData($service_id);
    }

    /**
     * Get the failure rate from 00:00 of the previous day until now.
     *
     * @return array{rate: float, processed: int, failed: int}
     */
    public function getFailureRate24h(?int $service_id = null): array
    {
        return $this->failureMetrics->getFailureRate24h($service_id);
    }

    /**
     * Get the failure rate over time from 00:00 of the previous day until now.
     *
     * @return array{xAxis: list<string>, rate: list<float|null>}
     */
    public function getFailureRateOverTime(?int $service_id = null): array
    {
        return $this->failureMetrics->getFailureRateOverTime($service_id);
    }

    /**
     * Get the average runtime over time from 00:00 of the previous day until now.
     *
     * @return array{xAxis: list<string>, avgSeconds: list<float|null>}
     */
    public function getAvgRuntimeOverTime(?int $service_id = null): array
    {
        return $this->runtimeMetrics->getAvgRuntimeOverTime($service_id);
    }

    /**
     * Get the processed and failed jobs by queue.
     *
     * @return array{queue: string, processed: int, failed: int}
     */
    public function getProcessedFailedByQueue(?int $service_id = null): array
    {
        return $this->queueFailureCounters->getProcessedFailedByQueue($service_id);
    }

    /**
     * Get the number of jobs processed in the past hour by service.
     *
     * @return array<int, array{service_id: int, service: string, jobs: int}>
     */
    public function getJobsPastHourByService(): array
    {
        return $this->jobsThroughputMetrics->getJobsPastHourByService();
    }
}
