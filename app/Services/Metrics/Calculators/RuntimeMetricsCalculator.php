<?php

namespace App\Services\Metrics\Calculators;

use App\Models\Service;
use App\Support\Jobs\JobRuntime;

final class RuntimeMetricsCalculator extends AbstractMetricsCalculator
{
    /**
     * Per-job runtimes over the rolling last 24 hours (completed and failed), for scatter charts.
     *
     * @param list<int> $serviceIds The service IDs.
     *
     * @return array{points: list<array{endAtMs: int, seconds: float, name: string, service: string, status: string}>}
     */
    public function getJobRuntimesLast24h(array $serviceIds = []): array
    {
        $now = \now();
        $sinceTimestamp = $now->copy()->subHours(24)->getTimestamp();

        $services = Service::getServices($serviceIds);

        if ($services->isEmpty()) {
            return ['points' => []];
        }

        $points = [];

        /** @var Service $service */
        foreach ($services as $service) {
            $serviceName = (string) $service->name;

            $this->private__appendRuntimePoints(
                $points,
                $this->jobsWindowFetcher->fetchCompletedJobsSince($service, $sinceTimestamp),
                $serviceName,
                $sinceTimestamp,
                'completed_at',
                'completed',
            );

            $this->private__appendRuntimePoints(
                $points,
                $this->jobsWindowFetcher->fetchFailedJobsSince($service, $sinceTimestamp),
                $serviceName,
                $sinceTimestamp,
                'failed_at',
                'failed',
            );
        }

        \usort($points, static function (array $a, array $b): int {
            return $a['endAtMs'] <=> $b['endAtMs'];
        });

        return ['points' => $points];
    }

    /**
     * Append runtime points to the points array.
     *
     * @param list<array{endAtMs: int, seconds: float, name: string, service: string, status: string}> $points
     * @param list<array<string, mixed>> $jobs
     * @param string $serviceName The service name.
     * @param int $sinceTimestamp The since timestamp.
     * @param string $endField The end field.
     * @param string $status The status.
     */
    private function private__appendRuntimePoints(
        array &$points,
        array $jobs,
        string $serviceName,
        int $sinceTimestamp,
        string $endField,
        string $status,
    ): void {
        foreach ($jobs as $job) {
            $reservedAt = JobRuntime::parseJobTimestamp($job['reserved_at'] ?? null);
            $endedAt = JobRuntime::parseJobTimestamp($job[$endField] ?? null);
            $seconds = JobRuntime::getRuntimeSeconds(null, $reservedAt, $endedAt, null);

            if ($reservedAt === null || $endedAt === null || $seconds === null) {
                continue;
            }

            $end = $endedAt->getTimestamp();

            if ($end < $sinceTimestamp) {
                continue;
            }

            $points[] = [
                'endAtMs' => $end * 1000,
                'seconds' => \round($seconds, 2),
                'name' => (string) ($job['name'] ?? ''),
                'service' => $serviceName,
                'status' => $status,
            ];
        }
    }
}
