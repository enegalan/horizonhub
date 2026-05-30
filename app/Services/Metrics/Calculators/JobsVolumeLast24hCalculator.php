<?php

namespace App\Services\Metrics\Calculators;

use App\Models\Service;
use Carbon\Carbon;

final class JobsVolumeLast24hCalculator extends AbstractMetricsCalculator
{
    /**
     * Hourly completed and failed job counts over the rolling last 24 hours.
     *
     * @param array<string, mixed> $serviceScope The service scope.
     *
     * @return array{xAxis: list<string>, completed: list<int>, failed: list<int>}
     */
    public function getJobsVolumeLast24h(array $serviceScope = []): array
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

        $services = $this->private__getServicesForMetrics($serviceScope);

        if ($services->isEmpty()) {
            return ['xAxis' => [], 'completed' => [], 'failed' => []];
        }

        /** @var Service $service */
        foreach ($services as $service) {
            $completedJobs = $this->jobsWindowFetcher->fetchCompletedJobsSince($service, $sinceTimestamp);

            $this->private__incrementHourlyBuckets($buckets, $completedJobs, 'completed_at', 'completed', $sinceTimestamp, $bucketFormat);

            $failedJobs = $this->private__fetchFailedJobsInWindow($service, $sinceTimestamp);
            $this->private__incrementHourlyBuckets($buckets, $failedJobs, 'failed_at', 'failed', $sinceTimestamp, $bucketFormat);
        }

        $xAxis = [];
        $completedSeries = [];
        $failedSeries = [];

        foreach ($buckets as $k => $v) {
            $xAxis[] = Carbon::parse($k)->format('d/m H:i');
            $completedSeries[] = $v['completed'];
            $failedSeries[] = $v['failed'];
        }

        return [
            'xAxis' => $xAxis,
            'completed' => $completedSeries,
            'failed' => $failedSeries,
        ];
    }
}
