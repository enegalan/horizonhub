<?php

namespace App\Services\Horizon;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ServiceShowPageDataService
{
    /**
     * The Horizon job list service.
     */
    private HorizonJobListService $jobList;

    /**
     * The Horizon metrics service.
     */
    private HorizonMetricsService $metrics;

    /**
     * The constructor.
     */
    public function __construct(HorizonMetricsService $metrics, HorizonJobListService $jobList)
    {
        $this->metrics = $metrics;
        $this->jobList = $jobList;
    }

    /**
     * Build the service show page data.
     *
     * @param  Service  $service  The service.
     * @param  Request  $request  The request.
     * @param  HorizonApiProxyService  $horizonApi  The horizon API proxy service.
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
    public function build(Service $service, Request $request, HorizonApiProxyService $horizonApi): array
    {
        $search = (string) $request->query('search', '');

        $jobsPastMinute = $this->metrics->getJobsPastMinute($service);
        $jobsPastHour = $this->metrics->getJobsPastHour($service);
        $failedPastSevenDays = $this->metrics->getFailedPastSevenDays($service);

        $totalProcesses = null;
        $maxWaitTimeSeconds = null;
        $queueWithMaxRuntime = null;
        $queueWithMaxThroughput = null;
        $horizonStatus = null;
        $supervisorGroups = \collect();
        $supervisors = \collect();
        $workloadQueues = \collect();

        $statsResponse = $horizonApi->getStats($service);
        $statsData = $statsResponse['data'] ?? null;

        if (($statsResponse['success'] ?? false) && \is_array($statsData)) {
            if (isset($statsData['status']) && ! empty((string) $statsData['status'])) {
                $horizonStatus = (string) $statsData['status'];
            }
            if (isset($statsData['processes']) && \is_numeric($statsData['processes'])) {
                $totalProcesses = (int) $statsData['processes'];
            }

            if (isset($statsData['wait']) && \is_array($statsData['wait'])) {
                foreach ($statsData['wait'] as $waitValue) {
                    if (! \is_numeric($waitValue)) {
                        continue;
                    }

                    $seconds = (float) $waitValue;

                    if ($seconds <= 0.0) {
                        continue;
                    }

                    if ($maxWaitTimeSeconds === null || $seconds > $maxWaitTimeSeconds) {
                        $maxWaitTimeSeconds = $seconds;
                    }
                }
            }

            if (isset($statsData['queueWithMaxRuntime']) && (string) $statsData['queueWithMaxRuntime'] !== '') {
                $queueWithMaxRuntime = (string) $statsData['queueWithMaxRuntime'];
            }

            if (isset($statsData['queueWithMaxThroughput']) && (string) $statsData['queueWithMaxThroughput'] !== '') {
                $queueWithMaxThroughput = (string) $statsData['queueWithMaxThroughput'];
            }
        }

        $mastersResponse = $horizonApi->getMasters($service);
        $mastersData = $mastersResponse['data'] ?? null;

        if (($mastersResponse['success'] ?? false) && \is_array($mastersData)) {
            foreach ($mastersData as $master) {
                if (! \is_array($master)) {
                    continue;
                }

                $supervisorsData = $master['supervisors'] ?? null;
                if (! \is_array($supervisorsData)) {
                    continue;
                }

                foreach ($supervisorsData as $supervisor) {
                    if (! \is_array($supervisor)) {
                        continue;
                    }

                    $name = isset($supervisor['name']) ? (string) $supervisor['name'] : '';
                    if ($name === '') {
                        continue;
                    }

                    $groupParts = \explode(':', $name, 2);
                    $groupName = $groupParts[0] !== '' ? $groupParts[0] : $name;

                    $options = isset($supervisor['options']) && \is_array($supervisor['options']) ? $supervisor['options'] : [];

                    $connection = '';
                    if (isset($options['connection']) && (string) $options['connection'] !== '') {
                        $connection = (string) $options['connection'];
                    } elseif (isset($supervisor['connection']) && (string) $supervisor['connection'] !== '') {
                        $connection = (string) $supervisor['connection'];
                    }

                    $queues = '';
                    if (isset($options['queue'])) {
                        $queuesRaw = $options['queue'];
                        $queues = \is_array($queuesRaw) ? \implode(', ', \array_map('strval', $queuesRaw)) : (string) $queuesRaw;
                    }

                    $processes = null;
                    if (isset($supervisor['processes']) && \is_array($supervisor['processes'])) {
                        $sum = 0;
                        foreach ($supervisor['processes'] as $value) {
                            if (\is_numeric($value)) {
                                $sum += (int) $value;
                            }
                        }
                        $processes = $sum;
                    }

                    $balancing = '';
                    if (isset($options['balance']) && (string) $options['balance'] !== '') {
                        $balancing = (string) $options['balance'];
                    }

                    $apiStatus = isset($supervisor['status']) ? (string) $supervisor['status'] : '';

                    $supervisorObj = (object) [
                        'name' => $name,
                        'connection' => $connection,
                        'queues' => $queues,
                        'processes' => $processes,
                        'balancing' => $balancing,
                        'status' => $apiStatus,
                    ];

                    if (! $supervisorGroups->has($groupName)) {
                        $supervisorGroups[$groupName] = \collect();
                    }

                    $supervisorGroups[$groupName]->push($supervisorObj);
                    $supervisors->push($supervisorObj);
                }
            }
            $supervisorGroups = $supervisorGroups->sortKeys();
        }

        $workloadRows = $this->metrics->getWorkloadForService($service);
        foreach ($workloadRows as $row) {
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
}
