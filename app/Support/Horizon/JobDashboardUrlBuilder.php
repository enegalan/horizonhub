<?php

namespace App\Support\Horizon;

use App\Models\Service;

final class JobDashboardUrlBuilder
{
    /**
     * Build the Horizon dashboard URL for a job.
     *
     * @param Service|null $service The service.
     * @param string|null $jobUuid The job UUID.
     * @param string|null $jobStatus The job status.
     */
    public static function build(?Service $service, ?string $jobUuid, ?string $jobStatus): ?string
    {
        if ($service === null || blank($jobUuid)) {
            return null;
        }

        $dashboardBase = $service->getPublicUrl();
        $encodedUuid = \urlencode($jobUuid);

        $jobPath = match ((string) $jobStatus) {
            'processing', 'pending', 'reserved' => config('horizonhub.horizon_paths.pending_jobs') . "/$encodedUuid",
            'processed', 'completed' => config('horizonhub.horizon_paths.completed_jobs') . "/$encodedUuid",
            'failed' => config('horizonhub.horizon_paths.failed_jobs') . "/$encodedUuid",
            default => config('horizonhub.horizon_paths.pending_jobs') . "/$encodedUuid",
        };

        return \rtrim($dashboardBase, '/') . config('horizonhub.horizon_paths.dashboard') . $jobPath;
    }
}
