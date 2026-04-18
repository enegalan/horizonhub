<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;

final class WorkerOfflineAlertRuleStrategy implements AlertRuleStrategyInterface
{
    /**
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs (if applicable).
     *
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array
    {
        return [
            'triggered' => $this->private__evaluateWorkerOffline($alert, $serviceId),
            'job_uuids' => [],
        ];
    }

    /**
     * Evaluate the worker offline.
     */
    private function private__evaluateWorkerOffline(Alert $alert, int $serviceId): bool
    {
        $threshold = $alert->threshold ?? [];
        $minutes = (int) ($threshold['minutes'] ?? config('horizonhub.alerts.default_minutes'));

        $service = Service::find($serviceId);
        if (! $service || ! $service->last_seen_at) {
            return false;
        }

        return $service->last_seen_at->copy()->addMinutes($minutes)->isPast();
    }
}
