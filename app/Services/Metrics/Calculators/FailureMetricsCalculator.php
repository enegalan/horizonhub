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
        $sinceTimestamp = \now()->subDay()->startOfDay()->getTimestamp();
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
        $result = $this->private__buildHourlyCompletedFailedBuckets(
            $serviceIds,
            \now()->copy()->subDay()->startOfDay(),
            48,
            static function (): array {
                return ['processed' => 0, 'failed' => 0];
            },
            'processed',
            'failed',
        );

        if ($result === null) {
            return ['xAxis' => [], 'rate' => []];
        }

        $series = [];

        foreach ($result['buckets'] as $v) {
            $total = $v['processed'] + $v['failed'];

            if ($total > 0) {
                $series[] = \round(100 * $v['failed'] / $total, 1);
            } else {
                $series[] = null;
            }
        }

        return ['xAxis' => $result['xAxis'], 'rate' => $series];
    }
}
