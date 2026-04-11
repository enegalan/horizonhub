<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\AlertRuleEvaluationSupport;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use App\Services\Horizon\HorizonApiProxyService;
use App\Support\ConfigHelper;

final class FailureCountAlertRuleStrategy implements AlertRuleStrategyInterface
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
        $threshold = $alert->threshold ?? [];
        $count = (int) ($threshold['count'] ?? ConfigHelper::get('horizonhub.alerts.default_count'));
        $minutes = (int) ($threshold['minutes'] ?? ConfigHelper::get('horizonhub.alerts.default_minutes'));

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $response = $this->horizonApi->getFailedJobs($service, ['starting_at' => 0]);
        $data = $response['data'] ?? null;
        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $cutoff = \now()->subMinutes($minutes);
        $jobs = collect($data['jobs'] ?? []);
        $inWindow = $this->support->filterFailedJobsInWindow($jobs, $cutoff)
            ->filter(function ($job) use ($alert) {
                return \is_array($job) && $this->support->failedJobRowMatches($alert, $job);
            })
            ->values();

        $actual = $inWindow->count();
        $triggered = $actual >= $count;

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
