<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Enums\AlertRuleType;
use App\Models\Alert;
use App\Models\Service;

final class NullRule extends AbstractAlertRuleStrategy
{
    /**
     * Get the type.
     *
     * The null rule has no concrete rule type; it is only used as a fallback
     * strategy and is never stored or validated as a rule type.
     */
    public static function type(): ?AlertRuleType
    {
        return null;
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
        return $this->notTriggered();
    }
}
