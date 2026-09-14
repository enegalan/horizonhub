<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Enums\AlertRuleType;
use App\Models\Alert;

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
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        return $this->notTriggered();
    }
}
