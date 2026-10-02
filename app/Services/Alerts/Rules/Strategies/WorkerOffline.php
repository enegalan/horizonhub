<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Enums\AlertRuleType;
use App\Models\Alert;
use App\Models\Service;

final class WorkerOffline extends AbstractAlertRuleStrategy
{
    /**
     * Get the type.
     */
    public static function type(): AlertRuleType
    {
        return AlertRuleType::WorkerOffline;
    }

    /**
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs.
     *
     * @param Alert $alert The alert.
     * @param Service $service The service.
     *
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, Service $service): array
    {
        $triggered = $service->last_seen_at !== null
            && $service->last_seen_at->copy()->addMinutes($alert->getThresholdMinutes())->isPast();

        return [
            'triggered' => $triggered,
            'job_uuids' => [],
        ];
    }
}
