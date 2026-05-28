<?php

namespace App\Support\Alerts;

use App\Models\Alert;

final class AlertRuleCatalog
{
    /**
     * Build the condition summary.
     *
     * @param Alert $alert The alert.
     * @param string|null $detectedAt The detected at.
     *
     * @return string The condition summary.
     */
    public static function conditionSummary(Alert $alert, ?string $detectedAt = null): string
    {
        $summary = match ($alert->rule_type) {
            Alert::RULE_FAILURE_COUNT => 'At least ' . $alert->getThresholdCount() . " failures in the last {$alert->getThresholdMinutes()} minutes",
            Alert::RULE_AVG_EXECUTION_TIME => 'Average execution time exceeds ' . $alert->getThresholdSeconds() . "s in the last {$alert->getThresholdMinutes()} minutes",
            Alert::RULE_QUEUE_BLOCKED => "Queue blocked for {$alert->getThresholdMinutes()} minutes",
            Alert::RULE_WORKER_OFFLINE => "Worker offline for {$alert->getThresholdMinutes()} minutes",
            Alert::RULE_SUPERVISOR_OFFLINE => "Supervisor offline for {$alert->getThresholdMinutes()} minutes",
            Alert::RULE_HORIZON_OFFLINE => 'Horizon is not running for this service' . (filled($detectedAt) ? " (detected at {$detectedAt})" : ''),
            default => 'Alert condition met',
        };

        if ($alert->rule_type === Alert::RULE_FAILURE_COUNT && filled($alert->queue)) {
            $summary .= " (queue: {$alert->queue})";
        }

        return $summary;
    }

    /**
     * Get the rule type labels.
     *
     * @return array<string, string> The rule type labels.
     */
    public static function ruleTypeLabels(): array
    {
        return [
            Alert::RULE_FAILURE_COUNT => 'Failure count in window',
            Alert::RULE_AVG_EXECUTION_TIME => 'Avg execution time exceeded',
            Alert::RULE_QUEUE_BLOCKED => 'Queue blocked',
            Alert::RULE_WORKER_OFFLINE => 'Worker offline',
            Alert::RULE_SUPERVISOR_OFFLINE => 'Supervisor offline',
            Alert::RULE_HORIZON_OFFLINE => 'Horizon offline',
        ];
    }
}
