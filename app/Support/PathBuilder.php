<?php

namespace App\Support;

use App\Models\Service;

final class PathBuilder
{
    /**
     * Build the API URL.
     *
     * @param Service $service The service.
     * @param string $path The path.
     *
     * @return string The API URL.
     */
    public static function api(Service $service, string $path): string
    {
        return $service->base_url . config('horizonhub.horizon_paths.api') . '/' . \ltrim($path, '/');
    }

    /**
     * Build the completed jobs URL.
     *
     * @param array<string, mixed> $query The query parameters.
     *
     * @return string The completed jobs URL.
     */
    public static function completedJobs(array $query = []): string
    {
        return config('horizonhub.horizon_paths.completed_jobs') . '?' . self::private__buildJobListQuery($query);
    }

    /**
     * Build the dashboard URL.
     *
     * @param Service $service The service.
     *
     * @return string The dashboard URL.
     */
    public static function dashboard(Service $service, bool $public = true): string
    {
        return ($public ? $service->public_url : $service->base_url) . config('horizonhub.horizon_paths.dashboard');
    }

    /**
     * Build the failed jobs URL.
     *
     * @param array<string, mixed> $query The query parameters.
     *
     * @return string The failed jobs URL.
     */
    public static function failedJobs(array $query = []): string
    {
        return config('horizonhub.horizon_paths.failed_jobs') . '?' . self::private__buildJobListQuery($query);
    }

    /**
     * Build the job URL.
     *
     * @param string $jobUuid The job UUID.
     *
     * @return string The job URL.
     */
    public static function job(string $jobUuid): string
    {
        return \str_replace('{id}', $jobUuid, config('horizonhub.horizon_paths.job'));
    }

    /**
     * Build the job dashboard URL.
     *
     * @param Service|null $service The service.
     * @param string|null $jobUuid The job UUID.
     * @param string|null $jobStatus The job status.
     *
     * @return string|null The job dashboard URL.
     */
    public static function jobDashboard(?Service $service, ?string $jobUuid, ?string $jobStatus): ?string
    {
        if ($service === null || blank($jobUuid)) {
            return null;
        }

        $encodedUuid = \urlencode($jobUuid);

        $jobPath = match ((string) $jobStatus) {
            'processing', 'pending', 'reserved' => config('horizonhub.horizon_paths.pending_jobs') . "/$encodedUuid",
            'processed', 'completed' => config('horizonhub.horizon_paths.completed_jobs') . "/$encodedUuid",
            'failed' => config('horizonhub.horizon_paths.failed_jobs_dashboard') . "/$encodedUuid",
            default => config('horizonhub.horizon_paths.pending_jobs') . "/$encodedUuid",
        };

        return PathBuilder::dashboard($service) . $jobPath;
    }

    /**
     * Build the masters URL.
     *
     * @return string The masters URL.
     */
    public static function masters(): string
    {
        return config('horizonhub.horizon_paths.masters');
    }

    /**
     * Build the pending jobs URL.
     *
     * @param array<string, mixed> $query The query parameters.
     *
     * @return string The pending jobs URL.
     */
    public static function pendingJobs(array $query = []): string
    {
        return config('horizonhub.horizon_paths.pending_jobs') . '?' . self::private__buildJobListQuery($query);
    }

    /**
     * Build the ping URL.
     *
     * @return string The ping URL.
     */
    public static function ping(): string
    {
        return config('horizonhub.horizon_paths.ping');
    }

    /**
     * Build the retry job URL.
     *
     * @param string $jobUuid The job UUID.
     *
     * @return string The retry job URL.
     */
    public static function retryJob(string $jobUuid): string
    {
        return \str_replace('{id}', $jobUuid, config('horizonhub.horizon_paths.retry'));
    }

    /**
     * Build the workload URL.
     *
     * @return string The workload URL.
     */
    public static function workload(): string
    {
        return config('horizonhub.horizon_paths.workload');
    }

    /**
     * Build the job list query.
     *
     * @param array<string, mixed> $overrides The overrides.
     *
     * @return string The job list query.
     */
    private static function private__buildJobListQuery(array $overrides = []): string
    {
        return \http_build_query([
            'starting_at' => 0,
            'limit' => (int) config('horizonhub.horizon_api_job_list_page_size'),
            ...$overrides,
        ]);
    }
}
