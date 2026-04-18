<?php

namespace App\Services\Horizon;

use App\Models\Service;
use App\Support\Horizon\JobCommandDataExtractor;
use App\Support\Horizon\JobRuntimeHelper;

class HorizonJobDetailService
{
    /**
     * Build the show view data.
     *
     * @param  Service  $service  The service.
     * @param  array<string, mixed>  $jobData  The job data.
     * @param  string  $routeUuid  The route UUID.
     * @return array{
     *     job: object,
     *     exception: string|null,
     *     horizonJob: array{
     *         attempts: int|null,
     *         connection: string|null,
     *         retries: int|null,
     *         tags: array<int, string>,
     *         uuid: string,
     *         exception: string|null,
     *         retriedBy: array<int, array{id: string, status: string|null, retried_at: int|null}>,
     *         context: array<string, mixed>|string|null,
     *         commandData: array<string, mixed>|null
     *     }
     * }
     */
    public function buildShowViewData(Service $service, array $jobData, string $routeUuid): array
    {
        $payload = isset($jobData['payload']) && \is_array($jobData['payload']) ? $jobData['payload'] : [];

        $attemptsRaw = $jobData['attempts'] ?? null;
        if ($attemptsRaw === null) {
            $attemptsRaw = $payload['attempts'] ?? null;
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

        $retriedBy = [];
        if (isset($jobData['retried_by']) && \is_array($jobData['retried_by'])) {
            foreach ($jobData['retried_by'] as $retriedJob) {
                if (! \is_array($retriedJob) || ! isset($retriedJob['id']) || ! \is_string($retriedJob['id']) || $retriedJob['id'] === '') {
                    continue;
                }

                $retriedStatus = null;
                if (isset($retriedJob['status']) && \is_string($retriedJob['status']) && $retriedJob['status'] !== '') {
                    $retriedStatus = $retriedJob['status'];
                }

                $retriedAt = null;
                if (isset($retriedJob['retried_at']) && \is_numeric($retriedJob['retried_at'])) {
                    $retriedAt = (int) $retriedJob['retried_at'];
                }

                $retriedBy[] = [
                    'id' => $retriedJob['id'],
                    'status' => $retriedStatus,
                    'retried_at' => $retriedAt,
                ];
            }
        }
        $retries = $retriedBy !== [] ? \count($retriedBy) : null;
        if ($attemptsRaw === null && $retries !== null) {
            $attemptsRaw = $retries + 1;
            $attempts = $attemptsRaw;
        }

        $context = null;
        if (isset($jobData['context']) && (\is_array($jobData['context']) || \is_string($jobData['context']))) {
            $context = $jobData['context'];
        }
        $commandData = JobCommandDataExtractor::extract($payload);

        $rawStatus = (string) ($jobData['status'] ?? 'failed');
        $status = $rawStatus === 'completed' ? 'processed' : $rawStatus;

        $queuedAtRaw = $payload['pushedAt'] ?? $jobData['pushedAt'] ?? null;
        $reservedAtRaw = $jobData['reserved_at'] ?? $jobData['reservedAt'] ?? $payload['reserved_at'] ?? $payload['reservedAt'] ?? null;
        $processedAtRaw = $jobData['completed_at'] ?? null;
        $failedAtRaw = $jobData['failed_at'] ?? null;

        $queuedAt = JobRuntimeHelper::parseJobTimestamp($queuedAtRaw);
        $reservedAt = JobRuntimeHelper::parseJobTimestamp($reservedAtRaw);
        $processedAt = JobRuntimeHelper::parseJobTimestamp($processedAtRaw);
        $failedAt = JobRuntimeHelper::parseJobTimestamp($failedAtRaw);
        JobRuntimeHelper::normalizeStatusDates($status, $processedAt, $failedAt);

        $availableAt = isset($commandData['delay']) && isset($commandData['delay']['date']) ? JobRuntimeHelper::parseJobTimestamp($commandData['delay']['date']) : null;
        $runtimeSeconds = isset($jobData['runtime']) && \is_numeric($jobData['runtime'])
            ? (float) $jobData['runtime']
            : null;

        $runtime = JobRuntimeHelper::getFormattedRuntime(
            JobRuntimeHelper::getRuntimeSeconds($runtimeSeconds, $reservedAt, $processedAt, $failedAt)
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
            'available_at' => $availableAt,
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
            'retriedBy' => $retriedBy,
            'context' => $context,
            'commandData' => $commandData,
        ];

        return [
            'job' => $jobView,
            'exception' => $exception,
            'horizonJob' => $horizonJob,
        ];
    }
}
