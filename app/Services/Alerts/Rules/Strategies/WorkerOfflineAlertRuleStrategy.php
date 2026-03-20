<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;

final class WorkerOfflineAlertRuleStrategy implements AlertRuleStrategyInterface {

    /**
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs (if applicable).
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array {
        return [
            'triggered' => $this->private__evaluateWorkerOffline($alert, $serviceId),
            'job_uuids' => [],
        ];
    }

    /**
     * Evaluate the worker offline.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function private__evaluateWorkerOffline(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $minutes = (int) ($threshold['minutes'] ?? 5);

        $service = Service::find($serviceId);
        if (! $service || ! $service->last_seen_at) {
            return false;
        }

        return $service->last_seen_at->copy()->addMinutes($minutes)->isPast();
    }
}
