<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Service;
use App\Services\HorizonApiProxyService;
use Carbon\Carbon;

class AlertRuleEvaluator {

    /**
     * The Horizon API proxy service.
     *
     * @var HorizonApiProxyService
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * Construct the alert rule evaluator.
     *
     * @param HorizonApiProxyService $horizonApi
     */
    public function __construct(HorizonApiProxyService $horizonApi) {
        $this->horizonApi = $horizonApi;
    }

    /**
     * Evaluate the given alert rule for the provided context.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return bool
     */
    public function evaluate(Alert $alert, int $serviceId, ?string $jobUuid): bool {
        return match ($alert->rule_type) {
            'job_specific_failure' => $this->private__evaluateJobSpecificFailure($alert, $serviceId, $jobUuid),
            'job_type_failure' => $this->private__evaluateJobTypeFailure($alert, $serviceId),
            'failure_count' => $this->private__evaluateFailureCount($alert, $serviceId),
            'avg_execution_time' => $this->private__evaluateAvgExecutionTime($alert, $serviceId),
            'queue_blocked' => $this->private__evaluateQueueBlocked($alert, $serviceId),
            'worker_offline' => $this->private__evaluateWorkerOffline($alert, $serviceId),
            'supervisor_offline' => $this->private__evaluateSupervisorOffline($alert, $serviceId),
            default => false,
        };
    }

    /**
     * Evaluate a job specific failure rule.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return bool
     */
    private function private__evaluateJobSpecificFailure(Alert $alert, int $serviceId, ?string $jobUuid): bool {
        if (empty($jobUuid)) {
            return false;
        }

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return false;
        }

        $response = $this->horizonApi->getFailedJob($service, $jobUuid);
        $data = $response['data'] ?? null;

        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return false;
        }

        if ($alert->job_type !== null && (string) $alert->job_type !== '') {
            $payload = $data['payload'] ?? [];
            $displayName = $payload['displayName'] ?? null;
            $rawJob = $payload['job'] ?? null;
            $haystack = (string) ($displayName ?? $rawJob ?? '');
            if (! \str_contains($haystack, $alert->job_type)) {
                return false;
            }
        }

        if (! empty($alert->queue) ) {
            if ((string) ($data['queue'] ?? '') !== (string) $alert->queue) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse failed_at from Horizon API (string, Unix timestamp, or Carbon) to Carbon.
     *
     * @param mixed $value
     * @return Carbon|null
     */
    private function private__parseFailedAt(mixed $value): ?Carbon {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            if ($value instanceof Carbon) {
                return $value;
            }
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value);
            }
            if (\is_numeric($value)) {
                return Carbon::createFromTimestamp((int) $value);
            }
            if (\is_string($value)) {
                return Carbon::parse($value);
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    /**
     * Evaluate the job type failure.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function private__evaluateJobTypeFailure(Alert $alert, int $serviceId): bool {
        if (! $alert->job_type) {
            return false;
        }

        $threshold = $alert->threshold ?? [];
        $minutes = (int) (isset($threshold['minutes']) ? $threshold['minutes'] : 15);

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return false;
        }

        $response = $this->horizonApi->getFailedJobs($service, ['starting_at' => 0]);
        $data = $response['data'] ?? null;

        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return false;
        }

        $cutoff = \now()->subMinutes($minutes);
        $recent = collect($data['jobs'] ?? [])
            ->filter(function ($job) use ($alert, $cutoff) {
                if (! \is_array($job)) {
                    return false;
                }

                $failedAt = $this->private__parseFailedAt($job['failed_at'] ?? null);
                if ($failedAt === null || $failedAt->lt($cutoff)) {
                    return false;
                }

                $payload = $job['payload'] ?? [];
                $displayName = $payload['displayName'] ?? null;
                $rawJob = $payload['job'] ?? null;
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
    private function private__evaluateFailureCount(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $count = (int) (isset($threshold['count']) ? $threshold['count'] : 5);
        $minutes = (int) (isset($threshold['minutes']) ? $threshold['minutes'] : 15);

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return false;
        }

        $response = $this->horizonApi->getFailedJobs($service, ['starting_at' => 0]);
        $data = $response['data'] ?? null;

        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return false;
        }

        $cutoff = \now()->subMinutes($minutes);
        $jobs = collect($data['jobs'] ?? []);

        if ($alert->queue) {
            $jobs = $jobs->filter(function ($job) use ($alert) {
                return \is_array($job) && (string) ($job['queue'] ?? '') === (string) $alert->queue;
            });
        }

        $actual = $jobs->filter(function ($job) use ($cutoff) {
            if (! \is_array($job)) {
                return false;
            }
            $failedAt = $this->private__parseFailedAt($job['failed_at'] ?? null);
            return $failedAt !== null && $failedAt->gte($cutoff);
        })->count();

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
    private function private__evaluateAvgExecutionTime(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $maxSeconds = (float) (isset($threshold['seconds']) ? $threshold['seconds'] : 60);
        $minutes = (int) (isset($threshold['minutes']) ? $threshold['minutes'] : 15);

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return false;
        }

        $response = $this->horizonApi->getCompletedJobs($service, ['starting_at' => 0]);
        $data = $response['data'] ?? null;

        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return false;
        }

        $jobs = collect($data['jobs'] ?? []);
        $cutoff = \now()->subMinutes($minutes);

        $durations = $jobs->map(function ($job) use ($cutoff) {
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
            return false;
        }

        $avg = $durations->average();

        return (float) $avg >= $maxSeconds;
    }

    /**
     * Evaluate the queue blocked.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function private__evaluateQueueBlocked(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $minutes = (int) (isset($threshold['minutes']) ? $threshold['minutes'] : 30);

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return false;
        }

        $response = $this->horizonApi->getCompletedJobs($service, ['starting_at' => 0]);
        $data = $response['data'] ?? null;

        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return false;
        }

        $jobs = collect($data['jobs'] ?? []);

        if ($alert->queue) {
            $jobs = $jobs->filter(function ($job) use ($alert) {
                return \is_array($job) && (string) ($job['queue'] ?? '') === (string) $alert->queue;
            });
        }

        $lastProcessed = $jobs->map(function ($job) {
            if (! \is_array($job)) {
                return null;
            }
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

        $triggered = $lastProcessed->copy()->addMinutes($minutes)->isPast();

        return $triggered;
    }

    /**
     * Evaluate the worker offline.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function private__evaluateWorkerOffline(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $minutes = (int) (isset($threshold['minutes']) ? $threshold['minutes'] : 5);

        $service = Service::find($serviceId);
        if (! $service || ! $service->last_seen_at) {
            return false;
        }

        $triggered = $service->last_seen_at->copy()->addMinutes($minutes)->isPast();

        return $triggered;
    }

    /**
     * Evaluate the supervisor offline rule.
     * Triggers when the service has at least one supervisor whose last_seen_at is older than the threshold.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function private__evaluateSupervisorOffline(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $minutes = (int) ($threshold['minutes'] ?? 5);

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return false;
        }

        $response = $this->horizonApi->getMasters($service);
        $data = $response['data'] ?? null;

        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return false;
        }

        $staleAt = \now()->subMinutes($minutes);
        $staleFound = false;

        foreach ($data as $master) {
            if (! \is_array($master) || ! isset($master['supervisors']) || ! \is_array($master['supervisors'])) {
                continue;
            }
            foreach ($master['supervisors'] as $supervisor) {
                if (! \is_array($supervisor)) {
                    continue;
                }
                $lastSeenRaw = $supervisor['last_heartbeat_at'] ?? ($supervisor['lastSeen'] ?? null);
                if (! \is_string($lastSeenRaw) || $lastSeenRaw === '') {
                    continue;
                }
                try {
                    $lastSeen = Carbon::parse($lastSeenRaw);
                } catch (\Throwable $e) {
                    continue;
                }
                if ($lastSeen->lt($staleAt)) {
                    $staleFound = true;
                    break 2;
                }
            }
        }

        return $staleFound;
    }
}
