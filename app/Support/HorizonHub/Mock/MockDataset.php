<?php

namespace App\Support\HorizonHub\Mock;

final class MockDataset
{
    public const PINNED_JOB_UUID = '763dc9c2-a7cd-4b95-9da5-77beff5c264e';

    private static ?array $config = null;

    /**
     * @return array{
     *     catalog: array<string, mixed>,
     *     horizon: array<int, array<string, mixed>>,
     *     job_service_index: array<string, int>
     * }
     */
    public static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $volumes = self::volumes();
        $catalog = (new CatalogBuilder($volumes))->build();
        $horizon = (new HorizonFixtureBuilder($catalog, $volumes['jobs_per_status']))->build();

        self::$config = [
            'catalog' => $catalog,
            'horizon' => $horizon,
            'job_service_index' => HorizonFixtureBuilder::jobServiceIndex($horizon),
        ];

        return self::$config;
    }

    /**
     * @return array{
     *     service_count: int,
     *     provider_count: int,
     *     alert_count: int,
     *     alert_log_count: int,
     *     jobs_per_status: int
     * }
     */
    public static function jobUuid(int $serviceId, int $index): string
    {
        $a = ($serviceId * 100003) ^ ($index * 9973);
        $b = ($serviceId << 12) | ($index & 0xFFF);

        return \sprintf(
            '%08x-%04x-4%03x-%04x-%012x',
            $a & 0xFFFFFFFF,
            ($b >> 16) & 0xFFFF,
            ($b >> 4) & 0x0FFF,
            (($b << 2) & 0x3FFF) | 0x8000,
            (($a * 1103515245 + $b) & 0xFFFFFFFFFFFF),
        );
    }

    public static function volumes(): array
    {
        $pageCap = (int) config('horizonhub.max_horizon_pages', 8)
            * (int) config('horizonhub.horizon_api_job_list_page_size', 25);

        $jobsPerStatus = \min(
            \max(5, (int) env('MOCK_JOBS_PER_STATUS', env('DEMO_JOBS_PER_STATUS', 15))),
            \max(5, $pageCap),
        );

        return [
            'service_count' => \max(3, (int) env('MOCK_SERVICE_COUNT', env('DEMO_SERVICE_COUNT', 96))),
            'provider_count' => \max(3, (int) env('MOCK_PROVIDER_COUNT', env('DEMO_PROVIDER_COUNT', 30))),
            'alert_count' => \max(2, (int) env('MOCK_ALERT_COUNT', env('DEMO_ALERT_COUNT', 64))),
            'alert_log_count' => \max(5, (int) env('MOCK_ALERT_LOG_COUNT', env('DEMO_ALERT_LOG_COUNT', 1200))),
            'jobs_per_status' => $jobsPerStatus,
        ];
    }
}
