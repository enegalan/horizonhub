<?php

namespace App\Services\Alerts\Rules;

use App\Models\Alert;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\AvgExecutionTime;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Alerts\Rules\Strategies\HorizonOffline;
use App\Services\Alerts\Rules\Strategies\NullRule;
use App\Services\Alerts\Rules\Strategies\QueueBlocked;
use App\Services\Alerts\Rules\Strategies\SupervisorOffline;
use App\Services\Alerts\Rules\Strategies\WorkerOffline;

final class AlertRuleStrategyRegistry
{
    /**
     * The null strategy.
     */
    private NullRule $nullStrategy;

    /**
     * The strategies.
     *
     * @var array<string, AlertRuleStrategy>
     */
    private array $strategies;

    /**
     * The constructor.
     *
     * @param NullRule $nullStrategy The null strategy.
     * @param FailureCount $failureCount The failure count strategy.
     * @param AvgExecutionTime $avgExecutionTime The avg execution time strategy.
     * @param QueueBlocked $queueBlocked The queue blocked strategy.
     * @param WorkerOffline $workerOffline The worker offline strategy.
     * @param SupervisorOffline $supervisorOffline The supervisor offline strategy.
     * @param HorizonOffline $horizonOffline The horizon offline strategy.
     */
    public function __construct(NullRule $nullStrategy, FailureCount $failureCount, AvgExecutionTime $avgExecutionTime, QueueBlocked $queueBlocked, WorkerOffline $workerOffline, SupervisorOffline $supervisorOffline, HorizonOffline $horizonOffline)
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
    public function resolve(string $ruleType): AlertRuleStrategy
    {
        return $this->strategies[$ruleType] ?? $this->nullStrategy;
    }
}
