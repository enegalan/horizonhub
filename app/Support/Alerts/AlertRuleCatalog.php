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
            Alert::RULE_HORIZON_OFFLINE => "Horizon offline for {$alert->getThresholdMinutes()} minutes" . (filled($detectedAt) ? " (detected at {$detectedAt})" : ''),
            default => 'Alert condition met',
        };

        if ($alert->rule_type === Alert::RULE_FAILURE_COUNT) {
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
            'defaultRuleType' => Alert::RULE_FAILURE_COUNT,
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
            Alert::RULE_FAILURE_COUNT => 'Failure count in window',
            Alert::RULE_AVG_EXECUTION_TIME => 'Avg execution time exceeded',
            Alert::RULE_QUEUE_BLOCKED => 'Queue blocked',
            Alert::RULE_WORKER_OFFLINE => 'Worker offline',
            Alert::RULE_SUPERVISOR_OFFLINE => 'Supervisor offline',
            Alert::RULE_HORIZON_OFFLINE => 'Horizon offline',
        ];
    }

    /**
     * Rule types that require a failure count threshold.
     *
     * @return list<string>
     */
    public static function ruleTypesRequiringCount(): array
    {
        return [Alert::RULE_FAILURE_COUNT];
    }

    /**
     * Rule types that require a minutes threshold.
     *
     * @return list<string>
     */
    public static function ruleTypesRequiringMinutes(): array
    {
        return [
            Alert::RULE_FAILURE_COUNT,
            Alert::RULE_AVG_EXECUTION_TIME,
            Alert::RULE_QUEUE_BLOCKED,
            Alert::RULE_WORKER_OFFLINE,
            Alert::RULE_SUPERVISOR_OFFLINE,
            Alert::RULE_HORIZON_OFFLINE,
        ];
    }

    /**
     * Rule types that require a seconds threshold.
     *
     * @return list<string>
     */
    public static function ruleTypesRequiringSeconds(): array
    {
        return [Alert::RULE_AVG_EXECUTION_TIME];
    }

    /**
     * Rule types that support optional job patterns.
     *
     * @return list<string>
     */
    public static function ruleTypesWithJobPatterns(): array
    {
        return [Alert::RULE_FAILURE_COUNT, Alert::RULE_AVG_EXECUTION_TIME];
    }

    /**
     * Rule types that only expose a minutes threshold field in the form.
     *
     * @return list<string>
     */
    public static function ruleTypesWithMinutesOnlyThreshold(): array
    {
        return [
            Alert::RULE_QUEUE_BLOCKED,
            Alert::RULE_WORKER_OFFLINE,
            Alert::RULE_SUPERVISOR_OFFLINE,
            Alert::RULE_HORIZON_OFFLINE,
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
            Alert::RULE_FAILURE_COUNT,
            Alert::RULE_AVG_EXECUTION_TIME,
            Alert::RULE_QUEUE_BLOCKED,
        ];
    }
}
