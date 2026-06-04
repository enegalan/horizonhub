<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Contracts\HorizonHubStore;
use App\Models\Alert;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;
use App\Support\Alerts\AlertRuleEvaluation;

final class FailureCount implements AlertRuleContract
{
    /**
     * The evaluation support.
     */
    private AlertRuleEvaluation $support;

    /**
     * The horizon hub store.
     */
    private HorizonHubStore $store;

    /**
     * The constructor.
     *
     * @param AlertRuleEvaluation $support The evaluation support.
     */
    public function __construct(AlertRuleEvaluation $support, HorizonHubStore $store) {
        $this->support = $support;
        $this->store = $store;
    }

    /**
     * Get the type.
     */
    public static function type(): string
    {
        return 'failure_count';
    }

    /**
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        $service = $this->store->findService($serviceId);

        if ($service === null) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $cutoff = \now()->subMinutes($alert->getThresholdMinutes());
        $inWindow = $this->support->matchingFailedJobsInWindow($alert, $service, $cutoff);

        $triggered = $inWindow->count() >= $alert->getThresholdCount();

        $jobUuids = [];

        if ($triggered) {
            $jobUuids = $this->support->collectTriggeringJobUuids($inWindow);
        }

        return [
            'triggered' => $triggered,
            'job_uuids' => $jobUuids,
        ];
    }
}
