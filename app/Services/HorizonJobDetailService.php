<?php

namespace App\Services;

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
        $attemptsRaw = $jobData['attempts'] ?? null;
        if ($attemptsRaw === null && isset($jobData['payload']) && \is_array($jobData['payload'])) {
            $attemptsRaw = $jobData['payload']['attempts'] ?? null;
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

        $queuedAt = $jobData['pushedAt'] ?? null;
        $processedAt = $jobData['completed_at'] ?? null;
        $failedAt = $jobData['failed_at'] ?? null;
        $runtimeSeconds = isset($jobData['runtime']) && \is_numeric($jobData['runtime'])
            ? (float) $jobData['runtime']
            : null;

        $runtime = JobRuntimeHelper::getFormattedRuntime(
            JobRuntimeHelper::getRuntimeSeconds($runtimeSeconds, $queuedAt, $processedAt, $failedAt)
        );

        $rawStatus = (string) ($jobData['status'] ?? 'failed');
        $status = $rawStatus === 'completed' ? 'processed' : $rawStatus;

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
