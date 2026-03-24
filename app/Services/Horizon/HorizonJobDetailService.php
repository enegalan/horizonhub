<?php

namespace App\Services\Horizon;

use App\Models\Service;
use App\Support\Horizon\JobRuntimeHelper;

class HorizonJobDetailService
{
    /**
     * Build the show view data.
     *
     * @param  array<string, mixed>  $jobData
     * @return array{job: object, exception: string|null, horizonJob: array<string, mixed>}
     */
    public function buildShowViewData(Service $service, array $jobData, string $routeUuid): array
    {
        $payload = isset($jobData['payload']) && \is_array($jobData['payload']) ? $jobData['payload'] : [];

        $attemptsRaw = $jobData['attempts'] ?? null;
        if ($attemptsRaw === null) {
            $attemptsRaw = $payload['attempts'] ?? null;
        }

        $retries = null;
        if (isset($jobData['retried_by']) && \is_array($jobData['retried_by'])) {
            $retries = \count($jobData['retried_by']);
            if ($attemptsRaw === null) {
                $attemptsRaw = $retries + 1;
            }
        }

        $attempts = null;
        if ($attemptsRaw !== null) {
            $attemptsInt = (int) $attemptsRaw;
            if ($attemptsInt > 0) {
                $attempts = $attemptsInt;
            }
        }

        $connection = isset($jobData['connection']) ? (string) $jobData['connection'] : null;

        $tags = [];
        if (isset($jobData['tags']) && \is_array($jobData['tags'])) {
            $tags = \array_values(\array_filter($jobData['tags'], static function ($tag) {
                return \is_string($tag) && $tag !== '';
            }));
        }

        $exception = null;
        if (isset($jobData['exception']) && (string) $jobData['exception'] !== '') {
            $exception = (string) $jobData['exception'];
        }

        $rawStatus = (string) ($jobData['status'] ?? 'failed');
        $status = $rawStatus === 'completed' ? 'processed' : $rawStatus;

        $queuedAtRaw = $payload['pushedAt'] ?? $jobData['pushedAt'] ?? null;
        $processedAtRaw = $jobData['completed_at'] ?? null;
        $failedAtRaw = $jobData['failed_at'] ?? null;
        if ($failedAtRaw === false) {
            $failedAtRaw = null;
        }

        $queuedAt = JobRuntimeHelper::parseJobTimestamp($queuedAtRaw);
        $processedAt = JobRuntimeHelper::parseJobTimestamp($processedAtRaw);
        $failedAt = JobRuntimeHelper::parseJobTimestamp($failedAtRaw);
        JobRuntimeHelper::normalizeStatusDates($status, $processedAt, $failedAt);
        $runtimeSeconds = isset($jobData['runtime']) && \is_numeric($jobData['runtime'])
            ? (float) $jobData['runtime']
            : null;

        $runtime = JobRuntimeHelper::getFormattedRuntime(
            JobRuntimeHelper::getRuntimeSeconds($runtimeSeconds, $queuedAt, $processedAt, $failedAt)
        );

        $jobView = (object) [
            'uuid' => $jobData['uuid'] ?? $routeUuid,
            'name' => $jobData['name'] ?? ($jobData['displayName'] ?? null),
            'queue' => $jobData['queue'] ?? null,
            'status' => $status,
            'attempts' => $attempts,
            'queued_at' => $queuedAt,
            'processed_at' => $processedAt,
            'failed_at' => $failedAt,
            'runtime' => $runtime,
            'payload' => $jobData['payload'] ?? null,
            'service' => $service,
        ];

        $horizonJob = [
            'attempts' => $attempts,
            'connection' => $connection,
            'retries' => $retries,
            'tags' => $tags,
            'uuid' => $jobView->uuid,
            'exception' => $exception,
        ];

        return [
            'job' => $jobView,
            'exception' => $exception,
            'horizonJob' => $horizonJob,
        ];
    }
}
