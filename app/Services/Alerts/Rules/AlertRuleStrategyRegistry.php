<?php

namespace App\Services\Alerts\Rules;

use App\Models\Alert;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use App\Services\Alerts\Rules\Strategies\AvgExecutionTimeAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\FailureCountAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\HorizonOfflineAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\NullAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\QueueBlockedAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\SupervisorOfflineAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\WorkerOfflineAlertRuleStrategy;

final class AlertRuleStrategyRegistry
{
    /**
     * The null strategy.
     */
    private NullAlertRuleStrategy $nullStrategy;

    /**
     * The strategies.
     *
     * @var array<string, AlertRuleStrategyInterface>
     */
    private array $strategies;

    /**
     * Construct the strategy registry.
     */
    public function __construct(NullAlertRuleStrategy $nullStrategy, FailureCountAlertRuleStrategy $failureCount, AvgExecutionTimeAlertRuleStrategy $avgExecutionTime, QueueBlockedAlertRuleStrategy $queueBlocked, WorkerOfflineAlertRuleStrategy $workerOffline, SupervisorOfflineAlertRuleStrategy $supervisorOffline, HorizonOfflineAlertRuleStrategy $horizonOffline)
    {
        $this->nullStrategy = $nullStrategy;
        $this->strategies = [
            Alert::RULE_FAILURE_COUNT => $failureCount,
            Alert::RULE_AVG_EXECUTION_TIME => $avgExecutionTime,
            Alert::RULE_QUEUE_BLOCKED => $queueBlocked,
            Alert::RULE_WORKER_OFFLINE => $workerOffline,
            Alert::RULE_SUPERVISOR_OFFLINE => $supervisorOffline,
            Alert::RULE_HORIZON_OFFLINE => $horizonOffline,
        ];
    }

    /**
     * Resolve the strategy for the given rule type.
     *
     * @param string $ruleType The rule type.
     */
    public function resolve(string $ruleType): AlertRuleStrategyInterface
    {
        return $this->strategies[$ruleType] ?? $this->nullStrategy;
    }
}
