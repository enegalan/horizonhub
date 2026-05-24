<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\AlertRuleEvaluationSupport;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use Carbon\Carbon;

final class AvgExecutionTimeAlertRuleStrategy implements AlertRuleStrategyInterface
{
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
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs (if applicable).
     *
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array
    {
        $threshold = $alert->threshold ?? [];
        $maxSeconds = (float) ($threshold['seconds'] ?? config('horizonhub.alerts.default_seconds'));
        $minutes = (int) ($threshold['minutes'] ?? config('horizonhub.alerts.default_minutes'));

        $service = Service::find($serviceId);

        if (empty($service?->getBaseUrl())) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $cutoff = \now()->subMinutes($minutes);

        $durations = $this->support
            ->matchingCompletedJobsInWindow($alert, $service, $cutoff)
            ->map(function ($job) use ($cutoff) {
                $completedRaw = $job['completed_at'] ?? null;
                $queuedRaw = $job['pushedAt'] ?? null;

                if (! \is_string($completedRaw) || $completedRaw === '' || ! \is_string($queuedRaw) || $queuedRaw === '') {
                    return null;
                }

                try {
                    $completed = Carbon::parse($completedRaw);
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
