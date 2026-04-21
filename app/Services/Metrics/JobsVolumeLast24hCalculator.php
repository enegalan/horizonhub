<?php

namespace App\Services\Metrics;

use App\Models\Service;
use Carbon\Carbon;

class JobsVolumeLast24hCalculator extends HorizonMetricsComputation
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
            $completedJobs = $this->private__fetchCompletedJobsInWindow($service, $sinceTimestamp);

            foreach ($completedJobs as $job) {
                $completedAt = $job['completed_at'] ?? $job['processed_at'] ?? null;

                if (! \is_numeric($completedAt)) {
                    continue;
                }

                $ts = (int) $completedAt;

                if ($ts < $sinceTimestamp) {
                    continue;
                }

                $bucket = Carbon::createFromTimestamp($ts)->format($bucketFormat);

                if (isset($buckets[$bucket])) {
                    $buckets[$bucket]['completed']++;
                }
            }

            $failedJobs = $this->private__fetchFailedJobsInWindow($service, $sinceTimestamp);

            foreach ($failedJobs as $job) {
                $failedAt = $job['failed_at'] ?? null;

                if (! \is_numeric($failedAt)) {
                    continue;
                }

                $ts = (int) $failedAt;

                if ($ts < $sinceTimestamp) {
                    continue;
                }

                $bucket = Carbon::createFromTimestamp($ts)->format($bucketFormat);

                if (isset($buckets[$bucket])) {
                    $buckets[$bucket]['failed']++;
                }
            }
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
