<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Enums\JobStatus;
use App\Models\Service;
use App\Services\Jobs\JobListService;
use App\Services\Jobs\JobServiceResolverService;
use App\Support\ArrayFormatter;
use App\Support\Jobs\JobCommandDataExtractor;
use App\Support\Jobs\JobRuntime;
use App\Support\StringFormatter;
use Carbon\Carbon;
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
        $resolved = JobServiceResolverService::resolve($routeJobUuid);

        if ($resolved === null) {
            return null;
        }

        $jobView = $this->private__buildJobShowViewData($resolved['service'], $resolved['data']);

        return $this->buildStreams([
            ['update', 'horizon-job-detail-actions-stream', \view('horizon.jobs.partials.show.actions', ['job' => $jobView])->render(), null],
            ['update', 'horizon-job-detail-meta', \view('horizon.jobs.partials.show.meta', ['job' => $jobView])->render(), null],
            ['update', 'horizon-job-detail-exception', \view('horizon.jobs.partials.show.exception', ['job' => $jobView])->render(), null],
            ['update', 'horizon-job-detail-context', \view('horizon.jobs.partials.show.context', ['job' => $jobView])->render(), null],
            ['update', 'horizon-job-detail-retry-history', \view('horizon.jobs.partials.show.retry-history', ['job' => $jobView])->render(), null],
            ['update', 'horizon-job-detail-data', \view('horizon.jobs.partials.show.data', ['job' => $jobView])->render(), null],
            ['update', 'horizon-job-detail-payload', \view('horizon.jobs.partials.show.payload', ['job' => $jobView])->render(), null],
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

        $index = JobListService::buildAggregatedJobsIndexFromRequest(Request::create($url, 'GET', $queryParams));

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
        $tags = [];

        if (\is_array($jobData['tags'] ?? null)) {
            $tags = \array_values(\array_filter($jobData['tags'], static function ($tag) {
                return StringFormatter::trim($tag) !== '';
            }));
        }

        $exception = ($jobData['exception'] ?? '') !== '' ? html_entity_decode($jobData['exception'] ?? '', ENT_QUOTES | ENT_HTML401, 'UTF-8') : null;
        $exceptionTrace = $exception ? (\preg_split("/\r\n|\n|\r/", $exception) ?: []) : [];

        $retriedBy = [];

        foreach (ArrayFormatter::orEmpty($jobData['retried_by'] ?? null) as $retriedJob) {
            if (! \is_array($retriedJob) || StringFormatter::trim($retriedJob['id'] ?? '') === '') {
                continue;
            }

            $retriedStatus = StringFormatter::trim($retriedJob['status'] ?? '');
            $retriedStatus = $retriedStatus !== '' ? JobStatus::normalizeStatus($retriedStatus)->value : null;

            $retriedAt = \is_numeric($retriedJob['retried_at'] ?? null) ? Carbon::createFromTimestamp((int) $retriedJob['retried_at']) : null;

            $retriedBy[] = [
                'id' => $retriedJob['id'],
                'status' => $retriedStatus,
                'retried_at' => $retriedAt,
            ];
        }
        $retries = ! empty($retriedBy) ? \count($retriedBy) : null;

        $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

        $context = $jobData['context'] ?? null;
        $context = \is_array($context) || \is_string($context) ? $context : null;
        $context = $context ? json_encode($context, $jsonFlags) : null;

        $payload = ArrayFormatter::orEmpty($jobData['payload'] ?? null);
        $commandData = JobCommandDataExtractor::extract($payload);
        $commandData = $commandData ? json_encode($commandData, $jsonFlags) : null;

        $rawStatus = (string) ($jobData['status'] ?? 'failed');
        $status = JobStatus::normalizeStatus($rawStatus)->value;
        $timing = JobRuntime::resolveJobTimingFields($jobData, $payload, $status);

        $encodedPayload = $payload ? json_encode($payload, $jsonFlags) : null;

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
            'queued_at' => $timing['queued_at'],
            'processed_at' => $timing['processed_at'],
            'failed_at' => $timing['failed_at'],
            'runtime' => $timing['runtime'],
            'available_at' => $timing['available_at'],
            'exception' => $exceptionTrace,
            'context' => $context,
            'command_data' => $commandData,
            'payload' => $encodedPayload,
            'service' => $service,
        ];
    }
}
