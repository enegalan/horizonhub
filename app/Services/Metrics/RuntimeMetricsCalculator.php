<?php

namespace App\Services\Metrics;

use App\Models\Service;
use Carbon\Carbon;

class RuntimeMetricsCalculator extends HorizonMetricsComputation
{
    /**
     * Get the average runtime over time from 00:00 of the previous day until now.
     *
     * @return array{xAxis: list<string>, avgSeconds: list<float|null>}
     */
    public function getAvgRuntimeOverTime(array $serviceScope = []): array
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
                return ['sum' => 0.0, 'count' => 0];
            },
        );

        $services = $this->private__getServicesForMetrics($serviceScope);
        if ($services->isEmpty()) {
            return ['xAxis' => [], 'avgSeconds' => []];
        }

        $jobsLimit = (int) \config('horizonhub.metrics_24h_jobs_limit', 500);

        /** @var Service $service */
        foreach ($services as $service) {
            $completedJobs = $this->private__fetchCompletedJobsInWindow($service, $sinceTimestamp, $jobsLimit);
            foreach ($completedJobs as $job) {
                $queuedAt = $job['reserved_at'] ?? null;
                $completedAt = $job['completed_at'] ?? null;
                if (! \is_numeric($queuedAt) || ! \is_numeric($completedAt)) {
                    continue;
                }

                $start = (int) $queuedAt;
                $end = (int) $completedAt;
                if ($end < $sinceTimestamp || $end <= $start) {
                    continue;
                }

                $bucket = Carbon::createFromTimestamp($end)->format($bucketFormat);
                if (isset($buckets[$bucket])) {
                    $buckets[$bucket]['sum'] += (float) ($end - $start);
                    $buckets[$bucket]['count']++;
                }
            }

            $failedJobs = $this->private__fetchFailedJobsInWindow($service, $sinceTimestamp, $jobsLimit);
            foreach ($failedJobs as $job) {
                $queuedAt = $job['reserved_at'] ?? null;
                $failedAt = $job['failed_at'] ?? null;
                if (! \is_numeric($queuedAt) || ! \is_numeric($failedAt)) {
                    continue;
                }

                $start = (int) $queuedAt;
                $end = (int) $failedAt;
                if ($end < $sinceTimestamp || $end <= $start) {
                    continue;
                }

                $bucket = Carbon::createFromTimestamp($end)->format($bucketFormat);
                if (isset($buckets[$bucket])) {
                    $buckets[$bucket]['sum'] += (float) ($end - $start);
                    $buckets[$bucket]['count']++;
                }
            }
        }

        $xAxis = [];
        $series = [];

        foreach ($buckets as $k => $v) {
            $xAxis[] = Carbon::parse($k)->format('d/m H:i');
            $series[] = $v['count'] > 0 ? \round($v['sum'] / $v['count'], 2) : null;
        }

        return ['xAxis' => $xAxis, 'avgSeconds' => $series];
    }
}
