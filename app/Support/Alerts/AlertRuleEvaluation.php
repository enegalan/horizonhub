<?php

namespace App\Support\Alerts;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Jobs\JobsWindowFetcher;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class AlertRuleEvaluation
{
    /**
     * The jobs window fetcher.
     */
    private JobsWindowFetcher $jobsWindowFetcher;

    /**
     * The constructor.
     *
     * @param JobsWindowFetcher $jobsWindowFetcher The jobs window fetcher.
     */
    public function __construct(JobsWindowFetcher $jobsWindowFetcher)
    {
        $this->jobsWindowFetcher = $jobsWindowFetcher;
    }

    /**
     * Collect the triggering job UUIDs.
     *
     * @param Collection<int, array<string, mixed>> $jobs
     *
     * @return array<int, string>
     */
    public function collectTriggeringJobUuids(Collection $jobs): array
    {
        return $jobs
            ->map(function ($job) {
                return ! empty($job['id']) ? (string) $job['id'] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Filter the failed jobs in the window.
     *
     * @param Collection<int, mixed> $jobs
     * @param Carbon $cutoff The cutoff time.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function filterFailedJobsInWindow(Collection $jobs, Carbon $cutoff): Collection
    {
        return $jobs->filter(function ($job) use ($cutoff) {
            if (! \is_array($job)) {
                return false;
            }
            $failedAt = $this->private__parseTimestamp($job['failed_at'] ?? null);

            return $failedAt !== null && $failedAt->gte($cutoff);
        });
    }

    /**
     * Check if the job matches the queue patterns.
     *
     * @param array<string, mixed> $job
     */
    public function jobMatchesQueuePatterns(Alert $alert, array $job): bool
    {
        $patterns = $this->resolveQueuePatterns($alert);

        if (empty($patterns)) {
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
     * Check if the job row matches the alert queue and job patterns.
     *
     * @param array<string, mixed> $job
     */
    public function jobRowMatches(Alert $alert, array $job): bool
    {
        return $this->jobMatchesQueuePatterns($alert, $job)
            && $this->private__jobMatchesJobPatterns($alert, $job);
    }

    /**
     * Find completed jobs in the window that match the alert.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function matchingCompletedJobsInWindow(Alert $alert, Service $service, Carbon $cutoff): Collection
    {
        $jobs = collect($this->jobsWindowFetcher->fetchCompletedJobsSince($service, $cutoff->getTimestamp()));

        return $jobs->filter(function (array $job) use ($alert, $cutoff) {
            $completedAt = $this->parseCompletedAt($job);

            if ($completedAt === null || $completedAt->lt($cutoff)) {
                return false;
            }

            return $this->jobRowMatches($alert, $job);
        })->values();
    }

    /**
     * Find the matching failed jobs in the window.
     *
     * @param Alert $alert The alert.
     * @param Service $service The service.
     * @param Carbon $cutoff The cutoff time.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function matchingFailedJobsInWindow(Alert $alert, Service $service, Carbon $cutoff): Collection
    {
        $jobs = collect($this->jobsWindowFetcher->fetchFailedJobsSince($service, $cutoff->getTimestamp()));

        return $this->filterFailedJobsInWindow($jobs, $cutoff)
            ->filter(function ($job) use ($alert) {
                return $this->jobRowMatches($alert, $job);
            })
            ->values();
    }

    /**
     * Parse the completed at timestamp.
     *
     * @param array<string, mixed> $job The job.
     *
     * @return CarbonInterface|null The completed at time.
     */
    public function parseCompletedAt(array $job): ?CarbonInterface
    {
        return $this->private__parseTimestamp($job['completed_at'] ?? $job['processed_at'] ?? null);
    }

    /**
     * Resolve the job patterns from the alert threshold.
     *
     * @param Alert $alert The alert.
     *
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

        if (! empty($patterns)) {
            return \array_values(\array_unique($patterns));
        }

        if (! empty($alert->job_type)) {
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
     * @param Alert $alert The alert.
     * @param array<string, mixed> $job The job.
     */
    private function private__jobMatchesJobPatterns(Alert $alert, array $job): bool
    {
        $patterns = $this->resolveJobPatterns($alert);

        if (empty($patterns)) {
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
     * @param array<string, mixed> $job The job.
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
     * Parse a timestamp value into Carbon.
     *
     * @param mixed $value The value to parse.
     */
    private function private__parseTimestamp(mixed $value): ?CarbonInterface
    {
        if (blank($value)) {
            return null;
        }

        try {
            if ($value instanceof CarbonInterface) {
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
