<?php

namespace App\Services\Metrics;

use App\Models\Service;

class RuntimeMetricsCalculator extends HorizonMetricsComputation
{
    /**
     * Per-job runtimes over the rolling last 24 hours (completed and failed), for scatter charts.
     *
     * @return array{points: list<array{endAtMs: int, seconds: float, name: string, service: string, status: string}>}
     */
    public function getJobRuntimesLast24h(array $serviceScope = []): array
    {
        $now = \now();
        $sinceTimestamp = $now->copy()->subHours(24)->getTimestamp();

        $services = $this->private__getServicesForMetrics($serviceScope);
        if ($services->isEmpty()) {
            return ['points' => []];
        }

        $points = [];

        /** @var Service $service */
        foreach ($services as $service) {
            $serviceName = (string) $service->name;

            $completedJobs = $this->private__fetchCompletedJobsInWindow($service, $sinceTimestamp);
            foreach ($completedJobs as $job) {
                if (! \is_array($job)) {
                    continue;
                }

                $queuedAt = $job['reserved_at'] ?? null;
                $completedAt = $job['completed_at'] ?? $job['processed_at'] ?? null;
                if (! \is_numeric($queuedAt) || ! \is_numeric($completedAt)) {
                    continue;
                }

                $start = (int) $queuedAt;
                $end = (int) $completedAt;
                if ($end < $sinceTimestamp || $end <= $start) {
                    continue;
                }

                $points[] = [
                    'endAtMs' => $end * 1000,
                    'seconds' => \round((float) ($end - $start), 2),
                    'name' => (string) ($job['name'] ?? ''),
                    'service' => $serviceName,
                    'status' => 'completed',
                ];
            }

            $failedJobs = $this->private__fetchFailedJobsInWindow($service, $sinceTimestamp);
            foreach ($failedJobs as $job) {
                if (! \is_array($job)) {
                    continue;
                }

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

                $points[] = [
                    'endAtMs' => $end * 1000,
                    'seconds' => \round((float) ($end - $start), 2),
                    'name' => (string) ($job['name'] ?? ''),
                    'service' => $serviceName,
                    'status' => 'failed',
                ];
            }
        }

        \usort($points, static function (array $a, array $b): int {
            return $a['endAtMs'] <=> $b['endAtMs'];
        });

        return ['points' => $points];
    }
}
