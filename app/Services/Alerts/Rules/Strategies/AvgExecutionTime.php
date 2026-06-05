<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Contracts\HorizonHubStore;
use App\Models\Alert;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;
use App\Support\Alerts\AlertRuleEvaluation;
use App\Support\Horizon\JobRuntimeHelper;

final class AvgExecutionTime implements AlertRuleContract
{
    /**
     * The horizon hub store.
     */
    private HorizonHubStore $store;

    /**
     * The evaluation support.
     */
    private AlertRuleEvaluation $support;

    /**
     * The constructor.
     *
     * @param AlertRuleEvaluation $support The evaluation support.
     */
    public function __construct(AlertRuleEvaluation $support, HorizonHubStore $store)
    {
        $this->support = $support;
        $this->store = $store;
    }

    /**
     * Get the type.
     */
    public static function type(): string
    {
        return 'avg_execution_time';
    }

    /**
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs (if applicable).
     *
     * @param Alert $alert The alert.
     * @param int $serviceId The service ID.
     *
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        $service = $this->store->findService($serviceId);

        if ($service === null) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $cutoff = \now()->subMinutes($alert->getThresholdMinutes());

        $durations = $this->support
            ->matchingCompletedJobsInWindow($alert, $service, $cutoff)
            ->map(function (array $job) use ($cutoff) {
                $completed = $this->support->parseCompletedAt($job);
                $queuedRaw = $job['pushedAt'] ?? null;
                $queued = JobRuntimeHelper::parseJobTimestamp($queuedRaw);

                if ($completed === null || $queued === null) {
                    return null;
                }

                if ($completed->lt($cutoff)) {
                    return null;
                }

                $seconds = $queued->diffInSeconds($completed, false);

                return $seconds >= 0 ? $seconds : null;
            })->filter(static fn ($v) => $v !== null);

        if ($durations->isEmpty()) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $triggered = (float) $durations->average() >= $alert->getThresholdSeconds();

        return [
            'triggered' => $triggered,
            'job_uuids' => [],
        ];
    }
}
