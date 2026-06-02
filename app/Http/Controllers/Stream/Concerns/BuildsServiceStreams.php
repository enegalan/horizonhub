<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\Service;
use Illuminate\Http\Request;

trait BuildsServiceStreams
{
    private function buildServiceShow(Service $service, string $query): string
    {
        $url = \route('horizon.services.show', ['service' => $service->id], true);
        $queryParams = [];

        \parse_str($query, $queryParams);
        $pageRequest = Request::create($url, 'GET', $queryParams);

        $d = $this->serviceDetail->build($service, $pageRequest, $this->horizonApi);

        $workloadCount = $d['workloadQueues']->count();

        $streams = [];
        $streams[] = $this->buildStreams([
            ['update', 'service-show-stats-row-1', \view('horizon.services.partials.show.stats-row-1', $d)->render(), null],
            ['update', 'service-show-stats-row-2', \view('horizon.services.partials.show.stats-row-2', $d)->render(), null],
            ['update', 'service-show-supervisors-panel', \view('horizon.services.partials.show.supervisors-panel', $d)->render(), null],
            ['update', 'service-show-workload-count', e($workloadCount > 0 ? $workloadCount . ' queue(s)' : ''), null],
            ['update', 'service-show-workload-body', \view('horizon.services.partials.show.workload-tbody', ['workloadQueues' => $d['workloadQueues']])->render(), 'morph'],
            ['update', 'service-show-supervisor-groups', \view('horizon.services.partials.show.supervisor-groups', $d)->render(), 'morph'],
        ]);

        $streams[] = $this->streamsForJobListSections(
            [
                'processing' => $d['jobsProcessing'],
                'processed' => $d['jobsProcessed'],
                'failed' => $d['jobsFailed'],
            ],
            'horizon-service-dashboard-jobs',
            false,
            $service,
        );

        return \implode("\n", $streams);
    }

    private function buildServices(string $query): string
    {
        $serviceIds = $this->serviceFilter->resolveFromQuery($query);
        $servicesQuery = Service::query()->orderBy('name');

        if (! empty($serviceIds)) {
            $servicesQuery->whereIn('id', $serviceIds);
        }

        $services = $servicesQuery->get();
        $this->serviceStats->attachHorizonStats($services, $this->horizonApi);
        $serviceStats = $this->serviceStats->buildListSummaryCounts($services);

        return $this->buildStreams([
            ['update', 'turbo-horizon-service-stats', \view('horizon.services.partials.index.stats', ['serviceStats' => $serviceStats])->render(), 'morph'],
            ['update', 'turbo-tbody-horizon-service-list', \view('horizon.services.partials.index.tbody', ['services' => $services])->render(), 'morph'],
        ]);
    }
}
