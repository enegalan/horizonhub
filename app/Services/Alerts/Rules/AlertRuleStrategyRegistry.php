<?php

namespace App\Services\Alerts\Rules;

use App\Models\Alert;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use App\Services\Alerts\Rules\Strategies\AvgExecutionTimeAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\FailureCountAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\HorizonOfflineAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\JobSpecificFailureAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\JobTypeFailureAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\QueueBlockedAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\SupervisorOfflineAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\WorkerOfflineAlertRuleStrategy;

final class AlertRuleStrategyRegistry {

    /**
     * The strategies.
     *
     * @var array<string, AlertRuleStrategyInterface>
     */
    private array $strategies;

    /**
     * The null strategy.
     *
     * @var NullAlertRuleStrategy
     */
    private NullAlertRuleStrategy $nullStrategy;

    /**
     * Construct the strategy registry.
     *
     * @param NullAlertRuleStrategy $nullStrategy
     * @param JobSpecificFailureAlertRuleStrategy $jobSpecificFailure
     * @param JobTypeFailureAlertRuleStrategy $jobTypeFailure
     * @param FailureCountAlertRuleStrategy $failureCount
     * @param AvgExecutionTimeAlertRuleStrategy $avgExecutionTime
     * @param QueueBlockedAlertRuleStrategy $queueBlocked
     * @param WorkerOfflineAlertRuleStrategy $workerOffline
     * @param SupervisorOfflineAlertRuleStrategy $supervisorOffline
     * @param HorizonOfflineAlertRuleStrategy $horizonOffline
     */
    public function __construct(
        NullAlertRuleStrategy $nullStrategy,
        JobSpecificFailureAlertRuleStrategy $jobSpecificFailure,
        JobTypeFailureAlertRuleStrategy $jobTypeFailure,
        FailureCountAlertRuleStrategy $failureCount,
        AvgExecutionTimeAlertRuleStrategy $avgExecutionTime,
        QueueBlockedAlertRuleStrategy $queueBlocked,
        WorkerOfflineAlertRuleStrategy $workerOffline,
        SupervisorOfflineAlertRuleStrategy $supervisorOffline,
        HorizonOfflineAlertRuleStrategy $horizonOffline,
    ) {
        $this->nullStrategy = $nullStrategy;
        $this->strategies = [
            Alert::RULE_JOB_SPECIFIC_FAILURE => $jobSpecificFailure,
            Alert::RULE_JOB_TYPE_FAILURE => $jobTypeFailure,
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
     * @param string $ruleType
     * @return AlertRuleStrategyInterface
     */
    public function resolve(string $ruleType): AlertRuleStrategyInterface {
        return $this->strategies[$ruleType] ?? $this->nullStrategy;
    }
}
