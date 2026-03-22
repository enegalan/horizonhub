<?php

namespace App\Services\Metrics;

use App\Models\Service;
use Carbon\Carbon;

class FailureMetricsCalculator extends HorizonMetricsComputation
{
    /**
     * Get the failure rate from 00:00 of the previous day until now.
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
            $completedJobs = $this->private__fetchCompletedJobsInWindow($service, $sinceTimestamp);
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
            $completedJobs = $this->private__fetchCompletedJobsInWindow($service, $sinceTimestamp);
            foreach ($completedJobs as $job) {
                $completedAt = $job['completed_at'] ?? $job['processed_at'] ?? null;
                if (! \is_numeric($completedAt)) {
                    continue;
                }

                $ts = (int) $completedAt;
                $bucket = Carbon::createFromTimestamp($ts)->format($bucketFormat);
                if (isset($buckets[$bucket])) {
                    $buckets[$bucket]['processed']++;
                }
            }

            $failedJobs = $this->private__fetchFailedJobsInWindow($service, $sinceTimestamp);
            foreach ($failedJobs as $job) {
                $failedAt = $job['failed_at'] ?? null;
                if (! \is_numeric($failedAt)) {
                    continue;
                }

                $ts = (int) $failedAt;
                $bucket = Carbon::createFromTimestamp($ts)->format($bucketFormat);
                if (isset($buckets[$bucket])) {
                    $buckets[$bucket]['failed']++;
                }
            }
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
