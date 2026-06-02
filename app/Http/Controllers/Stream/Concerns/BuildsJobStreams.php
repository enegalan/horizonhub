<?php

namespace App\Http\Controllers\Stream\Concerns;

use Illuminate\Http\Request;

trait BuildsJobStreams
{
    private function private__buildJobShowStreams(string $routeJobUuid): ?string
    {
        $resolved = $this->jobServiceResolver->resolve($routeJobUuid);

        if ($resolved === null) {
            return null;
        }

        $jobView = $this->jobDetail->buildShowViewData($resolved['service'], $resolved['data']);

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

    private function private__buildJobsIndexStreams(string $query): string
    {
        $url = \route('horizon.jobs.index', [], true);

        if ($query !== '') {
            $url .= "?$query";
        }
        $pageRequest = Request::create($url, 'GET');

        $index = $this->jobList->buildAggregatedJobsIndexFromRequest($pageRequest);

        return $this->private__streamsForJobListSections(
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
}
