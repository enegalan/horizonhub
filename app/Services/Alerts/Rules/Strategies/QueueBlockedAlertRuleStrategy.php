<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\AlertRuleEvaluationSupport;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use Carbon\Carbon;

final class QueueBlockedAlertRuleStrategy implements AlertRuleStrategyInterface
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
        return [
            'triggered' => $this->private__evaluateQueueBlocked($alert, $serviceId),
            'job_uuids' => [],
        ];
    }

    /**
     * Evaluate the queue blocked.
     */
    private function private__evaluateQueueBlocked(Alert $alert, int $serviceId): bool
    {
        $threshold = $alert->threshold ?? [];
        $minutes = (int) ($threshold['minutes'] ?? 30);

        $service = Service::find($serviceId);

        if (empty($service?->getBaseUrl())) {
            return false;
        }

        $cutoff = \now()->subMinutes($minutes);
        $jobs = $this->support->matchingCompletedJobsInWindow($alert, $service, $cutoff);

        $lastProcessed = $jobs->map(function (array $job) {
            $completedRaw = $job['completed_at'] ?? null;

            if (! \is_string($completedRaw) || $completedRaw === '') {
                return null;
            }

            try {
                return Carbon::parse($completedRaw);
            } catch (\Throwable $e) {
                return null;
            }
        })->filter()->sort()->last();

        if (! $lastProcessed) {
            return false;
        }

        return $lastProcessed->copy()->addMinutes($minutes)->isPast();
    }
}
