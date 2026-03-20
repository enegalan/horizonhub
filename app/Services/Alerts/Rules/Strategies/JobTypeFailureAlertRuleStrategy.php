<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\AlertRuleEvaluationSupport;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;

final class JobTypeFailureAlertRuleStrategy implements AlertRuleStrategyInterface {

    /**
     * The evaluation support.
     *
     * @var AlertRuleEvaluationSupport
     */
    private AlertRuleEvaluationSupport $support;

    /**
     * Construct the strategy.
     *
     * @param AlertRuleEvaluationSupport $support
     */
    public function __construct(AlertRuleEvaluationSupport $support) {
        $this->support = $support;
    }

    /**
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs (if applicable).
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array {
        $patterns = $this->support->resolveJobPatterns($alert);
        if ($patterns === []) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $threshold = $alert->threshold ?? [];
        $minutes = (int) ($threshold['minutes'] ?? 15);
        if ($minutes < 1) {
            $minutes = 15;
        }
        $minCount = (int) ($threshold['count'] ?? 1);
        if ($minCount < 1) {
            $minCount = 1;
        }

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $cutoff = \now()->subMinutes($minutes);
        $matching = $this->support->matchingFailedJobsInWindow($alert, $service, $cutoff);
        if ($matching->count() < $minCount) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        return [
            'triggered' => true,
            'job_uuids' => $this->support->collectTriggeringJobUuids($matching),
        ];
    }
}
