<?php

namespace App\Support\Horizon;

use App\Models\Service;

class JobDashboardUrlBuilder
{
    /**
     * Build the Horizon dashboard URL for a job.
     *
     * @param  Service|null  $service  The service.
     * @param  string|null  $jobUuid  The job UUID.
     * @param  string|null  $jobStatus  The job status.
     */
    public static function build(?Service $service, ?string $jobUuid, ?string $jobStatus): ?string
    {
        if ($service === null || $jobUuid === null || $jobUuid === '') {
            return null;
        }

        $dashboardBase = $service->getPublicUrl();
        if ($dashboardBase === '') {
            return null;
        }
        $encodedUuid = \urlencode($jobUuid);

        $jobPath = match ((string) $jobStatus) {
            'processing', 'pending', 'reserved' => "/jobs/pending/$encodedUuid",
            'processed', 'completed' => "/jobs/completed/$encodedUuid",
            'failed' => "/failed/$encodedUuid",
            default => "/jobs/pending/$encodedUuid",
        };

        return \rtrim($dashboardBase, '/').config('horizonhub.horizon_paths.dashboard').$jobPath;
    }
}
