<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;
use App\Support\Alerts\AlertRuleEvaluation;
use App\Support\Alerts\AlertRuleStrategy;
use Carbon\Carbon;

final class AvgExecutionTime implements AlertRuleContract
{
    use AlertRuleStrategy;

    /**
     * The evaluation support.
     */
    private AlertRuleEvaluation $support;

    /**
     * The constructor.
     *
     * @param AlertRuleEvaluation $support The evaluation support.
     */
    public function __construct(AlertRuleEvaluation $support)
    {
        $this->support = $support;
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
        $threshold = $alert->threshold ?? [];
        $maxSeconds = (float) ($threshold['seconds'] ?? config('horizonhub.alerts.default_seconds'));
        $minutes = $this->private__thresholdMinutes($alert);

        $service = Service::find($serviceId);

        if ($service === null) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $cutoff = \now()->subMinutes($minutes);

        $durations = $this->support
            ->matchingCompletedJobsInWindow($alert, $service, $cutoff)
            ->map(function (array $job) use ($cutoff) {
                $completed = $this->support->parseCompletedAt($job);
                $queuedRaw = $job['pushedAt'] ?? null;

                if ($completed === null || ! \is_string($queuedRaw) || $queuedRaw === '') {
                    return null;
                }

                try {
                    $queued = Carbon::parse($queuedRaw);
                } catch (\Throwable $e) {
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

        $triggered = (float) $durations->average() >= $maxSeconds;

        return [
            'triggered' => $triggered,
            'job_uuids' => [],
        ];
    }
}
