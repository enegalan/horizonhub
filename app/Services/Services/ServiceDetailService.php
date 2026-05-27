<?php

namespace App\Services\Services;

use App\Models\Service;
use App\Services\Horizon\HorizonClientService;
use App\Services\Jobs\JobListService;
use App\Services\Metrics\MetricsDataService;
use App\Support\Horizon\HorizonMastersReader;
use App\Support\Horizon\HorizonStatsReader;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ServiceDetailService
{
    /**
     * The Horizon job list service.
     */
    private JobListService $jobList;

    /**
     * The metrics data service.
     */
    private MetricsDataService $metrics;

    /**
     * The constructor.
     *
     * @param MetricsDataService $metrics The metrics data service.
     * @param JobListService $jobList The job list service.
     */
    public function __construct(MetricsDataService $metrics, JobListService $jobList)
    {
        $this->metrics = $metrics;
        $this->jobList = $jobList;
    }

    /**
     * Build the service detail page data.
     *
     * @param Service $service The service.
     * @param Request $request The request.
     * @param HorizonClientService $horizonApi The horizon API client.
     *
     * @return array{
     *     jobsPastMinute: mixed,
     *     jobsPastHour: mixed,
     *     failedPastSevenDays: mixed,
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
    public function build(Service $service, Request $request, HorizonClientService $horizonApi): array
    {
        if (! $service->enabled) {
            return $this->private__buildDisabledPageData($service, $request);
        }

        $search = (string) $request->query('search', '');

        $jobsPastMinute = $this->metrics->getJobsPastMinute($service);
        $jobsPastHour = $this->metrics->getJobsPastHour($service);
        $failedPastSevenDays = $this->metrics->getFailedPastSevenDays($service);

        $statsData = HorizonStatsReader::dataFromResponse($horizonApi->getStats($service));
        $horizonStatus = HorizonStatsReader::status($statsData);
        $totalProcesses = HorizonStatsReader::processes($statsData);
        $maxWaitTimeSeconds = HorizonStatsReader::maxWaitTimeSeconds($statsData);
        $queueWithMaxRuntime = HorizonStatsReader::queueWithMaxRuntime($statsData);
        $queueWithMaxThroughput = HorizonStatsReader::queueWithMaxThroughput($statsData);

        $supervisorGroups = \collect();
        $supervisors = \collect();
        $mastersResponse = $horizonApi->getMasters($service);
        $mastersData = $mastersResponse['data'] ?? null;

        if ($mastersResponse['success'] && \is_array($mastersData)) {
            foreach (HorizonMastersReader::supervisorsFromMastersPayload($mastersData) as $supervisor) {
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
                    $supervisorGroups[$groupName] = \collect();
                }

                $supervisorGroups[$groupName]->push($supervisorObj);
                $supervisors->push($supervisorObj);
            }

            $supervisorGroups = $supervisorGroups->sortKeys();
        }

        $workloadQueues = \collect();

        foreach ($this->metrics->getWorkloadForService($service) as $row) {
            $workloadQueues->push((object) [
                'queue' => $row['queue'],
                'jobs' => $row['jobs'],
                'processes' => $row['processes'],
                'wait' => $row['wait'],
            ]);
        }

        $workloadQueues = $workloadQueues->values();

        $perPage = (int) config('horizonhub.jobs_per_page');

        $pageProcessing = \max(1, (int) $request->query('page_processing', 1));
        $pageProcessed = \max(1, (int) $request->query('page_processed', 1));
        $pageFailed = \max(1, (int) $request->query('page_failed', 1));

        $paginators = $this->jobList->buildServiceStatusPaginators(
            $service,
            $search,
            $pageProcessing,
            $pageProcessed,
            $pageFailed,
            $perPage,
            $request->url(),
            $request->query(),
        );

        return [
            'jobsPastMinute' => $jobsPastMinute,
            'jobsPastHour' => $jobsPastHour,
            'failedPastSevenDays' => $failedPastSevenDays,
            'totalProcesses' => $totalProcesses,
            'maxWaitTimeSeconds' => $maxWaitTimeSeconds,
            'queueWithMaxRuntime' => $queueWithMaxRuntime,
            'queueWithMaxThroughput' => $queueWithMaxThroughput,
            'horizonStatus' => $horizonStatus,
            'supervisorGroups' => $supervisorGroups,
            'supervisors' => $supervisors,
            'workloadQueues' => $workloadQueues,
            'jobsProcessing' => $paginators['processing'],
            'jobsProcessed' => $paginators['processed'],
            'jobsFailed' => $paginators['failed'],
            'filters' => [
                'search' => $search,
            ],
        ];
    }

    /**
     * Build empty dashboard data when the service is disabled.
     *
     * @return array{
     *     jobsPastMinute: int,
     *     jobsPastHour: int,
     *     failedPastSevenDays: int,
     *     totalProcesses: null,
     *     maxWaitTimeSeconds: null,
     *     queueWithMaxRuntime: null,
     *     queueWithMaxThroughput: null,
     *     horizonStatus: null,
     *     supervisorGroups: Collection,
     *     supervisors: Collection,
     *     workloadQueues: Collection,
     *     jobsProcessing: LengthAwarePaginator,
     *     jobsProcessed: LengthAwarePaginator,
     *     jobsFailed: LengthAwarePaginator,
     *     filters: array{search: string}
     * }
     */
    private function private__buildDisabledPageData(Service $service, Request $request): array
    {
        $search = (string) $request->query('search', '');
        $perPage = (int) config('horizonhub.jobs_per_page');
        $path = $request->url();
        $query = $request->query();
        $pageProcessing = \max(1, (int) $request->query('page_processing', 1));
        $pageProcessed = \max(1, (int) $request->query('page_processed', 1));
        $pageFailed = \max(1, (int) $request->query('page_failed', 1));

        $emptyPaginator = static fn (int $page, string $pageName): LengthAwarePaginator => new LengthAwarePaginator(
            [],
            0,
            $perPage,
            $page,
            ['path' => $path, 'pageName' => $pageName, 'query' => $query],
        );

        return [
            'jobsPastMinute' => 0,
            'jobsPastHour' => 0,
            'failedPastSevenDays' => 0,
            'totalProcesses' => null,
            'maxWaitTimeSeconds' => null,
            'queueWithMaxRuntime' => null,
            'queueWithMaxThroughput' => null,
            'horizonStatus' => null,
            'supervisorGroups' => \collect(),
            'supervisors' => \collect(),
            'workloadQueues' => \collect(),
            'jobsProcessing' => $emptyPaginator($pageProcessing, 'page_processing'),
            'jobsProcessed' => $emptyPaginator($pageProcessed, 'page_processed'),
            'jobsFailed' => $emptyPaginator($pageFailed, 'page_failed'),
            'filters' => [
                'search' => $search,
            ],
        ];
    }
}
