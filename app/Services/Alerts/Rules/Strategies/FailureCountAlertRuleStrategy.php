<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Services\Alerts\Rules\AlertRuleEvaluationSupport;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use App\Support\Alerts\AlertRuleStrategySupport;

final class FailureCountAlertRuleStrategy implements AlertRuleStrategyInterface
{
    use AlertRuleStrategySupport;

    /**
     * The evaluation support.
     */
    private AlertRuleEvaluationSupport $support;

    /**
     * Construct the strategy.
     */
    public function __construct(AlertRuleEvaluationSupport $support)
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

        $service = $this->private__resolveServiceForEvaluation($serviceId);

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
