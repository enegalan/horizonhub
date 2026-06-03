<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\Service;
use App\Support\Horizon\JobCommandDataExtractor;
use App\Support\Horizon\JobRuntimeHelper;
use Illuminate\Http\Request;

trait BuildsJobStreams
{
    /**
     * Build the job show streams.
     *
     * @param string $routeJobUuid The job UUID.
     */
    protected function buildJobShow(string $routeJobUuid): ?string
    {
        $resolved = $this->jobServiceResolver->resolve($routeJobUuid);

        if ($resolved === null) {
            return null;
        }

        $jobView = $this->private__buildJobShowViewData($resolved['service'], $resolved['data']);

        $exception = ($jobView->exception ?? null) ? html_entity_decode((string) $jobView->exception, ENT_QUOTES | ENT_HTML401, 'UTF-8') : null;
        $exceptionTrace = $exception ? (\preg_split("/\r\n|\n|\r/", $exception) ?: []) : [];
        $retryHistory = \is_array($jobView->retried_by ?? null) ? $jobView->retried_by : [];
        $payload = $jobView->payload ? json_encode($jobView->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $context = ($jobView->context ?? null) ? json_encode($jobView->context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $commandData = ($jobView->command_data ?? null) ? json_encode($jobView->command_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $vars = [
            'job' => $jobView,
            'exception' => $exceptionTrace,
            'retryHistory' => $retryHistory,
            'payload' => $payload,
            'context' => $context,
            'commandData' => $commandData,
        ];

        return $this->buildStreams([
            ['update', 'horizon-job-detail-actions-stream', \view('horizon.jobs.partials.show.actions', $vars)->render(), null],
            ['update', 'horizon-job-detail-meta', \view('horizon.jobs.partials.show.meta', $vars)->render(), null],
            ['update', 'horizon-job-detail-exception', \view('horizon.jobs.partials.show.exception', $vars)->render(), null],
            ['update', 'horizon-job-detail-context', \view('horizon.jobs.partials.show.context', $vars)->render(), null],
            ['update', 'horizon-job-detail-retry-history', \view('horizon.jobs.partials.show.retry-history', $vars)->render(), null],
            ['update', 'horizon-job-detail-data', \view('horizon.jobs.partials.show.data', $vars)->render(), null],
            ['update', 'horizon-job-detail-payload', \view('horizon.jobs.partials.show.payload', $vars)->render(), null],
        ]);
    }

    /**
     * Build the jobs index streams.
     *
     * @param string $query The query.
     */
    protected function buildJobsIndex(string $query): string
    {
        $url = \route('horizon.jobs.index', [], true);
        $queryParams = [];

        \parse_str($query, $queryParams);

        $index = $this->jobList->buildAggregatedJobsIndexFromRequest(Request::create($url, 'GET', $queryParams));

        return $this->streamsForJobListSections(
            [
                'processing' => $index['processing'],
                'processed' => $index['processed'],
                'failed' => $index['failed'],
            ],
            'horizon-job-list',
            true,
            null,
        );
    }

    /**
     * Build the job show view data.
     *
     * @param Service $service The service.
     * @param array<string, mixed> $jobData The job data.
     */
    private function private__buildJobShowViewData(Service $service, array $jobData): object
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
        $retries = ! empty($retriedBy) ? \count($retriedBy) : null;

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
            JobRuntimeHelper::getRuntimeSeconds($runtimeSeconds, $reservedAt, $processedAt, $failedAt),
        );

        return (object) [
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
    }
}
