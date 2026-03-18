<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\HorizonApiProxyService;
use App\Services\HorizonJobResolverService;
use App\Support\Horizon\JobRuntimeHelper;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class JobController extends Controller {

    /**
     * The Horizon API proxy service.
     *
     * @var HorizonApiProxyService
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * The job resolver.
     *
     * @var HorizonJobResolverService
     */
    private HorizonJobResolverService $jobResolver;

    /**
     * Construct the job controller.
     *
     * @param HorizonApiProxyService $horizonApi
     * @param HorizonJobResolverService $jobResolver
     */
    public function __construct(HorizonApiProxyService $horizonApi, HorizonJobResolverService $jobResolver) {
        $this->horizonApi = $horizonApi;
        $this->jobResolver = $jobResolver;
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

        $perPage = (int) \config('horizonhub.jobs_per_page');

        $servicesQuery = Service::query()->whereNotNull('base_url');
        if ($serviceFilter !== '') {
            $servicesQuery->where('id', (int) $serviceFilter);
        }

        /** @var \Illuminate\Support\Collection<int, Service> $servicesWithApi */
        $servicesWithApi = $servicesQuery->get();

        $jobs = \collect();

        foreach ($servicesWithApi as $service) {
            $apiQuery = [
                'starting_at' => 0,
                'limit' => $perPage,
            ];

            $isFailed = $statusFilter === 'failed';
            $isProcessed = $statusFilter === 'processed';
            $isProcessing = $statusFilter === 'processing';

            if ($statusFilter === '' || $isFailed) {
                $response = $this->horizonApi->getFailedJobs($service, $apiQuery);
                $this->private__appendJobsFromApi($jobs, $service, $response, 'failed', $search);
            }
            if ($statusFilter === '' || $isProcessed) {
                $response = $this->horizonApi->getCompletedJobs($service, $apiQuery);
                $this->private__appendJobsFromApi($jobs, $service, $response, 'processed', $search);
            }
            if ($statusFilter === '' || $isProcessing) {
                $response = $this->horizonApi->getPendingJobs($service, $apiQuery);
                $this->private__appendJobsFromApi($jobs, $service, $response, 'processing', $search);
            }
        }

        $page = (int) $request->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $total = $jobs->count();
        $offset = ($page - 1) * $perPage;
        $pageItems = $perPage > 0 ? $jobs->slice($offset, $perPage)->values() : $jobs;

        $services = Service::orderBy('name')->get();

        return \view('horizon.jobs.index', [
            'jobs' => new \Illuminate\Pagination\LengthAwarePaginator(
                $pageItems,
                $total,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            ),
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
     * @param string $job
     * @return View
     */
    public function show(string $job): View {
        $resolved = $this->jobResolver->getJobAndService($job);

        if ($resolved === null) {
            \abort(404);
        }

        $service = $resolved['service'];
        $jobData = $resolved['job'];

        $attemptsRaw = $jobData['attempts'] ?? null;
        if ($attemptsRaw === null && isset($jobData['payload']) && \is_array($jobData['payload'])) {
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

        $exception = null;
        if (isset($jobData['exception']) && (string) $jobData['exception'] !== '') {
            $exception = (string) $jobData['exception'];
        }

        $queuedAt = $jobData['pushedAt'] ?? null;
        $processedAt = $jobData['completed_at'] ?? null;
        $failedAt = $jobData['failed_at'] ?? null;
        $runtimeSeconds = isset($jobData['runtime']) && \is_numeric($jobData['runtime'])
            ? (float) $jobData['runtime']
            : null;

        $runtime = JobRuntimeHelper::getFormattedRuntime(
            JobRuntimeHelper::getRuntimeSeconds($runtimeSeconds, $queuedAt, $processedAt, $failedAt)
        );

        $rawStatus = (string) ($jobData['status'] ?? 'failed');
        $status = $rawStatus === 'completed' ? 'processed' : $rawStatus;

        $jobView = (object) [
            'uuid' => $jobData['uuid'] ?? $job,
            'name' => $jobData['name'] ?? ($jobData['displayName'] ?? null),
            'queue' => $jobData['queue'] ?? null,
            'status' => $status,
            'attempts' => $attempts,
            'queued_at' => $queuedAt,
            'processed_at' => $processedAt,
            'failed_at' => $failedAt,
            'runtime' => $runtime,
            'payload' => $jobData['payload'] ?? null,
            'service' => $service,
        ];

        $horizonJob = [
            'attempts' => $attempts,
            'connection' => $connection,
            'retries' => $retries,
            'tags' => $tags,
            'uuid' => $jobView->uuid,
            'exception' => $exception,
        ];

        return \view('horizon.jobs.show', [
            'job' => $jobView,
            'exception' => $exception,
            'horizonJob' => $horizonJob,
            'header' => 'Job: ' . ($jobView->name ?? $jobView->uuid),
        ]);
    }

    /**
     * @param \Illuminate\Support\Collection $jobs
     * @param Service $service
     * @param array $response
     * @param string $status
     * @param string $search
     * @return void
     */
    private function private__appendJobsFromApi($jobs, Service $service, array $response, string $status, string $search): void {
        $data = $response['data'] ?? null;
        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return;
        }

        foreach ($data['jobs'] ?? [] as $job) {
            if (! \is_array($job)) {
                continue;
            }

            $uuid = (string) $job['id'];
            if (empty($uuid)) {
                continue;
            }

            $queue = (string) $job['queue'];
            $name = (string) $job['name'];

            if (! empty($search)) {
                $haystack = "$queue $name $uuid";
                if (\stripos($haystack, $search) === false) {
                    continue;
                }
            }

            $payload = $job['payload'] ?? [];

            $pushedAt = $job['pushedAt'] ?? $payload['pushedAt'] ?? null;
            $completedAt = $job['completed_at'] ?? null;
            $failedAtRaw = $job['failed_at'] ?? null;

            $queuedAt = $this->private__parseJobTimestamp($pushedAt);
            $processedAt = $this->private__parseJobTimestamp($completedAt);
            $failedAt = $this->private__parseJobTimestamp($failedAtRaw);
            JobRuntimeHelper::normalizeStatusDates($status, $processedAt, $failedAt);

            $attemptsRaw = $job['attempts'] ?? $payload['attempts'] ?? null;
            $attempts = $attemptsRaw !== null && $attemptsRaw !== '' ? (int) $attemptsRaw : null;
            if ($attempts !== null && $attempts < 1) {
                $attempts = null;
            }

            $jobs->push((object) [
                'uuid' => $uuid,
                'queue' => $queue,
                'name' => $name,
                'status' => $status,
                'attempts' => $attempts,
                'queued_at' => $queuedAt,
                'processed_at' => $processedAt,
                'failed_at' => $failedAt,
                'runtime' => JobRuntimeHelper::getFormattedRuntime(
                    JobRuntimeHelper::getRuntimeSeconds(
                        isset($job['runtime']) && \is_numeric($job['runtime']) ? (float) $job['runtime'] : null,
                        $queuedAt,
                        $processedAt,
                        $failedAt
                    )
                ),
                'service' => $service,
            ]);
        }
    }

    /**
     * Parse a job timestamp (ISO string or Unix float).
     *
     * @param mixed $value
     * @return \Carbon\Carbon|null
     */
    private function private__parseJobTimestamp($value): ?Carbon {
        if ($value === null || $value === '') {
            return null;
        }
        if (\is_numeric($value)) {
            $seconds = (float) $value;
            return Carbon::createFromTimestampMs((int) \round($seconds * 1000));
        }

        return Carbon::parse($value);
    }
}
