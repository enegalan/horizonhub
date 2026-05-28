<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;
use App\Support\Alerts\AlertRuleEvaluation;

final class QueueBlocked implements AlertRuleContract
{
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
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        return [
            'triggered' => $this->private__evaluateQueueBlocked($alert, $serviceId),
            'job_uuids' => [],
        ];
    }

    private function private__evaluateQueueBlocked(Alert $alert, int $serviceId): bool
    {
        $minutes = $alert->getThresholdMinutes(30);
        $service = Service::find($serviceId);

        if ($service === null) {
            return false;
        }

        $cutoff = \now()->subMinutes($minutes);
        $jobs = $this->support->matchingCompletedJobsInWindow($alert, $service, $cutoff);

        $lastProcessed = $jobs
            ->map(fn (array $job) => $this->support->parseCompletedAt($job))
            ->filter()
            ->sort()
            ->last();

        if ($lastProcessed === null) {
            return false;
        }

        return $lastProcessed->copy()->addMinutes($minutes)->isPast();
    }
}
