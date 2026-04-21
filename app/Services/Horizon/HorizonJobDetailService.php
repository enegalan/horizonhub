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
     * @return object
     */
    public function buildShowViewData(Service $service, array $jobData): object
    {
        $payload = isset($jobData['payload']) && \is_array($jobData['payload']) ? $jobData['payload'] : [];

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

        $context = null;
        if (isset($jobData['context']) && (\is_array($jobData['context']) || \is_string($jobData['context']))) {
            $context = $jobData['context'];
        }
        $commandData = JobCommandDataExtractor::extract($payload);

        $rawStatus = (string) ($jobData['status'] ?? 'failed');
        $status = $rawStatus === 'completed' ? 'processed' : $rawStatus;


        $queuedAt = JobRuntimeHelper::parseJobTimestamp($payload['pushedAt'] ?? null);
        $reservedAt = JobRuntimeHelper::parseJobTimestamp($jobData['reserved_at'] ?? null);
        $processedAt = JobRuntimeHelper::parseJobTimestamp($jobData['completed_at'] ?? null);
        $failedAt = JobRuntimeHelper::parseJobTimestamp($jobData['failed_at'] ?? null);
        JobRuntimeHelper::normalizeStatusDates($status, $processedAt, $failedAt);

        $availableAt = isset($commandData['delay']) && isset($commandData['delay']['date']) ? JobRuntimeHelper::parseJobTimestamp($commandData['delay']['date']) : null;
        $runtimeSeconds = isset($jobData['runtime']) && \is_numeric($jobData['runtime'])
            ? (float) $jobData['runtime']
            : null;

        $runtime = JobRuntimeHelper::getFormattedRuntime(
            JobRuntimeHelper::getRuntimeSeconds($runtimeSeconds, $reservedAt, $processedAt, $failedAt)
        );

        $jobView = (object) [
            'uuid' => $jobData['id'],
            'name' => $jobData['name'],
            'queue' => $jobData['queue'],
            'status' => $status,
            'attempts' => (int) ($jobData['attempts'] ?? 0),
            'connection' => $jobData['connection'],
            'retries' => $retries,
            'tags' => $tags,
            'retried_by' => $retriedBy,
            'queued_at' => $queuedAt,
            'processed_at' => $processedAt,
            'failed_at' => $failedAt,
            'runtime' => $runtime,
            'available_at' => $availableAt,
            'exception' => $exception,
            'context' => $context,
            'command_data' => $commandData,
            'payload' => $payload,
            'service' => $service,
        ];

        return $jobView;
    }
}
