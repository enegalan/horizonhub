<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Services\Alerts\Rules\AlertRuleStrategyRegistry;

class AlertRuleEvaluator
{
    /**
     * The strategy registry.
     */
    private AlertRuleStrategyRegistry $strategyRegistry;

    /**
     * Construct the rule evaluator.
     */
    public function __construct(AlertRuleStrategyRegistry $strategyRegistry)
    {
        $this->strategyRegistry = $strategyRegistry;
    }

    /**
     * Evaluate the alert rule and return whether it triggered plus the list of job UUIDs that triggered it.
     *
     * @param  Alert  $alert  The alert.
     * @param  int  $serviceId  The service ID.
     * @param  string|null  $jobUuid  The job UUID.
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array
    {
        $strategy = $this->strategyRegistry->resolve((string) $alert->rule_type);

        return $strategy->evaluateWithTriggeringJobs($alert, $serviceId, $jobUuid);
    }
}
