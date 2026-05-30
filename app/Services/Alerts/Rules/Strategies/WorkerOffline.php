<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;

final class WorkerOffline implements AlertRuleContract
{
    /**
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        $service = Service::find($serviceId);

        $triggered = false;

        if ($service?->last_seen_at) {
            $triggered = $service->last_seen_at->copy()->addMinutes($alert->getThresholdMinutes())->isPast();
        }

        return [
            'triggered' => $triggered,
            'job_uuids' => [],
        ];
    }
}
