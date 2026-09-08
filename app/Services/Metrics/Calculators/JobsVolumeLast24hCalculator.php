<?php

namespace App\Services\Metrics\Calculators;

use App\Models\Service;

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
        $now = \now();
        $sinceBucketStart = $now->copy()->subHours(24)->startOfHour();
        $sinceTimestamp = $now->copy()->subHours(24)->getTimestamp();
        $bucketFormat = 'Y-m-d H:00';
        $endHour = $now->copy()->startOfHour();

        $buckets = $this->private__initHourlyBuckets(
            $sinceBucketStart,
            $endHour,
            $bucketFormat,
            25,
            static function (): array {
                return ['completed' => 0, 'failed' => 0];
            },
        );

        $services = Service::getServices($serviceIds);

        if ($services->isEmpty()) {
            return ['xAxis' => [], 'completed' => [], 'failed' => []];
        }

        $this->private__accumulateCompletedFailedHourlyBuckets(
            $buckets,
            $services,
            $sinceTimestamp,
            $bucketFormat,
            'completed',
            'failed',
        );

        $completedSeries = [];
        $failedSeries = [];

        foreach ($buckets as $v) {
            $completedSeries[] = $v['completed'];
            $failedSeries[] = $v['failed'];
        }

        return [
            'xAxis' => $this->private__hourlyAxisLabels($buckets),
            'completed' => $completedSeries,
            'failed' => $failedSeries,
        ];
    }
}
