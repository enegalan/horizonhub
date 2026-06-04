<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\Service;
use App\Services\Horizon\Contracts\HorizonClientApi;
use App\Support\Horizon\HorizonMastersReader;
use App\Support\Horizon\HorizonStatsReader;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

trait BuildsServiceStreams
{
    /**
     * Build the services index streams.
     *
     * @param string $query The query.
     */
    protected function buildServices(string $query): string
    {
        $serviceIds = $this->serviceFilter->resolveFromQuery($query);
        $services = $this->store->servicesOrdered(! empty($serviceIds) ? $serviceIds : null);
        $this->serviceStats->attachHorizonStats($services, $this->horizonApi);

        $enabledServices = $services->where('enabled', true);

        $serviceStats = [
            'total' => $services->count(),
            'online' => $enabledServices->where('status', 'online')->count(),
            'offline' => $enabledServices->whereIn('status', ['offline', 'stand_by'])->count(),
        ];

        return $this->buildStreams([
            ['update', 'turbo-horizon-service-stats', \view('horizon.services.partials.index.stats', ['serviceStats' => $serviceStats])->render(), 'morph'],
            ['update', 'turbo-tbody-horizon-service-list', \view('horizon.services.partials.index.tbody', ['services' => $services])->render(), 'morph'],
        ]);
    }

    /**
     * Build the service show streams.
     *
     * @param Service $service The service.
     * @param string $query The query.
     */
    protected function buildServiceShow(Service $service, string $query): string
    {
        $url = \route('horizon.services.show', ['service' => $service->id], true);
        $queryParams = [];

        \parse_str($query, $queryParams);

        $d = $this->private__buildServiceShowData($service, Request::create($url, 'GET', $queryParams), $this->horizonApi);

        $workloadCount = $d['workloadQueues']->count();

        $streams = [];
        $streams[] = $this->buildStreams([
            ['update', 'service-show-stats-row-1', \view('horizon.services.partials.show.stats-row-1', $d)->render(), null],
            ['update', 'service-show-stats-row-2', \view('horizon.services.partials.show.stats-row-2', $d)->render(), null],
            ['update', 'service-show-supervisors-panel', \view('horizon.services.partials.show.supervisors-panel', $d)->render(), null],
            ['update', 'service-show-workload-count', e($workloadCount > 0 ? "$workloadCount queue(s)" : ''), null],
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

    /**
     * Build the service detail page data.
     *
     * @param Service $service The service.
     * @param Request $request The request.
     * @param HorizonClientApi $horizonApi The horizon API client.
     *
     * @return array{
     *     jobsPastMinute: int|mixed,
     *     jobsPastHour: int|mixed,
     *     failedPastSevenDays: int|mixed,
     *     totalProcesses: int|null,
     *     maxWaitTimeSeconds: float|null,
     *     queueWithMaxRuntime: string|null,
     *     queueWithMaxThroughput: string|null,
     *     horizonStatus: string|null,
     *     supervisorGroups: Collection,
     *     supervisors: Collection,
     *     workloadQueues: Collection,
     *     jobsProcessing: LengthAwarePaginator,
     *     jobsProcessed: LengthAwarePaginator,
     *     jobsFailed: LengthAwarePaginator,
     *     filters: array{search: string}
     * }
     */
    private function private__buildServiceShowData(Service $service, Request $request, HorizonClientApi $horizonApi): array
    {
        $search = (string) $request->query('search', '');

        $data = [
            'jobsPastMinute' => 0,
            'jobsPastHour' => 0,
            'failedPastSevenDays' => 0,
            'totalProcesses' => null,
            'maxWaitTimeSeconds' => null,
            'queueWithMaxRuntime' => null,
            'queueWithMaxThroughput' => null,
            'horizonStatus' => null,
            'supervisorGroups' => collect(),
            'supervisors' => collect(),
            'workloadQueues' => collect(),
        ];

        if ($service->enabled) {
            $data['jobsPastMinute'] = $this->metrics->getJobsPastMinute($service);
            $data['jobsPastHour'] = $this->metrics->getJobsPastHour($service);
            $data['failedPastSevenDays'] = $this->metrics->getFailedPastSevenDays($service);

            $statsData = HorizonStatsReader::dataFromResponse(
                $horizonApi->getStats($service),
            );

            $data['horizonStatus'] = HorizonStatsReader::status($statsData);
            $data['totalProcesses'] = HorizonStatsReader::processes($statsData);
            $data['maxWaitTimeSeconds'] = HorizonStatsReader::maxWaitTimeSeconds($statsData);
            $data['queueWithMaxRuntime'] = HorizonStatsReader::queueWithMaxRuntime($statsData);
            $data['queueWithMaxThroughput'] = HorizonStatsReader::queueWithMaxThroughput($statsData);

            $supervisorGroups = collect();
            $supervisors = collect();

            $mastersResponse = $horizonApi->getMasters($service);
            $mastersData = $mastersResponse['data'] ?? null;

            if ($mastersResponse['success'] && \is_array($mastersData)) {
                foreach (
                    HorizonMastersReader::supervisorsFromMastersPayload($mastersData) as $supervisor
                ) {
                    $supervisorObj = (object) [
                        'name' => $supervisor['name'],
                        'connection' => $supervisor['connection'],
                        'queues' => $supervisor['queues'],
                        'processes' => $supervisor['processes'],
                        'balancing' => $supervisor['balancing'],
                        'status' => $supervisor['apiStatus'],
                    ];

                    $groupName = $supervisor['groupName'];

                    if (! $supervisorGroups->has($groupName)) {
                        $supervisorGroups[$groupName] = collect();
                    }

                    $supervisorGroups[$groupName]->push($supervisorObj);
                    $supervisors->push($supervisorObj);
                }

                $supervisorGroups = $supervisorGroups->sortKeys();
            }

            $workloadQueues = collect();

            foreach ($this->metrics->getWorkloadForService($service) as $row) {
                $workloadQueues->push((object) [
                    'queue' => $row['queue'],
                    'jobs' => $row['jobs'],
                    'processes' => $row['processes'],
                    'wait' => $row['wait'],
                ]);
            }

            $data['supervisorGroups'] = $supervisorGroups;
            $data['supervisors'] = $supervisors;
            $data['workloadQueues'] = $workloadQueues->values();

            $pageProcessing = max(1, (int) $request->query('page_processing', 1));
            $pageProcessed = max(1, (int) $request->query('page_processed', 1));
            $pageFailed = max(1, (int) $request->query('page_failed', 1));

            $paginators = $this->jobList->buildServiceStatusPaginators(
                $service,
                $search,
                $pageProcessing,
                $pageProcessed,
                $pageFailed,
                (int) config('horizonhub.jobs_per_page'),
                $request->url(),
                $request->query(),
            );

            $data['jobsProcessing'] = $paginators['processing'];
            $data['jobsProcessed'] = $paginators['processed'];
            $data['jobsFailed'] = $paginators['failed'];
        } else {
            $path = $request->url();
            $query = $request->query();

            $emptyPaginator = static fn (int $page, string $pageName): LengthAwarePaginator => new LengthAwarePaginator(
                [],
                0,
                (int) config('horizonhub.jobs_per_page'),
                $page,
                [
                    'path' => $path,
                    'pageName' => $pageName,
                    'query' => $query,
                ],
            );

            $data['jobsProcessing'] = $emptyPaginator(
                max(1, (int) $request->query('page_processing', 1)),
                'page_processing',
            );

            $data['jobsProcessed'] = $emptyPaginator(
                max(1, (int) $request->query('page_processed', 1)),
                'page_processed',
            );

            $data['jobsFailed'] = $emptyPaginator(
                max(1, (int) $request->query('page_failed', 1)),
                'page_failed',
            );
        }

        $data['filters'] = [
            'search' => $search,
        ];

        return $data;
    }
}
