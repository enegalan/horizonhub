<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\AlertRuleEvaluationSupport;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use App\Services\HorizonApiProxyService;

final class JobSpecificFailureAlertRuleStrategy implements AlertRuleStrategyInterface
{
    /**
     * The evaluation support.
     */
    private AlertRuleEvaluationSupport $support;

    /**
     * The Horizon API proxy service.
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * Construct the strategy.
     */
    public function __construct(
        AlertRuleEvaluationSupport $support,
        HorizonApiProxyService $horizonApi,
    ) {
        $this->support = $support;
        $this->horizonApi = $horizonApi;
    }

    /**
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs (if applicable).
     *
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array
    {
        if (empty($jobUuid)) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $response = $this->horizonApi->getFailedJob($service, $jobUuid);
        $data = $response['data'] ?? null;
        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        if (! $this->support->failedJobRowMatches($alert, $data)) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $threshold = $alert->threshold ?? [];
        $minCount = (int) ($threshold['count'] ?? 1);
        if ($minCount < 1) {
            $minCount = 1;
        }
        $minutes = (int) ($threshold['minutes'] ?? 15);
        if ($minutes < 1) {
            $minutes = 15;
        }

        if ($minCount <= 1) {
            return [
                'triggered' => true,
                'job_uuids' => [(string) $jobUuid],
            ];
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
