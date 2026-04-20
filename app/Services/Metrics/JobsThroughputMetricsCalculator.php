<?php

namespace App\Services\Metrics;

use App\Models\Service;
use Illuminate\Support\Collection;

class JobsThroughputMetricsCalculator extends HorizonMetricsComputation
{
    /**
     * Get the number of failed jobs in the past seven days.
     *
     * @param  Service|null  $service  The service.
     */
    public function getFailedPastSevenDays(?Service $service = null): int
    {
        if ($service !== null && $service->getBaseUrl()) {
            $response = $this->horizonApi->getStats($service);
            $data = $response['data'] ?? null;

            if (($response['success'] ?? false) && \is_array($data) && isset($data['failedJobs'])) {
                return (int) $data['failedJobs'];
            }

            return 0;
        }

        /** @var Collection<int, Service> $services */
        $services = $this->private__getServicesForMetrics();

        if ($services->isEmpty()) {
            return 0;
        }

        $total = 0;
        foreach ($services as $svc) {
            $response = $this->horizonApi->getStats($svc);
            $data = $response['data'] ?? null;

            if (! (($response['success'] ?? false) && \is_array($data) && isset($data['failedJobs']))) {
                continue;
            }

            $total += (int) $data['failedJobs'];
        }

        return $total;
    }

    /**
     * Get the number of jobs processed in the past hour.
     *
     * @param  Service|null  $service  The service.
     */
    public function getJobsPastHour(?Service $service = null): int
    {
        if ($service !== null && $service->getBaseUrl()) {
            $response = $this->horizonApi->getStats($service);
            $data = $response['data'] ?? null;

            if (($response['success'] ?? false) && \is_array($data) && isset($data['recentJobs'])) {
                return (int) $data['recentJobs'];
            }

            return 0;
        }

        /** @var Collection<int, Service> $services */
        $services = $this->private__getServicesForMetrics();

        if ($services->isEmpty()) {
            return 0;
        }

        $total = 0;
        foreach ($services as $svc) {
            $response = $this->horizonApi->getStats($svc);
            $data = $response['data'] ?? null;

            if (! (($response['success'] ?? false) && \is_array($data) && isset($data['recentJobs']))) {
                continue;
            }

            $total += (int) $data['recentJobs'];
        }

        return $total;
    }

    /**
     * Get jobs past hour per service using Horizon HTTP API stats.
     *
     * @return array{services: list<string>, jobsPastHour: list<int>}
     */
    public function getJobsPastHourByService(): array
    {
        /** @var Collection<int, Service> $services */
        $services = $this->private__getServicesForMetrics(null, true, ['id', 'name', 'base_url']);

        if ($services->isEmpty()) {
            return ['services' => [], 'jobsPastHour' => []];
        }

        $names = [];
        $values = [];

        /** @var Service $service */
        foreach ($services as $service) {
            $response = $this->horizonApi->getStats($service);
            $data = $response['data'] ?? null;

            if (! (($response['success'] ?? false) && \is_array($data) && isset($data['recentJobs']))) {
                continue;
            }

            $names[] = (string) $service->name;
            $values[] = (int) $data['recentJobs'];
        }

        return ['services' => $names, 'jobsPastHour' => $values];
    }

    /**
     * Get the number of jobs processed in the past minute.
     *
     * @param  Service|null  $service  The service.
     */
    public function getJobsPastMinute(?Service $service = null): int
    {
        if ($service !== null && $service->getBaseUrl()) {
            $response = $this->horizonApi->getStats($service);
            $data = $response['data'] ?? null;

            if (($response['success'] ?? false) && \is_array($data)) {
                $jobs_per_minute = isset($data['jobsPerMinute']) ? (float) $data['jobsPerMinute'] : 0.0;
                if ($jobs_per_minute > 0) {
                    return (int) \round($jobs_per_minute);
                }
                $recent = isset($data['recentJobs']) ? (int) $data['recentJobs'] : 0;
                $period = isset($data['periods']['recentJobs']) ? (int) $data['periods']['recentJobs'] : 60;
                if ($recent >= 0 && $period > 0) {
                    return (int) \round($recent / $period);
                }
            }

            return 0;
        }

        /** @var Collection<int, Service> $services */
        $services = $this->private__getServicesForMetrics();

        if ($services->isEmpty()) {
            return 0;
        }

        $total = 0;
        foreach ($services as $svc) {
            $total += $this->getJobsPastMinute($svc);
        }

        return $total;
    }
}
