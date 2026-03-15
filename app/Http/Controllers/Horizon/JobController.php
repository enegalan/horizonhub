<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\HorizonJob;
use App\Models\Service;
use App\Services\HorizonApiProxyService;
use App\Services\HorizonSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class JobController extends Controller {

    /**
     * The Horizon sync service.
     *
     * @var HorizonSyncService
     */
    private HorizonSyncService $horizonSync;

    /**
     * The Horizon API proxy service.
     *
     * @var HorizonApiProxyService
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * Construct the job controller.
     *
     * @param HorizonSyncService $horizonSync
     * @param HorizonApiProxyService $horizonApi
     */
    public function __construct(HorizonSyncService $horizonSync, HorizonApiProxyService $horizonApi) {
        $this->horizonSync = $horizonSync;
        $this->horizonApi = $horizonApi;
    }

    /**
     * Show the jobs index.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View {
        $serviceFilter = (string) $request->query('serviceFilter', '');
        $statusFilter = (string) $request->query('statusFilter', '');
        $search = (string) $request->query('search', '');

        $this->horizonSync->syncRecentJobs($serviceFilter !== '' ? (int) $serviceFilter : null);

        $perPage = (int) \config('horizonhub.jobs_per_page');
        $page = (int) $request->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $servicesQuery = Service::query()->whereNotNull('base_url');
        if ($serviceFilter !== '') {
            $servicesQuery->where('id', (int) $serviceFilter);
        }

        /** @var \Illuminate\Support\Collection<int, Service> $servicesWithApi */
        $servicesWithApi = $servicesQuery->get();

        $jobEntries = [];

        foreach ($servicesWithApi as $service) {
            $endpoints = [];

            if ($statusFilter === 'failed' || $statusFilter === '') {
                $endpoints[] = 'failed';
            }
            if ($statusFilter === 'processed' || $statusFilter === '') {
                $endpoints[] = 'processed';
            }
            if ($statusFilter === 'processing' || $statusFilter === '') {
                $endpoints[] = 'processing';
            }

            foreach ($endpoints as $endpoint) {
                $apiQuery = [
                    'starting_at' => 0,
                    'limit' => $perPage,
                ];

                if ($endpoint === 'failed') {
                    $apiResponse = $this->horizonApi->getFailedJobs($service, $apiQuery);
                } elseif ($endpoint === 'processed') {
                    $apiResponse = $this->horizonApi->getCompletedJobs($service, $apiQuery);
                } else {
                    $apiResponse = $this->horizonApi->getPendingJobs($service, $apiQuery);
                }

                $apiData = $apiResponse['data'] ?? null;

                if (! ($apiResponse['success'] ?? false) || ! \is_array($apiData)) {
                    continue;
                }

                foreach ($apiData['jobs'] ?? [] as $job) {
                    $jobId = (string) $job['id'];

                    if ( empty($jobId) ) {
                        continue;
                    }

                    $jobEntries[] = [
                        'service_id' => $service->id,
                        'job_uuid' => $jobId,
                    ];
                }
            }
        }

        if (\count($jobEntries) === 0) {
            $jobsCollection = \collect();
        } else {
            $uuids = [];
            foreach ($jobEntries as $entry) {
                $uuids[] = $entry['job_uuid'];
            }
            $uuids = \array_values(\array_unique($uuids));

            $jobsCollection = HorizonJob::with('service')
                ->whereIn('job_uuid', $uuids)
                ->when($serviceFilter !== '', static function ($q) use ($serviceFilter): void {
                    $q->where('service_id', (int) $serviceFilter);
                })
                ->when($statusFilter !== '', static function ($q) use ($statusFilter): void {
                    $q->where('status', $statusFilter);
                })
                ->when($search !== '', static function ($q) use ($search): void {
                    $q->where(function ($inner) use ($search): void {
                        $inner->where('queue', 'like', "%$search%")
                            ->orWhere('name', 'like', "%$search%")
                            ->orWhere('job_uuid', 'like', "%$search%");
                    });
                })
                ->get()
                ->sortByDesc('created_at')
                ->values();
        }

        $total = $jobsCollection->count();
        $offset = ($page - 1) * $perPage;
        $pageItems = $perPage > 0 ? $jobsCollection->slice($offset, $perPage)->values() : $jobsCollection;

        $jobs = new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $services = Service::orderBy('name')->get();

        return \view('horizon.jobs.index', [
            'jobs' => $jobs,
            'services' => $services,
            'filters' => [
                'serviceFilter' => $serviceFilter,
                'statusFilter' => $statusFilter,
                'search' => $search,
            ],
            'header' => 'Horizon Hub – Jobs',
        ]);
    }

    /**
     * Show a single job detail.
     *
     * @param int $job
     * @return View
     */
    public function show(int $job): View {
        $jobModel = HorizonJob::with('service')->find($job);

        if (! $jobModel || ! $jobModel->service || ! $jobModel->service->base_url) {
            \abort(404);
        }

        $service = $jobModel->service;
        $jobUuid = $jobModel->job_uuid;

        $horizonJob = null;
        $exception = null;

        if ($service && $service->base_url && $jobUuid) {
            $response = $this->horizonApi->getFailedJob($service, (string) $jobUuid);

            if ($response['success'] ?? false) {
                $jobData = $response['data'] ?? null;

                if (\is_array($jobData)) {

                    $attemptsRaw = $jobData['attempts'] ?? null;

                    if ($attemptsRaw === null && \is_array($jobData['payload'])) {
                        $attemptsRaw = $jobData['payload']['attempts'] ?? null;
                    }

                    $retries = null;
                    if (isset($jobData['retried_by']) && \is_array($jobData['retried_by'])) {
                        $retries = \count($jobData['retried_by']);
                        if ($attemptsRaw === null) {
                            $attemptsRaw = $retries + 1;
                        }
                    }

                    $attempts = null;
                    if ($attemptsRaw !== null) {
                        $attemptsInt = (int) $attemptsRaw;
                        if ($attemptsInt > 0) {
                            $attempts = $attemptsInt;
                        }
                    }

                    $connection = isset($jobData['connection']) ? (string) $jobData['connection'] : null;

                    $tags = [];
                    if (isset($jobData['tags']) && \is_array($jobData['tags'])) {
                        $tags = \array_values(\array_filter($jobData['tags'], static function ($tag) {
                            return \is_string($tag) && $tag !== '';
                        }));
                    }


                    if (isset($jobData['exception']) && (string) $jobData['exception'] !== '') {
                        $exception = (string) $jobData['exception'];
                    }

                    $horizonJob = [
                        'attempts' => $attempts,
                        'connection' => $connection,
                        'retries' => $retries,
                        'tags' => $tags,
                        'uuid' => $jobUuid,
                        'exception' => $exception,
                    ];
                }
            }
        }

        return \view('horizon.jobs.show', [
            'job' => $jobModel,
            'exception' => $exception,
            'horizonJob' => $horizonJob,
            'header' => 'Job: ' . ($jobModel->name ?? $jobModel->job_uuid),
        ]);
    }
}
