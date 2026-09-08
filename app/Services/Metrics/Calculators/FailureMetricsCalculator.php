<?php

namespace App\Services\Metrics\Calculators;

use App\Models\Service;

final class FailureMetricsCalculator extends AbstractMetricsCalculator
{
    /**
     * Get the failure rate from 00:00 of the previous day until now.
     *
     * @param list<int> $serviceIds The service IDs.
     *
     * @return array{rate: float, processed: int, failed: int}
     */
    public function getFailureRate24h(array $serviceIds = []): array
    {
        $since = \now()->subDay()->startOfDay();
        $sinceTimestamp = $since->getTimestamp();

        $services = Service::getServices($serviceIds);

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

            $failedJobs = $this->jobsWindowFetcher->fetchFailedJobsSince($service, $sinceTimestamp);
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
     * @param list<int> $serviceIds The service IDs.
     *
     * @return array{xAxis: list<string>, rate: list<float|null>}
     */
    public function getFailureRateOverTime(array $serviceIds = []): array
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

        $services = Service::getServices($serviceIds);

        if ($services->isEmpty()) {
            return ['xAxis' => [], 'rate' => []];
        }

        $this->private__accumulateCompletedFailedHourlyBuckets(
            $buckets,
            $services,
            $sinceTimestamp,
            $bucketFormat,
            'processed',
            'failed',
        );

        $xAxis = $this->private__hourlyAxisLabels($buckets);
        $series = [];

        foreach ($buckets as $v) {
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
