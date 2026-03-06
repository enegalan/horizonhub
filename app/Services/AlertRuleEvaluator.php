<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;
use Carbon\Carbon;

class AlertRuleEvaluator {
    /**
     * Evaluate the given alert rule for the provided context.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param int|null $jobId
     * @return bool
     */
    public function evaluate(Alert $alert, int $serviceId, ?int $jobId): bool {
        return match ($alert->rule_type) {
            'job_specific_failure' => $this->evaluateJobSpecificFailure($alert, $serviceId, $jobId),
            'job_type_failure' => $this->evaluateJobTypeFailure($alert, $serviceId),
            'failure_count' => $this->evaluateFailureCount($alert, $serviceId),
            'avg_execution_time' => $this->evaluateAvgExecutionTime($alert, $serviceId),
            'queue_blocked' => $this->evaluateQueueBlocked($alert, $serviceId),
            'worker_offline' => $this->evaluateWorkerOffline($alert, $serviceId),
            default => false,
        };
    }

    /**
     * Evaluate a job specific failure rule.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param int|null $jobId
     * @return bool
     */
    private function evaluateJobSpecificFailure(Alert $alert, int $serviceId, ?int $jobId): bool {
        $triggered = true;
        return $triggered;
    }

    /**
     * Evaluate the job type failure.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function evaluateJobTypeFailure(Alert $alert, int $serviceId): bool {
        if (! $alert->job_type) {
            return false;
        }

        $recent = HorizonFailedJob::where('service_id', $serviceId)
            ->where('failed_at', '>=', \now()->subMinutes(15))
            ->get()
            ->filter(function ($job) use ($alert) {
                $payload = $job->payload ?? [];
                $displayName = \isset($payload['displayName']) ? $payload['displayName'] : null;
                $rawJob = \isset($payload['job']) ? $payload['job'] : null;
                $haystack = (string) ($displayName ?? $rawJob ?? '');

                return \str_contains($haystack, $alert->job_type);
            })
            ->isNotEmpty();

        return $recent;
    }

    /**
     * Evaluate the failure count.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function evaluateFailureCount(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $count = (int) (\isset($threshold['count']) ? $threshold['count'] : 5);
        $minutes = (int) (\isset($threshold['minutes']) ? $threshold['minutes'] : 15);

        $actual = HorizonFailedJob::where('service_id', $serviceId)
            ->when($alert->queue, function ($query) use ($alert) {
                return $query->where('queue', $alert->queue);
            })
            ->where('failed_at', '>=', \now()->subMinutes($minutes))
            ->count();

        $triggered = $actual >= $count;

        return $triggered;
    }

    /**
     * Evaluate the average execution time.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function evaluateAvgExecutionTime(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $maxSeconds = (float) (\isset($threshold['seconds']) ? $threshold['seconds'] : 60);
        $minutes = (int) (\isset($threshold['minutes']) ? $threshold['minutes'] : 15);

        $jobs = HorizonJob::where('service_id', $serviceId)
            ->where('status', 'processed')
            ->whereNotNull('processed_at')
            ->where('processed_at', '>=', \now()->subMinutes($minutes))
            ->get();

        if ($jobs->isEmpty()) {
            return false;
        }

        $jobsWithRuntime = $jobs->filter(function ($job) {
            return $job->getRuntimeSeconds() !== null;
        });

        $avg = $jobsWithRuntime->isEmpty()
            ? 0.0
            : $jobsWithRuntime->avg(function ($job) {
                return $job->getRuntimeSeconds();
            });

        $triggered = $avg >= $maxSeconds;

        return $triggered;
    }

    /**
     * Evaluate the queue blocked.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function evaluateQueueBlocked(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $minutes = (int) (\isset($threshold['minutes']) ? $threshold['minutes'] : 30);

        $lastProcessed = HorizonJob::where('service_id', $serviceId)
            ->when($alert->queue, function ($query) use ($alert) {
                return $query->where('queue', $alert->queue);
            })
            ->where('status', 'processed')
            ->max('processed_at');

        if (! $lastProcessed) {
            return false;
        }

        $triggered = Carbon::parse($lastProcessed)->addMinutes($minutes)->isPast();

        return $triggered;
    }

    /**
     * Evaluate the worker offline.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function evaluateWorkerOffline(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $minutes = (int) (\isset($threshold['minutes']) ? $threshold['minutes'] : 5);

        $service = Service::find($serviceId);
        if (! $service || ! $service->last_seen_at) {
            return false;
        }

        $triggered = $service->last_seen_at->addMinutes($minutes)->isPast();

        return $triggered;
    }
}
