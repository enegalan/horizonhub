<?php

namespace App\Http\Controllers\Stream\Concerns;

trait BuildsQueueStreams
{
    private function private__buildQueuesStreams(string $query): string
    {
        $serviceFilterIds = $this->serviceFilter->resolveFromQuery($query);
        $queues = $this->metrics->buildQueuesCollectionForServiceFilter($serviceFilterIds);

        $totalJobs = (int) $queues->sum(static fn ($r): int => (int) ($r->job_count ?? 0));

        $statsHtml = \view('horizon.queues.partials.index.stats', [
            'queueCount' => $queues->count(),
            'totalJobs' => $totalJobs,
        ])->render();

        $tbodyHtml = \view('horizon.queues.partials.index.tbody', ['queues' => $queues])->render();

        return $this->buildStreams([
            ['update', 'turbo-horizon-queue-stats', $statsHtml, 'morph'],
            ['update', 'turbo-tbody-horizon-queue-list', $tbodyHtml, 'morph'],
        ]);
    }
}
