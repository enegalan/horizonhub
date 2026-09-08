<?php

namespace App\Services\Metrics\Calculators;

use App\Models\Service;
use App\Support\Horizon\ClientResponse;
use App\Support\Horizon\StatsReader;
use Illuminate\Support\Collection;

final class JobsThroughputMetricsCalculator extends AbstractMetricsCalculator
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
     * Get the number of jobs processed in the past minute.
     *
     * @param Service|null $service The service.
     */
    public function getJobsPastMinute(?Service $service = null): int
    {
        return $this->private__sumStatsField($service, 'jobsPastMinute');
    }

    /**
     * Aggregate minute / hour / failed-7d from one getStats call per service.
     *
     * @param Collection<int, Service>|null $services Null = all enabled services for metrics.
     *
     * @return array{jobsPastMinute: int, jobsPastHour: int, failedPastSevenDays: int}
     */
    public function getThroughputTotals(?Collection $services = null): array
    {
        if ($services === null) {
            $services = Service::getServices();
        }

        $minute = 0;
        $hour = 0;
        $failed = 0;

        /** @var Service $service */
        foreach ($services as $service) {
            $data = ClientResponse::data($this->horizonApi->getStats($service));
            $summary = StatsReader::summary($data);
            $minute += $summary['jobsPastMinute'];
            $hour += $summary['recentJobs'];
            $failed += $summary['failedJobs'];
        }

        return [
            'jobsPastMinute' => $minute,
            'jobsPastHour' => $hour,
            'failedPastSevenDays' => $failed,
        ];
    }

    /**
     * @param 'failedJobs'|'recentJobs'|'jobsPastMinute' $field
     */
    private function private__sumStatsField(?Service $service, string $field): int
    {
        if ($service !== null) {
            $data = ClientResponse::data($this->horizonApi->getStats($service));

            return match ($field) {
                'failedJobs' => StatsReader::failedJobs($data),
                'recentJobs' => StatsReader::recentJobs($data),
                'jobsPastMinute' => StatsReader::jobsPastMinute($data),
            };
        }

        $totals = $this->getThroughputTotals(null);

        return match ($field) {
            'failedJobs' => $totals['failedPastSevenDays'],
            'recentJobs' => $totals['jobsPastHour'],
            'jobsPastMinute' => $totals['jobsPastMinute'],
        };
    }
}
