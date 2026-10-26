<?php

namespace App\Support\Horizon;

use App\Support\NumberFormatter;

final class StatsReader
{
    /**
     * Get the number of failed jobs.
     *
     * @param array<string, mixed>|null $data
     */
    public static function failedJobs(?array $data): int
    {
        return NumberFormatter::nonNegativeInt($data['failedJobs'] ?? null);
    }

    /**
     * Get the number of jobs past minute.
     *
     * @param array<string, mixed>|null $data
     */
    public static function jobsPastMinute(?array $data): int
    {
        if ($data === null) {
            return 0;
        }

        $jobsPerMinute = NumberFormatter::floatOrNull($data['jobsPerMinute'] ?? null) ?? 0.0;

        if ($jobsPerMinute > 0) {
            return (int) \round($jobsPerMinute);
        }

        $recent = NumberFormatter::nonNegativeInt($data['recentJobs'] ?? null);
        $period = NumberFormatter::intOrNull($data['periods']['recentJobs'] ?? null) ?? 60;

        if ($recent >= 0 && $period > 0) {
            return (int) \round($recent / $period);
        }

        return 0;
    }

    /**
     * Get the queue with the longest wait time.
     *
     * @param array<string, mixed>|null $data
     *
     * @return array{queue: string, seconds: float}|null
     */
    public static function maxWaitEntry(?array $data): ?array
    {
        if ($data === null || ! isset($data['wait']) || ! \is_array($data['wait'])) {
            return null;
        }

        $entry = null;

        foreach ($data['wait'] as $queue => $waitValue) {
            $seconds = NumberFormatter::floatOrNull($waitValue);

            if ($seconds === null || $seconds <= 0.0) {
                continue;
            }

            if ($entry === null || $seconds > $entry['seconds']) {
                $entry = [
                    'queue' => (string) $queue,
                    'seconds' => $seconds,
                ];
            }
        }

        return $entry;
    }

    /**
     * Get the maximum wait time in seconds.
     *
     * @param array<string, mixed>|null $data
     */
    public static function maxWaitTimeSeconds(?array $data): ?float
    {
        return self::maxWaitEntry($data)['seconds'] ?? null;
    }

    /**
     * Get the number of processes.
     *
     * @param array<string, mixed>|null $data
     */
    public static function processes(?array $data): ?int
    {
        return NumberFormatter::intOrNull($data['processes'] ?? null);
    }

    /**
     * Get the queue with the maximum runtime.
     *
     * @param array<string, mixed>|null $data
     */
    public static function queueWithMaxRuntime(?array $data): ?string
    {
        if ($data === null || ! isset($data['queueWithMaxRuntime']) || (string) $data['queueWithMaxRuntime'] === '') {
            return null;
        }

        return (string) $data['queueWithMaxRuntime'];
    }

    /**
     * Get the queue with the maximum throughput.
     *
     * @param array<string, mixed>|null $data
     */
    public static function queueWithMaxThroughput(?array $data): ?string
    {
        if ($data === null || ! isset($data['queueWithMaxThroughput']) || (string) $data['queueWithMaxThroughput'] === '') {
            return null;
        }

        return (string) $data['queueWithMaxThroughput'];
    }

    /**
     * Get the number of recent jobs.
     *
     * @param array<string, mixed>|null $data
     */
    public static function recentJobs(?array $data): int
    {
        return NumberFormatter::nonNegativeInt($data['recentJobs'] ?? null);
    }

    /**
     * Get the status.
     *
     * @param array<string, mixed>|null $data
     */
    public static function status(?array $data): ?string
    {
        if ($data === null || empty($data['status'])) {
            return null;
        }

        return (string) $data['status'];
    }

    /**
     * Typed stats snapshot. Callers may use only the keys they need.
     *
     * @param array<string, mixed>|null $data
     *
     * @return array{
     *     failedJobs: int,
     *     jobsPastMinute: int,
     *     maxWaitTimeSeconds: float|null,
     *     processes: int|null,
     *     queueWithMaxRuntime: string|null,
     *     queueWithMaxThroughput: string|null,
     *     recentJobs: int,
     *     status: string|null
     * }
     */
    public static function summary(?array $data): array
    {
        return [
            'failedJobs' => self::failedJobs($data),
            'jobsPastMinute' => self::jobsPastMinute($data),
            'maxWaitTimeSeconds' => self::maxWaitTimeSeconds($data),
            'processes' => self::processes($data),
            'queueWithMaxRuntime' => self::queueWithMaxRuntime($data),
            'queueWithMaxThroughput' => self::queueWithMaxThroughput($data),
            'recentJobs' => self::recentJobs($data),
            'status' => self::status($data),
        ];
    }
}
