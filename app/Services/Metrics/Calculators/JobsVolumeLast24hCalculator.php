<?php

namespace App\Services\Metrics\Calculators;

final class JobsVolumeLast24hCalculator extends AbstractMetricsCalculator
{
    /**
     * Hourly completed and failed job counts over the rolling last 24 hours.
     *
     * @param list<int> $serviceIds The service IDs.
     *
     * @return array{xAxis: list<string>, completed: list<int>, failed: list<int>}
     */
    public function getJobsVolumeLast24h(array $serviceIds = []): array
    {
        $result = $this->private__buildHourlyCompletedFailedBuckets(
            $serviceIds,
            \now()->copy()->subHours(24)->startOfHour(),
            25,
            static function (): array {
                return ['completed' => 0, 'failed' => 0];
            },
            'completed',
            'failed',
        );

        if ($result === null) {
            return ['xAxis' => [], 'completed' => [], 'failed' => []];
        }

        $completedSeries = [];
        $failedSeries = [];

        foreach ($result['buckets'] as $v) {
            $completedSeries[] = $v['completed'];
            $failedSeries[] = $v['failed'];
        }

        return [
            'xAxis' => $result['xAxis'],
            'completed' => $completedSeries,
            'failed' => $failedSeries,
        ];
    }
}
