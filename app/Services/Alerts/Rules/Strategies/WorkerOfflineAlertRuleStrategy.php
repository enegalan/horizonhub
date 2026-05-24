<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use App\Support\Alerts\AlertRuleStrategySupport;

final class WorkerOfflineAlertRuleStrategy implements AlertRuleStrategyInterface
{
    use AlertRuleStrategySupport;

    /**
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        return [
            'triggered' => $this->private__evaluateWorkerOffline($alert, $serviceId),
            'job_uuids' => [],
        ];
    }

    private function private__evaluateWorkerOffline(Alert $alert, int $serviceId): bool
    {
        $minutes = $this->private__thresholdMinutes($alert);
        $service = Service::find($serviceId);

        if (! $service || ! $service->last_seen_at) {
            return false;
        }

        return $service->last_seen_at->copy()->addMinutes($minutes)->isPast();
    }
}
