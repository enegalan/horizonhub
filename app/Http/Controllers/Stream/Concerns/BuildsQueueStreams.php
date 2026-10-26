<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Services\Jobs\JobListService;
use App\Services\Services\ServiceFilterService;
use App\Support\Queues\QueueNameNormalizer;

trait BuildsQueueStreams
{
    /**
     * Build the queues streams.
     *
     * @param string $query The query.
     */
    protected function buildQueues(string $query): string
    {
        $serviceFilterIds = ServiceFilterService::resolveServiceIdsFromQuery($query);
        $overview = $this->queueOverview->build($serviceFilterIds);
        $queues = $this->queueOverview->queueRowsCollection($overview['rows']);

        $statsHtml = \view('pages.queues.partials.index.stats', [
            'queueCount' => $overview['totals']['queueCount'],
            'totalJobs' => $overview['totals']['totalJobs'],
        ])->render();

        $tbodyHtml = \view('pages.queues.partials.index.tbody', ['queues' => $queues])->render();

        return $this->buildStreams([
            ['update', 'horizon-queue-stats', $statsHtml, 'morph'],
            ['update', 'tbody-horizon-queue-list', $tbodyHtml, 'morph'],
        ]);
    }

    /**
     * Build the aggregated queue detail streams.
     *
     * @param string $queue The route queue name.
     * @param string $query The query string.
     */
    protected function buildQueueShow(string $queue, string $query): string
    {
        $queue = QueueNameNormalizer::normalize($queue);
        $serviceFilterIds = ServiceFilterService::resolveServiceIdsFromQuery($query);
        $overview = $this->queueOverview->forQueue($serviceFilterIds, $queue);
        $search = ServiceFilterService::searchFromQuery($query);
        $queryParams = [];
        \parse_str($query, $queryParams);

        $paginators = JobListService::buildAggregatedStatusPaginators(
            $overview['services'],
            $search,
            \max(1, (int) ($queryParams['page_processing'] ?? 1)),
            \max(1, (int) ($queryParams['page_processed'] ?? 1)),
            \max(1, (int) ($queryParams['page_failed'] ?? 1)),
            (int) config('horizonhub.jobs_per_page'),
            \route('horizon.queues.show', ['queue' => $queue]),
            $queryParams,
            $queue,
        );

        $statsHtml = \view('pages.queues.partials.show.stats', [
            'failedJobs' => $paginators['failed']->total(),
            'failedMayBeTruncated' => $paginators['failed']->resultsMayBeTruncated(),
            'pendingJobs' => $overview['totals']['pendingJobs'],
            'processedJobs' => $paginators['processed']->total(),
            'processedMayBeTruncated' => $paginators['processed']->resultsMayBeTruncated(),
            'processes' => $overview['totals']['processes'],
            'silencedJobs' => $overview['silencedJobs'],
        ])->render();

        $servicesHtml = \view('pages.queues.partials.show.services-tbody', [
            'rows' => $overview['rows'],
        ])->render();

        $metricsHtml = \view('pages.queues.partials.show.metrics', [
            'metrics' => $overview['metrics'],
        ])->render();

        $streams = $this->buildStreams([
            ['update', 'queue-show-stats', $statsHtml, 'morph'],
            ['update', 'tbody-horizon-queue-services', $servicesHtml, 'morph'],
            ['update', 'queue-show-metrics', $metricsHtml, 'morph'],
        ]);

        return $streams . $this->streamsForJobListSections([
            'processing' => $paginators['processing'],
            'processed' => $paginators['processed'],
            'failed' => $paginators['failed'],
        ], 'horizon-queue-show-jobs', true, null);
    }
}
