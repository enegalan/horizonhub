<?php

namespace App\Support\Alerts;

use App\Models\Alert;
use App\Services\Alerts\Rules\Strategies\AvgExecutionTime;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Alerts\Rules\Strategies\HorizonOffline;
use App\Services\Alerts\Rules\Strategies\QueueBlocked;
use App\Services\Alerts\Rules\Strategies\SupervisorOffline;
use App\Services\Alerts\Rules\Strategies\WorkerOffline;

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
            FailureCount::type() => 'At least ' . $alert->getThresholdCount() . " failures in the last {$alert->getThresholdMinutes()} minutes",
            AvgExecutionTime::type() => 'Average execution time exceeds ' . $alert->getThresholdSeconds() . "s in the last {$alert->getThresholdMinutes()} minutes",
            QueueBlocked::type() => "Queue blocked for {$alert->getThresholdMinutes()} minutes",
            WorkerOffline::type() => "Worker offline for {$alert->getThresholdMinutes()} minutes",
            SupervisorOffline::type() => "Supervisor offline for {$alert->getThresholdMinutes()} minutes",
            HorizonOffline::type() => "Horizon offline for {$alert->getThresholdMinutes()} minutes" . (filled($detectedAt) ? " (detected at {$detectedAt})" : ''),
            default => 'Alert condition met',
        };

        if ($alert->rule_type === FailureCount::type()) {
            $queuePatterns = $alert->getQueuePatterns();

            if (\count($queuePatterns) === 1) {
                $summary .= " (queue: {$queuePatterns[0]})";
            }
        }

        return $summary;
    }

    /**
     * Metadata for the alert form Alpine.js bindings.
     *
     * @return array{
     *     defaultRuleType: string,
     *     queuePatternRuleTypes: list<string>,
     *     jobPatternRuleTypes: list<string>,
     *     thresholdRuleTypes: list<string>,
     *     countRuleTypes: list<string>,
     *     secondsRuleTypes: list<string>,
     *     minutesOnlyRuleTypes: list<string>
     * }
     */
    public static function formRuleMetadata(): array
    {
        return [
            'defaultRuleType' => FailureCount::type(),
            'queuePatternRuleTypes' => self::ruleTypesWithQueuePatterns(),
            'jobPatternRuleTypes' => self::ruleTypesWithJobPatterns(),
            'thresholdRuleTypes' => self::ruleTypesRequiringMinutes(),
            'countRuleTypes' => self::ruleTypesRequiringCount(),
            'secondsRuleTypes' => self::ruleTypesRequiringSeconds(),
            'minutesOnlyRuleTypes' => self::ruleTypesWithMinutesOnlyThreshold(),
        ];
    }

    /**
     * Get the rule type labels.
     *
     * @return array<string, string> The rule type labels.
     */
    public static function ruleTypeLabels(): array
    {
        return [
            FailureCount::type() => 'Failure count in window',
            AvgExecutionTime::type() => 'Avg execution time exceeded',
            QueueBlocked::type() => 'Queue blocked',
            WorkerOffline::type() => 'Worker offline',
            SupervisorOffline::type() => 'Supervisor offline',
            HorizonOffline::type() => 'Horizon offline',
        ];
    }

    /**
     * Rule types that require a failure count threshold.
     *
     * @return list<string>
     */
    public static function ruleTypesRequiringCount(): array
    {
        return [FailureCount::type()];
    }

    /**
     * Rule types that require a minutes threshold.
     *
     * @return list<string>
     */
    public static function ruleTypesRequiringMinutes(): array
    {
        return [
            FailureCount::type(),
            AvgExecutionTime::type(),
            QueueBlocked::type(),
            WorkerOffline::type(),
            SupervisorOffline::type(),
            HorizonOffline::type(),
        ];
    }

    /**
     * Rule types that require a seconds threshold.
     *
     * @return list<string>
     */
    public static function ruleTypesRequiringSeconds(): array
    {
        return [AvgExecutionTime::type()];
    }

    /**
     * Rule types that support optional job patterns.
     *
     * @return list<string>
     */
    public static function ruleTypesWithJobPatterns(): array
    {
        return [FailureCount::type(), AvgExecutionTime::type()];
    }

    /**
     * Rule types that only expose a minutes threshold field in the form.
     *
     * @return list<string>
     */
    public static function ruleTypesWithMinutesOnlyThreshold(): array
    {
        return [
            QueueBlocked::type(),
            WorkerOffline::type(),
            SupervisorOffline::type(),
            HorizonOffline::type(),
        ];
    }

    /**
     * Rule types that support optional queue patterns.
     *
     * @return list<string>
     */
    public static function ruleTypesWithQueuePatterns(): array
    {
        return [
            FailureCount::type(),
            AvgExecutionTime::type(),
            QueueBlocked::type(),
        ];
    }
}
