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
        $search = (string) $request->query('search', '');

        $perPage = (int) \config('horizonhub.jobs_per_page');

        $servicesQuery = Service::query()->whereNotNull('base_url');
        if ($serviceFilter !== '') {
            $servicesQuery->where('id', (int) $serviceFilter);
        }

        /** @var \Illuminate\Support\Collection<int, Service> $servicesWithApi */
        $servicesWithApi = $servicesQuery->get();

        $jobsProcessing = \collect();
        $jobsProcessed = \collect();
        $jobsFailed = \collect();

        $apiQuery = [
            'starting_at' => 0,
            'limit' => $perPage,
        ];

        foreach ($servicesWithApi as $service) {
            $response = $this->horizonApi->getPendingJobs($service, $apiQuery);
            $this->private__appendJobsFromApi($jobsProcessing, $service, $response, 'processing', $search);

            $response = $this->horizonApi->getCompletedJobs($service, $apiQuery);
            $this->private__appendJobsFromApi($jobsProcessed, $service, $response, 'processed', $search);

            $response = $this->horizonApi->getFailedJobs($service, $apiQuery);
            $this->private__appendJobsFromApi($jobsFailed, $service, $response, 'failed', $search);
        }

        $pageProcessing = \max(1, (int) $request->query('page_processing', 1));
        $pageProcessed = \max(1, (int) $request->query('page_processed', 1));
        $pageFailed = \max(1, (int) $request->query('page_failed', 1));

        $totalProcessing = $jobsProcessing->count();
        $totalProcessed = $jobsProcessed->count();
        $totalFailed = $jobsFailed->count();

        $offsetProcessing = ($pageProcessing - 1) * $perPage;
        $offsetProcessed = ($pageProcessed - 1) * $perPage;
        $offsetFailed = ($pageFailed - 1) * $perPage;

        $pageItemsProcessing = $perPage > 0 ? $jobsProcessing->slice($offsetProcessing, $perPage)->values() : $jobsProcessing;
        $pageItemsProcessed = $perPage > 0 ? $jobsProcessed->slice($offsetProcessed, $perPage)->values() : $jobsProcessed;
        $pageItemsFailed = $perPage > 0 ? $jobsFailed->slice($offsetFailed, $perPage)->values() : $jobsFailed;

        $baseQuery = $request->query();
        $path = $request->url();

        $paginatorProcessing = new \Illuminate\Pagination\LengthAwarePaginator(
            $pageItemsProcessing,
            $totalProcessing,
            $perPage,
            $pageProcessing,
            ['path' => $path, 'query' => $baseQuery]
        );
        $paginatorProcessing->setPageName('page_processing');

        $paginatorProcessed = new \Illuminate\Pagination\LengthAwarePaginator(
            $pageItemsProcessed,
            $totalProcessed,
            $perPage,
            $pageProcessed,
            ['path' => $path, 'query' => $baseQuery]
        );
        $paginatorProcessed->setPageName('page_processed');

        $paginatorFailed = new \Illuminate\Pagination\LengthAwarePaginator(
            $pageItemsFailed,
            $totalFailed,
            $perPage,
            $pageFailed,
            ['path' => $path, 'query' => $baseQuery]
        );
        $paginatorFailed->setPageName('page_failed');

        $services = Service::orderBy('name')->get();

        return \view('horizon.jobs.index', [
            'jobsProcessing' => $paginatorProcessing,
            'jobsProcessed' => $paginatorProcessed,
            'jobsFailed' => $paginatorFailed,
            'services' => $services,
            'filters' => [
                'serviceFilter' => $serviceFilter,
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
