<?php

namespace App\Services\Metrics;

use App\Models\Service;
use App\Support\Horizon\HorizonStatsReader;
use Illuminate\Support\Collection;

class JobsThroughputMetricsCalculator extends HorizonMetricsComputation
{
    /**
     * Get the number of failed jobs in the past seven days.
     *
     * @param Service|null $service The service.
     */
    public function getFailedPastSevenDays(?Service $service = null): int
    {
        return $this->private__sumStatsField($service, 'failedJobs');
    }

    /**
     * Get the number of jobs processed in the past hour.
     *
     * @param Service|null $service The service.
     */
    public function getJobsPastHour(?Service $service = null): int
    {
        return $this->private__sumStatsField($service, 'recentJobs');
    }

    /**
     * Get jobs past hour per service using Horizon HTTP API stats.
     *
     * @return array{services: list<string>, jobsPastHour: list<int>}
     */
    public function getJobsPastHourByService(): array
    {
        /** @var Collection<int, Service> $services */
        $services = $this->private__getServicesForMetrics([], true, ['id', 'name', 'base_url']);

        if ($services->isEmpty()) {
            return ['services' => [], 'jobsPastHour' => []];
        }

        $names = [];
        $values = [];

        /** @var Service $service */
        foreach ($services as $service) {
            $data = HorizonStatsReader::dataFromResponse($this->horizonApi->getStats($service));

            if ($data === null || ! isset($data['recentJobs'])) {
                continue;
            }

            $names[] = (string) $service->name;
            $values[] = HorizonStatsReader::recentJobs($data);
        }

        return ['services' => $names, 'jobsPastHour' => $values];
    }

    /**
     * Get the number of jobs processed in the past minute.
     *
     * @param Service|null $service The service.
     */
    public function getJobsPastMinute(?Service $service = null): int
    {
        if ($service !== null) {
            $data = HorizonStatsReader::dataFromResponse($this->horizonApi->getStats($service));

            return $data !== null ? HorizonStatsReader::jobsPastMinute($data) : 0;
        }

        /** @var Collection<int, Service> $services */
        $services = $this->private__getServicesForMetrics();

        if ($services->isEmpty()) {
            return 0;
        }

        $total = 0;

        foreach ($services as $svc) {
            $data = HorizonStatsReader::dataFromResponse($this->horizonApi->getStats($svc));

            if ($data !== null) {
                $total += HorizonStatsReader::jobsPastMinute($data);
            }
        }

        return $total;
    }

    /**
     * @param 'failedJobs'|'recentJobs' $field
     */
    private function private__sumStatsField(?Service $service, string $field): int
    {
        if ($service !== null) {
            $data = HorizonStatsReader::dataFromResponse($this->horizonApi->getStats($service));

            if ($data === null || ! isset($data[$field])) {
                return 0;
            }

            return $field === 'failedJobs'
                ? HorizonStatsReader::failedJobs($data)
                : HorizonStatsReader::recentJobs($data);
        }

        /** @var Collection<int, Service> $services */
        $services = $this->private__getServicesForMetrics();

        if ($services->isEmpty()) {
            return 0;
        }

        $total = 0;

        foreach ($services as $svc) {
            $data = HorizonStatsReader::dataFromResponse($this->horizonApi->getStats($svc));

            if ($data === null || ! isset($data[$field])) {
                continue;
            }

            $total += $field === 'failedJobs'
                ? HorizonStatsReader::failedJobs($data)
                : HorizonStatsReader::recentJobs($data);
        }

        return $total;
    }
}
