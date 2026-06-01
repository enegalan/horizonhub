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
     * Get the type.
     */
    public static function type(): string
    {
        return 'queue_blocked';
    }

    /**
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        $minutes = $alert->getThresholdMinutes();
        $service = Service::find($serviceId);

        $triggered = false;

        if ($service !== null) {
            $cutoff = \now()->subMinutes($minutes);
            $jobs = $this->support->matchingCompletedJobsInWindow($alert, $service, $cutoff);

            $lastProcessed = $jobs
                ->map(fn (array $job) => $this->support->parseCompletedAt($job))
                ->filter()
                ->sort()
                ->last();

            if ($lastProcessed !== null) {
                $triggered = $lastProcessed->copy()->addMinutes($minutes)->isPast();
            }
        }

        return [
            'triggered' => $triggered,
            'job_uuids' => [],
        ];
    }
}
