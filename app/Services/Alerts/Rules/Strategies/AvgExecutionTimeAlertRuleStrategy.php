<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\AlertRuleEvaluationSupport;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use App\Services\Horizon\HorizonApiProxyService;
use Carbon\Carbon;

final class AvgExecutionTimeAlertRuleStrategy implements AlertRuleStrategyInterface
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
    public function __construct(AlertRuleEvaluationSupport $support, HorizonApiProxyService $horizonApi)
    {
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
        $maxSeconds = (float) ($threshold['seconds'] ?? config('horizonhub.alerts.default_seconds'));
        $minutes = (int) ($threshold['minutes'] ?? config('horizonhub.alerts.default_minutes'));

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $response = $this->horizonApi->getCompletedJobs($service, ['starting_at' => 0]);
        $data = $response['data'] ?? null;
        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $jobs = collect($data['jobs'] ?? []);
        $cutoff = \now()->subMinutes($minutes);

        $durations = $jobs->filter(function ($job) use ($alert) {
            return \is_array($job) && $this->support->completedJobRowMatches($alert, $job);
        })->map(function ($job) use ($cutoff) {
            if (! \is_array($job)) {
                return null;
            }
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

        $avg = $durations->average();
        $triggered = (float) $avg >= $maxSeconds;

        return [
            'triggered' => $triggered,
            'job_uuids' => [],
        ];
    }
}
