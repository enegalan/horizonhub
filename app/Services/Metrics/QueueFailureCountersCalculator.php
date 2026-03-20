<?php

namespace App\Services\Metrics;

use App\Models\Service;

class QueueFailureCountersCalculator extends HorizonMetricsComputation {

    /**
     * Get the processed vs failed data by queue.
     *
     * @param int|null $service_id
     * @return array{queues: list<string>, processed: list<int>, failed: list<int>}
     */
    public function getProcessedFailedByQueue(?int $service_id = null): array {
        $since = \now()->subDays(self::DAYS_7);
        $sinceTimestamp = $since->getTimestamp();

        $services = $this->private__getServicesForMetrics($service_id);
        if ($services->isEmpty()) {
            return ['queues' => [], 'processed' => [], 'failed' => []];
        }

        $normalizedProcessed = [];
        $normalizedFailed = [];

        /** @var Service $service */
        foreach ($services as $service) {
            $completedResponse = $this->horizonApi->getCompletedJobs($service, [
                'starting_at' => 0,
            ]);
            $completedData = $completedResponse['data'] ?? null;

            if ($completedResponse['success'] ?? false && \is_array($completedData)) {
                $jobsPayload = $completedData['jobs'] ?? [];
                $this->private__aggregateQueueCountsFromJobsPayload($jobsPayload, $sinceTimestamp, 'completed_at', $normalizedProcessed);
            }

            $failedResponse = $this->horizonApi->getFailedJobs($service, [
                'starting_at' => 0,
            ]);
            $failedData = $failedResponse['data'] ?? null;

            if ($failedResponse['success'] ?? false && \is_array($failedData)) {
                $jobsPayload = $failedData['jobs'] ?? [];
                $this->private__aggregateQueueCountsFromJobsPayload($jobsPayload, $sinceTimestamp, 'failed_at', $normalizedFailed);
            }
        }

        $allQueues = \array_unique(\array_merge(\array_keys($normalizedProcessed), \array_keys($normalizedFailed)));
        $agg = [];
        foreach ($allQueues as $q) {
            $agg[$q] = [
                'processed' => $normalizedProcessed[$q] ?? 0,
                'failed' => $normalizedFailed[$q] ?? 0,
            ];
        }
        \uasort($agg, static fn ($a, $b) => ($b['processed'] + $b['failed']) <=> ($a['processed'] + $a['failed']));
        $agg = \array_slice($agg, 0, self::TOP_N_QUEUES, true);
        $queues = \array_keys($agg);

        $processed = [];
        $failed = [];
        foreach ($agg as $v) {
            $processed[] = $v['processed'];
            $failed[] = $v['failed'];
        }

        return ['queues' => $queues, 'processed' => $processed, 'failed' => $failed];
    }
}
