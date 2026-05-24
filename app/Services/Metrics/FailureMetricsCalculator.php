<?php

namespace App\Services\Metrics;

use App\Models\Service;
use Carbon\Carbon;

class FailureMetricsCalculator extends HorizonMetricsComputation
{
    /**
     * Get the failure rate from 00:00 of the previous day until now.
     *
     * @param array<string, mixed> $serviceScope The service scope.
     *
     * @return array{rate: float, processed: int, failed: int}
     */
    public function getFailureRate24h(array $serviceScope = []): array
    {
        $since = \now()->subDay()->startOfDay();
        $sinceTimestamp = $since->getTimestamp();

        $services = $this->private__getServicesForMetrics($serviceScope);

        if ($services->isEmpty()) {
            return [
                'rate' => 0.0,
                'processed' => 0,
                'failed' => 0,
            ];
        }

        $processed = 0;
        $failed = 0;

        /** @var Service $service */
        foreach ($services as $service) {
            $completedJobs = $this->jobsWindowFetcher->fetchCompletedJobsSince($service, $sinceTimestamp);
            $processed += \count($completedJobs);

            $failedJobs = $this->private__fetchFailedJobsInWindow($service, $sinceTimestamp);
            $failed += \count($failedJobs);
        }

        $total = $processed + $failed;
        $rate = $total > 0 ? \round(100 * $failed / $total, 1) : 0.0;

        return [
            'rate' => $rate,
            'processed' => $processed,
            'failed' => $failed,
        ];
    }

    /**
     * Get the failure rate over time from 00:00 of the previous day until now.
     *
     * @param array<string, mixed> $serviceScope The service scope.
     *
     * @return array{xAxis: list<string>, rate: list<float|null>}
     */
    public function getFailureRateOverTime(array $serviceScope = []): array
    {
        $now = \now();
        $since = $now->copy()->subDay()->startOfDay();
        $sinceTimestamp = $since->getTimestamp();
        $bucketFormat = 'Y-m-d H:00';
        $endHour = $now->copy()->startOfHour();

        $buckets = $this->private__initHourlyBuckets(
            $since,
            $endHour,
            $bucketFormat,
            48,
            static function (): array {
                return ['processed' => 0, 'failed' => 0];
            },
        );

        $services = $this->private__getServicesForMetrics($serviceScope);

        if ($services->isEmpty()) {
            return ['xAxis' => [], 'rate' => []];
        }

        /** @var Service $service */
        foreach ($services as $service) {
            $completedJobs = $this->jobsWindowFetcher->fetchCompletedJobsSince($service, $sinceTimestamp);

            $this->private__incrementHourlyBuckets($buckets, $completedJobs, 'completed_at', 'processed', $sinceTimestamp, $bucketFormat);

            $failedJobs = $this->private__fetchFailedJobsInWindow($service, $sinceTimestamp);
            $this->private__incrementHourlyBuckets($buckets, $failedJobs, 'failed_at', 'failed', $sinceTimestamp, $bucketFormat);
        }

        $xAxis = [];
        $series = [];

        foreach ($buckets as $k => $v) {
            $xAxis[] = Carbon::parse($k)->format('d/m H:i');
            $total = $v['processed'] + $v['failed'];

            if ($total > 0) {
                $series[] = \round(100 * $v['failed'] / $total, 1);
            } else {
                $series[] = null;
            }
        }

        return ['xAxis' => $xAxis, 'rate' => $series];
    }
}
