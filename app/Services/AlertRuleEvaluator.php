<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Service;
use App\Services\HorizonApiProxyService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
     * Maximum number of triggering job UUIDs to attach to an alert (avoids oversized batches).
     *
     * @var int
     */
    private const MAX_TRIGGERING_JOB_UUIDS = 20;

    /**
     * Evaluate the given alert rule for the provided context.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return bool
     */
    public function evaluate(Alert $alert, int $serviceId, ?string $jobUuid): bool {
        return $this->evaluateWithTriggeringJobs($alert, $serviceId, $jobUuid)['triggered'];
    }

    /**
     * Evaluate the alert rule and return whether it triggered plus the list of job UUIDs that triggered it.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array {
        return match ($alert->rule_type) {
            'job_specific_failure' => $this->private__evaluateJobSpecificFailureWithJobs($alert, $serviceId, $jobUuid),
            'job_type_failure' => $this->private__evaluateJobTypeFailureWithJobs($alert, $serviceId),
            'failure_count' => $this->private__evaluateFailureCountWithJobs($alert, $serviceId),
            'avg_execution_time' => $this->private__evaluateAvgExecutionTimeWithJobs($alert, $serviceId),
            'queue_blocked' => $this->private__evaluateQueueBlockedWithJobs($alert, $serviceId),
            'worker_offline' => $this->private__evaluateWorkerOfflineWithJobs($alert, $serviceId),
            'supervisor_offline' => $this->private__evaluateSupervisorOfflineWithJobs($alert, $serviceId),
            'horizon_offline' => $this->private__evaluateHorizonOfflineWithJobs($alert, $serviceId),
            default => ['triggered' => false, 'job_uuids' => []],
        };
    }

    /**
     * Resolve the job patterns from the alert threshold.
     *
     * @param Alert $alert
     * @return array<int, string>
     */
    private function private__resolveJobPatterns(Alert $alert): array {
        $threshold = $alert->threshold ?? [];
        $fromThreshold = $threshold['job_patterns'] ?? [];
        if (! \is_array($fromThreshold)) {
            $fromThreshold = [];
        }
        $patterns = [];
        foreach ($fromThreshold as $p) {
            if (\is_string($p) && $p !== '') {
                $patterns[] = $p;
            }
        }
        if ($patterns !== []) {
            return \array_values(\array_unique($patterns));
        }

        if ($alert->job_type !== null && (string) $alert->job_type !== '') {
            return [(string) $alert->job_type];
        }

        return [];
    }

    /**
     * Resolve the queue patterns from the alert threshold.
     *
     * @param Alert $alert
     * @return list<string>
     */
    private function private__resolveQueuePatterns(Alert $alert): array {
        $threshold = $alert->threshold ?? [];
        $fromThreshold = $threshold['queue_patterns'] ?? [];
        if (! \is_array($fromThreshold)) {
            $fromThreshold = [];
        }
        $patterns = [];
        foreach ($fromThreshold as $p) {
            if (\is_string($p) && $p !== '') {
                $patterns[] = $p;
            }
        }
        if (! empty($alert->queue)) {
            $q = (string) $alert->queue;
            if (! \in_array($q, $patterns, true)) {
                $patterns[] = $q;
            }
        }

        return \array_values(\array_unique($patterns));
    }

    /**
     * Build the haystack for job pattern matching.
     *
     * @param array<string, mixed> $job
     * @return string
     */
    private function private__jobPayloadHaystack(array $job): string {
        $payload = $job['payload'] ?? [];
        if (! \is_array($payload)) {
            $payload = [];
        }
        $displayName = $payload['displayName'] ?? null;
        $rawJob = $payload['job'] ?? null;

        return (string) ($displayName ?? $rawJob ?? '');
    }

    /**
     * Empty job patterns means no filter (match any job class).
     *
     * @param Alert $alert
     * @param array<string, mixed> $job
     */
    private function private__job_matches_job_patterns(Alert $alert, array $job): bool {
        $patterns = $this->private__resolveJobPatterns($alert);
        if ($patterns === []) {
            return true;
        }
        $haystack = $this->private__jobPayloadHaystack($job);
        foreach ($patterns as $pattern) {
            if ($pattern !== '' && \str_contains($haystack, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Empty queue patterns means no filter (any queue).
     *
     * @param array<string, mixed> $job
     */
    private function private__job_matches_queue_patterns(Alert $alert, array $job): bool {
        $patterns = $this->private__resolveQueuePatterns($alert);
        if ($patterns === []) {
            return true;
        }
        $queue = (string) ($job['queue'] ?? '');

        foreach ($patterns as $pattern) {
            if ($queue === (string) $pattern) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the failed job row matches the alert.
     *
     * @param Alert $alert
     * @param array<string, mixed> $job
     */
    private function private__failedJobRowMatchesAlert(Alert $alert, array $job): bool {
        return $this->private__job_matches_queue_patterns($alert, $job)
            && $this->private__job_matches_job_patterns($alert, $job);
    }

    /**
     * Check if the completed job row matches the alert.
     *
     * @param Alert $alert
     * @param array<string, mixed> $job
     */
    private function private__completedJobRowMatchesAlert(Alert $alert, array $job): bool {
        return $this->private__job_matches_queue_patterns($alert, $job)
            && $this->private__job_matches_job_patterns($alert, $job);
    }

    /**
     * Filter the failed jobs in the window.
     *
     * @param Collection<int, mixed> $jobs
     * @param Carbon $cutoff
     * @return Collection<int, array<string, mixed>>
     */
    private function private__filterFailedJobsInWindow(Collection $jobs, Carbon $cutoff): Collection {
        return $jobs->filter(function ($job) use ($cutoff) {
            if (! \is_array($job)) {
                return false;
            }
            $failedAt = $this->private__parseFailedAt($job['failed_at'] ?? null);

            return $failedAt !== null && $failedAt->gte($cutoff);
        });
    }

    /**
     * Find the matching failed jobs in the window.
     *
     * @param Alert $alert
     * @param Service $service
     * @param Carbon $cutoff
     * @return Collection<int, array<string, mixed>>
     */
    private function private__matchingFailedJobsInWindow(Alert $alert, Service $service, Carbon $cutoff): Collection {
        $response = $this->horizonApi->getFailedJobs($service, ['starting_at' => 0]);
        $data = $response['data'] ?? null;
        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return collect();
        }
        $jobs = collect($data['jobs'] ?? []);

        return $this->private__filterFailedJobsInWindow($jobs, $cutoff)
            ->filter(function ($job) use ($alert) {
                return \is_array($job) && $this->private__failedJobRowMatchesAlert($alert, $job);
            })
            ->values();
    }

    /**
     * Evaluate job specific failure and return triggering job UUIDs.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    private function private__evaluateJobSpecificFailureWithJobs(Alert $alert, int $serviceId, ?string $jobUuid): array {
        if (empty($jobUuid)) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $response = $this->horizonApi->getFailedJob($service, $jobUuid);
        $data = $response['data'] ?? null;
        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        if (! $this->private__failedJobRowMatchesAlert($alert, $data)) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $threshold = $alert->threshold ?? [];
        $minCount = (int) ($threshold['count'] ?? 1);
        if ($minCount < 1) {
            $minCount = 1;
        }
        $minutes = (int) ($threshold['minutes'] ?? 15);
        if ($minutes < 1) {
            $minutes = 15;
        }

        if ($minCount <= 1) {
            return [
                'triggered' => true,
                'job_uuids' => [(string) $jobUuid],
            ];
        }

        $cutoff = \now()->subMinutes($minutes);
        $matching = $this->private__matchingFailedJobsInWindow($alert, $service, $cutoff);
        if ($matching->count() < $minCount) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $jobUuids = $matching->take(self::MAX_TRIGGERING_JOB_UUIDS)
            ->map(function ($job) {
                $id = $job['id'] ?? null;

                return $id !== null && $id !== '' ? (string) $id : null;
            })
            ->filter()
            ->values()
            ->all();

        return [
            'triggered' => true,
            'job_uuids' => $jobUuids,
        ];
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
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    private function private__evaluateJobTypeFailureWithJobs(Alert $alert, int $serviceId): array {
        $patterns = $this->private__resolveJobPatterns($alert);
        if ($patterns === []) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $threshold = $alert->threshold ?? [];
        $minutes = (int) ($threshold['minutes'] ?? 15);
        if ($minutes < 1) {
            $minutes = 15;
        }
        $minCount = (int) ($threshold['count'] ?? 1);
        if ($minCount < 1) {
            $minCount = 1;
        }

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $cutoff = \now()->subMinutes($minutes);
        $matching = $this->private__matchingFailedJobsInWindow($alert, $service, $cutoff);
        if ($matching->count() < $minCount) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $forUuids = $matching->take(self::MAX_TRIGGERING_JOB_UUIDS);
        $jobUuids = $forUuids->map(function ($job) {
            $id = $job['id'] ?? null;

            return $id !== null && $id !== '' ? (string) $id : null;
        })->filter()->values()->all();

        return [
            'triggered' => true,
            'job_uuids' => $jobUuids,
        ];
    }

    /**
     * Evaluate the failure count.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    private function private__evaluateFailureCountWithJobs(Alert $alert, int $serviceId): array {
        $threshold = $alert->threshold ?? [];
        $count = (int) ($threshold['count'] ?? 5);
        $minutes = (int) ($threshold['minutes'] ?? 15);

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
        $inWindow = $this->private__filterFailedJobsInWindow($jobs, $cutoff)
            ->filter(function ($job) use ($alert) {
                return \is_array($job) && $this->private__failedJobRowMatchesAlert($alert, $job);
            })
            ->values();

        $actual = $inWindow->count();
        $triggered = $actual >= $count;

        $jobUuids = [];
        if ($triggered) {
            $jobUuids = $inWindow->take(self::MAX_TRIGGERING_JOB_UUIDS)
                ->map(function ($job) {
                    $id = $job['id'] ?? null;

                    return $id !== null && $id !== '' ? (string) $id : null;
                })
                ->filter()
                ->values()
                ->all();
        }

        return [
            'triggered' => $triggered,
            'job_uuids' => $jobUuids,
        ];
    }

    /**
     * Evaluate the average execution time.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    private function private__evaluateAvgExecutionTimeWithJobs(Alert $alert, int $serviceId): array {
        $threshold = $alert->threshold ?? [];
        $maxSeconds = (float) ($threshold['seconds'] ?? 60);
        $minutes = (int) ($threshold['minutes'] ?? 15);

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
            return \is_array($job) && $this->private__completedJobRowMatchesAlert($alert, $job);
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

    /**
     * Evaluate the queue blocked.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function private__evaluateQueueBlocked(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $minutes = (int) ($threshold['minutes'] ?? 30);

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

        $queuePatterns = $this->private__resolveQueuePatterns($alert);
        if ($queuePatterns !== []) {
            $jobs = $jobs->filter(function ($job) use ($alert) {
                return \is_array($job) && $this->private__job_matches_queue_patterns($alert, $job);
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

        return $lastProcessed->copy()->addMinutes($minutes)->isPast();
    }

    /**
     * Evaluate the queue blocked with jobs.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    private function private__evaluateQueueBlockedWithJobs(Alert $alert, int $serviceId): array {
        return [
            'triggered' => $this->private__evaluateQueueBlocked($alert, $serviceId),
            'job_uuids' => [],
        ];
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
        $minutes = (int) ($threshold['minutes'] ?? 5);

        $service = Service::find($serviceId);
        if (! $service || ! $service->last_seen_at) {
            return false;
        }

        return $service->last_seen_at->copy()->addMinutes($minutes)->isPast();
    }

    /**
     * Evaluate the worker offline with jobs.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    private function private__evaluateWorkerOfflineWithJobs(Alert $alert, int $serviceId): array {
        return [
            'triggered' => $this->private__evaluateWorkerOffline($alert, $serviceId),
            'job_uuids' => [],
        ];
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

    /**
     * Evaluate the supervisor offline with jobs.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    private function private__evaluateSupervisorOfflineWithJobs(Alert $alert, int $serviceId): array {
        return [
            'triggered' => $this->private__evaluateSupervisorOffline($alert, $serviceId),
            'job_uuids' => [],
        ];
    }

    /**
     * Evaluate the Horizon offline rule.
     * Triggers when Horizon status is not active/running for at least the configured minutes.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function private__evaluateHorizonOffline(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $minutes = (int) ($threshold['minutes'] ?? 5);

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return false;
        }

        if ((string) $service->status === 'offline') {
            if (! $service->last_seen_at) {
                return true;
            }
            return $service->last_seen_at->copy()->addMinutes($minutes)->isPast();
        }

        $response = $this->horizonApi->getStats($service);
        $data = $response['data'] ?? null;
        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return false;
        }

        $status = \strtolower((string) ($data['status'] ?? ''));
        if ($status === 'active' || $status === 'running') {
            return false;
        }

        if (! \in_array($status, ['inactive', 'offline', 'paused'], true)) {
            return false;
        }

        $referenceTime = $service->last_seen_at;
        if (! $referenceTime) {
            return true;
        }

        return $referenceTime->copy()->addMinutes($minutes)->isPast();
    }

    /**
     * Evaluate the Horizon offline rule with jobs.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    private function private__evaluateHorizonOfflineWithJobs(Alert $alert, int $serviceId): array {
        return [
            'triggered' => $this->private__evaluateHorizonOffline($alert, $serviceId),
            'job_uuids' => [],
        ];
    }
}
