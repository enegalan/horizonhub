<?php

namespace App\Support\Horizon;

final class StatsReader
{
    /**
     * Get the number of failed jobs.
     *
     * @param array<string, mixed>|null $data
     */
    public static function failedJobs(?array $data): int
    {
        return isset($data['failedJobs']) ? (int) $data['failedJobs'] : 0;
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

        $jobsPerMinute = isset($data['jobsPerMinute']) ? (float) $data['jobsPerMinute'] : 0.0;

        if ($jobsPerMinute > 0) {
            return (int) \round($jobsPerMinute);
        }

        $recent = isset($data['recentJobs']) ? (int) $data['recentJobs'] : 0;
        $period = isset($data['periods']['recentJobs']) ? (int) $data['periods']['recentJobs'] : 60;

        if ($recent >= 0 && $period > 0) {
            return (int) \round($recent / $period);
        }

        return 0;
    }

    /**
     * Get the maximum wait time in seconds.
     *
     * @param array<string, mixed>|null $data
     */
    public static function maxWaitTimeSeconds(?array $data): ?float
    {
        if ($data === null || ! isset($data['wait']) || ! \is_array($data['wait'])) {
            return null;
        }

        $maxWaitTimeSeconds = null;

        foreach ($data['wait'] as $waitValue) {
            if (! \is_numeric($waitValue)) {
                continue;
            }

            $seconds = (float) $waitValue;

            if ($seconds <= 0.0) {
                continue;
            }

            if ($maxWaitTimeSeconds === null || $seconds > $maxWaitTimeSeconds) {
                $maxWaitTimeSeconds = $seconds;
            }
        }

        return $maxWaitTimeSeconds;
    }

    /**
     * Get the number of processes.
     *
     * @param array<string, mixed>|null $data
     */
    public static function processes(?array $data): ?int
    {
        if ($data === null || ! isset($data['processes']) || ! \is_numeric($data['processes'])) {
            return null;
        }

        return (int) $data['processes'];
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
        return isset($data['recentJobs']) ? (int) $data['recentJobs'] : 0;
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
