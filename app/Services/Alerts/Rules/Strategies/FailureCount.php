<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;
use App\Support\Alerts\AlertRuleEvaluation;
use App\Support\Alerts\AlertRuleStrategy;

final class FailureCount implements AlertRuleContract
{
    use AlertRuleStrategy;

    /**
     * The evaluation support.
     */
    private AlertRuleEvaluation $support;

    /**
     * Construct the strategy.
     */
    public function __construct(AlertRuleEvaluation $support)
    {
        $this->support = $support;
    }

    /**
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        $threshold = $alert->threshold ?? [];
        $count = (int) ($threshold['count'] ?? config('horizonhub.alerts.default_count'));
        $minutes = $this->private__thresholdMinutes($alert);

        $service = Service::find($serviceId);

        if ($service === null) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $cutoff = \now()->subMinutes($minutes);
        $inWindow = $this->support->matchingFailedJobsInWindow($alert, $service, $cutoff);

        $triggered = $inWindow->count() >= $count;

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
