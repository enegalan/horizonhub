<?php

namespace App\Services\Alerts\Rules;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class AlertRuleEvaluationSupport
{
    /**
     * The Horizon API proxy service.
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * Construct the evaluation support.
     */
    public function __construct(HorizonApiProxyService $horizonApi)
    {
        $this->horizonApi = $horizonApi;
    }

    /**
     * Collect the triggering job UUIDs.
     *
     * @param  Collection<int, array<string, mixed>>  $jobs
     * @return array<int, string>
     */
    public function collectTriggeringJobUuids(Collection $jobs): array
    {
        return $jobs
            ->map(function ($job) {
                $id = $job['id'] ?? null;

                return $id !== null && $id !== '' ? (string) $id : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Check if the completed job row matches the alert.
     *
     * @param  Alert  $alert  The alert.
     * @param  array<string, mixed>  $job  The job.
     */
    public function completedJobRowMatches(Alert $alert, array $job): bool
    {
        return $this->jobMatchesQueuePatterns($alert, $job)
            && $this->private__jobMatchesJobPatterns($alert, $job);
    }

    /**
     * Check if the failed job row matches the alert.
     *
     * @param  Alert  $alert  The alert.
     * @param  array<string, mixed>  $job  The job.
     */
    public function failedJobRowMatches(Alert $alert, array $job): bool
    {
        return $this->jobMatchesQueuePatterns($alert, $job)
            && $this->private__jobMatchesJobPatterns($alert, $job);
    }

    /**
     * Filter the failed jobs in the window.
     *
     * @param  Collection<int, mixed>  $jobs
     * @param  Carbon  $cutoff  The cutoff time.
     * @return Collection<int, array<string, mixed>>
     */
    public function filterFailedJobsInWindow(Collection $jobs, Carbon $cutoff): Collection
    {
        return $jobs->filter(function ($job) use ($cutoff) {
            if (! \is_array($job)) {
                return false;
            }
            $failedAt = $this->private__parseFailedAt($job['failed_at'] ?? null);

            return $failedAt !== null && $failedAt->gte($cutoff);
        });
    }

    /**
     * Check if the job matches the queue patterns.
     *
     * @param  array<string, mixed>  $job
     */
    public function jobMatchesQueuePatterns(Alert $alert, array $job): bool
    {
        $patterns = $this->resolveQueuePatterns($alert);
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
     * Find the matching failed jobs in the window.
     *
     * @param  Alert  $alert  The alert.
     * @param  Service  $service  The service.
     * @param  Carbon  $cutoff  The cutoff time.
     * @return Collection<int, array<string, mixed>>
     */
    public function matchingFailedJobsInWindow(Alert $alert, Service $service, Carbon $cutoff): Collection
    {
        $response = $this->horizonApi->getFailedJobs($service, ['starting_at' => 0]);
        $data = $response['data'] ?? null;
        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return collect();
        }
        $jobs = collect($data['jobs'] ?? []);

        return $this->filterFailedJobsInWindow($jobs, $cutoff)
            ->filter(function ($job) use ($alert) {
                return \is_array($job) && $this->failedJobRowMatches($alert, $job);
            })
            ->values();
    }

    /**
     * Resolve the job patterns from the alert threshold.
     *
     * @param  Alert  $alert  The alert.
     * @return array<int, string>
     */
    public function resolveJobPatterns(Alert $alert): array
    {
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
     * @return list<string>
     */
    public function resolveQueuePatterns(Alert $alert): array
    {
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
     * Check if the job matches the job patterns.
     *
     * @param  Alert  $alert  The alert.
     * @param  array<string, mixed>  $job  The job.
     */
    private function private__jobMatchesJobPatterns(Alert $alert, array $job): bool
    {
        $patterns = $this->resolveJobPatterns($alert);
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
     * Get the job payload haystack.
     *
     * @param  array<string, mixed>  $job  The job.
     */
    private function private__jobPayloadHaystack(array $job): string
    {
        $payload = $job['payload'] ?? [];
        if (! \is_array($payload)) {
            $payload = [];
        }
        $displayName = $payload['displayName'] ?? null;
        $rawJob = $payload['job'] ?? null;

        return (string) ($displayName ?? $rawJob ?? '');
    }

    /**
     * Parse the failed at value.
     *
     * @param  mixed  $value  The value to parse.
     */
    private function private__parseFailedAt(mixed $value): ?Carbon
    {
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
}
