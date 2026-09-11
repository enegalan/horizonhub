<?php

namespace App\Support\Alerts;

use App\Enums\AlertRuleType;
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
            AlertRuleType::FailureCount => 'At least ' . $alert->getThresholdCount() . " failures in the last {$alert->getThresholdMinutes()} minutes",
            AlertRuleType::AvgExecutionTime => 'Average execution time exceeds ' . $alert->getThresholdSeconds() . "s in the last {$alert->getThresholdMinutes()} minutes",
            AlertRuleType::QueueBlocked => "Queue blocked for {$alert->getThresholdMinutes()} minutes",
            AlertRuleType::WorkerOffline => "Worker offline for {$alert->getThresholdMinutes()} minutes",
            AlertRuleType::SupervisorOffline => "Supervisor offline for {$alert->getThresholdMinutes()} minutes",
            AlertRuleType::HorizonOffline => "Horizon offline for {$alert->getThresholdMinutes()} minutes" . (filled($detectedAt) ? " (detected at {$detectedAt})" : ''),
        };

        if ($alert->rule_type === AlertRuleType::FailureCount) {
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
            'defaultRuleType' => AlertRuleType::FailureCount->value,
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
        return AlertRuleType::labels();
    }

    /**
     * Rule types that require a failure count threshold.
     *
     * @return list<string>
     */
    public static function ruleTypesRequiringCount(): array
    {
        return [AlertRuleType::FailureCount->value];
    }

    /**
     * Rule types that require a minutes threshold.
     *
     * @return list<string>
     */
    public static function ruleTypesRequiringMinutes(): array
    {
        return [
            AlertRuleType::FailureCount->value,
            AlertRuleType::AvgExecutionTime->value,
            AlertRuleType::QueueBlocked->value,
            AlertRuleType::WorkerOffline->value,
            AlertRuleType::SupervisorOffline->value,
            AlertRuleType::HorizonOffline->value,
        ];
    }

    /**
     * Rule types that require a seconds threshold.
     *
     * @return list<string>
     */
    public static function ruleTypesRequiringSeconds(): array
    {
        return [AlertRuleType::AvgExecutionTime->value];
    }

    /**
     * Rule types that support optional job patterns.
     *
     * @return list<string>
     */
    public static function ruleTypesWithJobPatterns(): array
    {
        return [AlertRuleType::FailureCount->value, AlertRuleType::AvgExecutionTime->value];
    }

    /**
     * Rule types that only expose a minutes threshold field in the form.
     *
     * @return list<string>
     */
    public static function ruleTypesWithMinutesOnlyThreshold(): array
    {
        return [
            AlertRuleType::QueueBlocked->value,
            AlertRuleType::WorkerOffline->value,
            AlertRuleType::SupervisorOffline->value,
            AlertRuleType::HorizonOffline->value,
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
            AlertRuleType::FailureCount->value,
            AlertRuleType::AvgExecutionTime->value,
            AlertRuleType::QueueBlocked->value,
        ];
    }
}
