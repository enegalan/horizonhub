<?php

namespace App\Services\Metrics;

use App\Models\Service;

class QueueFailureCountersCalculator extends HorizonMetricsComputation
{
    /**
     * Get the processed vs failed data by queue.
     *
     * @param array<string, mixed> $serviceScope The service scope.
     *
     * @return array{queues: list<string>, processed: list<int>, failed: list<int>}
     */
    public function getProcessedFailedByQueue(array $serviceScope = []): array
    {
        $since = \now()->subDays(7);
        $sinceTimestamp = $since->getTimestamp();

        $services = $this->private__getServicesForMetrics($serviceScope);

        if ($services->isEmpty()) {
            return ['queues' => [], 'processed' => [], 'failed' => []];
        }

        $normalizedProcessed = [];
        $normalizedFailed = [];

        /** @var Service $service */
        foreach ($services as $service) {
            $completedJobs = $this->jobsWindowFetcher->fetchCompletedJobsSince($service, $sinceTimestamp);
            $this->private__aggregateQueueCountsFromJobsPayload($completedJobs, $sinceTimestamp, 'completed_at', $normalizedProcessed);

            $failedJobs = $this->private__fetchFailedJobsInWindow($service, $sinceTimestamp);
            $this->private__aggregateQueueCountsFromJobsPayload($failedJobs, $sinceTimestamp, 'failed_at', $normalizedFailed);
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
