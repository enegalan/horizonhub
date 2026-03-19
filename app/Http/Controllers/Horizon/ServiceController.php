<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\HorizonSyncService;
use App\Services\HorizonApiProxyService;
use App\Services\HorizonMetricsService;
use App\Support\Horizon\JobRuntimeHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller {

    /**
     * The Horizon sync service.
     *
     * @var HorizonSyncService
     */
    private HorizonSyncService $horizonSync;

    /**
     * The Horizon metrics service.
     *
     * @var HorizonMetricsService
     */
    private HorizonMetricsService $metrics;

    /**
     * Construct the service controller.
     *
     * @param HorizonSyncService $horizonSync
     * @param HorizonMetricsService $metrics
     */
    public function __construct(HorizonSyncService $horizonSync, HorizonMetricsService $metrics) {
        $this->horizonSync = $horizonSync;
        $this->metrics = $metrics;
    }

    /**
     * Display the list of services and registration form.
     *
     * @return View
     */
    public function index(HorizonApiProxyService $horizonApi): View {
        $services = Service::query()
            ->orderBy('name')
            ->get();

        $failedJobCounts = [];
        $recentJobCounts = [];
        $horizonStatuses = [];

        /** @var Service $service */
        foreach ($services as $service) {
            if (! $service->base_url) {
                $failedJobCounts[$service->id] = 0;
                $recentJobCounts[$service->id] = 0;
                continue;
            }

            $response = $horizonApi->getStats($service);
            $data = $response['data'] ?? null;

            if (($response['success'] ?? false) && \is_array($data)) {
                $failedJobCounts[$service->id] = isset($data['failedJobs']) ? (int) $data['failedJobs'] : 0;
                $recentJobCounts[$service->id] = isset($data['recentJobs']) ? (int) $data['recentJobs'] : 0;
                $horizonStatuses[$service->id] = isset($data['status']) && (string) $data['status'] !== '' ? (string) $data['status'] : null;
            } else {
                $failedJobCounts[$service->id] = 0;
                $recentJobCounts[$service->id] = 0;
                $horizonStatuses[$service->id] = null;
            }
        }

        foreach ($services as $service) {
            $service->horizon_failed_jobs_count = (int) ($failedJobCounts[$service->id] ?? 0);
            $service->horizon_jobs_count = (int) ($recentJobCounts[$service->id] ?? 0);
            $service->horizon_status = $horizonStatuses[$service->id] ?? null;
        }

        return \view('horizon.services.index', [
            'services' => $services,
            'header' => 'Horizon Hub – Services',
        ]);
    }

    /**
     * Store a new service.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:services,name',
            'base_url' => 'required|url',
            'public_url' => 'nullable|url',
        ]);

        $apiKey = $this->private__generateApiKey();

        Service::create([
            'name' => $validated['name'],
            'api_key' => $apiKey,
            'base_url' => \rtrim($validated['base_url'], '/'),
            'public_url' => ! empty($validated['public_url']) ? \rtrim($validated['public_url'], '/') : null,
            'status' => 'online',
        ]);

        return redirect()
            ->route('horizon.services.index')
            ->with('status', 'Service created.');
    }

    /**
     * Edit an existing service.
     *
     * @param Service $service
     * @return View
     */
    public function edit(Service $service): View {
        return \view('horizon.services.edit', [
            'service' => $service,
            'header' => 'Edit service',
        ]);
    }

    /**
     * Update an existing service.
     *
     * @param Request $request
     * @param Service $service
     * @return RedirectResponse
     */
    public function update(Request $request, Service $service): RedirectResponse {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:services,name,' . (int) $service->id,
            'base_url' => 'required|url',
            'public_url' => 'nullable|url',
        ]);

        $service->update([
            'name' => $validated['name'],
            'base_url' => \rtrim($validated['base_url'], '/'),
            'public_url' => ! empty($validated['public_url']) ? \rtrim($validated['public_url'], '/') : null,
        ]);

        return redirect()
            ->route('horizon.services.index')
            ->with('status', 'Service updated.');
    }

    /**
     * Delete a service.
     *
     * @param Service $service
     * @return RedirectResponse
     */
    public function destroy(Service $service): RedirectResponse {
        $service->delete();

        return redirect()
            ->route('horizon.services.index')
            ->with('status', 'Service deleted.');
    }

    /**
     * Test connectivity with the Horizon HTTP API for the given service.
     *
     * @param Service $service
     * @param HorizonApiProxyService $horizonApi
     * @return RedirectResponse
     */
    public function testConnection(Service $service, HorizonApiProxyService $horizonApi): RedirectResponse {
        if (! $service->base_url) {
            return redirect()
                ->route('horizon.services.index')
                ->with('status', 'Service has no base URL configured.');
        }

        $result = $horizonApi->ping($service);

        if ($result['success'] ?? false) {
            $service->update([
                'status' => 'online',
                'last_seen_at' => now(),
            ]);

            return redirect()
                ->route('horizon.services.index')
                ->with('status', 'Service Horizon API is reachable.');
        }

        $service->update(['status' => 'offline']);

        $message = $result['message'] ?? 'Connection test failed.';

        return redirect()
            ->route('horizon.services.index')
            ->with('status', $message);
    }

    /**
     * Show the service dashboard.
     *
     * @param Service $service
     * @param HorizonApiProxyService $horizonApi
     * @return View
     */
    public function show(Request $request, Service $service, HorizonApiProxyService $horizonApi): View {
        $this->horizonSync->syncRecentJobs((int) $service->id);

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
            if (isset($statsData['status']) && !empty((string) $statsData['status'])) {
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

        $perPage = (int) \config('horizonhub.jobs_per_page');
        $apiLimit = $perPage > 0 ? $perPage * 3 : 50;
        $apiLimit = \max($apiLimit, 50);
        $apiQuery = ['starting_at' => 0, 'limit' => $apiLimit];

        $jobsProcessing = \collect();
        $jobsProcessed = \collect();
        $jobsFailed = \collect();

        $pendingResponse = $horizonApi->getPendingJobs($service, $apiQuery);
        $this->private__appendServiceJobs($jobsProcessing, $pendingResponse, 'processing', $search);

        $completedResponse = $horizonApi->getCompletedJobs($service, $apiQuery);
        $this->private__appendServiceJobs($jobsProcessed, $completedResponse, 'processed', $search);

        $failedResponse = $horizonApi->getFailedJobs($service, $apiQuery);
        $this->private__appendServiceJobs($jobsFailed, $failedResponse, 'failed', $search);

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

        return \view('horizon.services.show', [
            'service' => $service,
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
            'jobsProcessing' => $paginatorProcessing,
            'jobsProcessed' => $paginatorProcessed,
            'jobsFailed' => $paginatorFailed,
            'filters' => [
                'search' => $search,
            ],
            'header' => "Horizon Hub – {$service->name}",
        ]);
    }

    /**
     * Append job items from an API response to the collection for service dashboard.
     *
     * @param \Illuminate\Support\Collection $jobs
     * @param array $response
     * @param string $status
     * @param string $search
     * @return void
     */
    private function private__appendServiceJobs($jobs, array $response, string $status, string $search = ''): void {
        $data = $response['data'] ?? null;
        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return;
        }

        foreach ($data['jobs'] ?? [] as $job) {
            if (! \is_array($job)) {
                continue;
            }

            $uuid = (string) ($job['id'] ?? '');
            if ($uuid === '') {
                continue;
            }

            $queue = (string) ($job['queue'] ?? '');
            $name = (string) ($job['name'] ?? '');
            if ($search !== '') {
                $haystack = $queue . ' ' . $name . ' ' . $uuid;
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

            $runtimeSeconds = isset($job['runtime']) && \is_numeric($job['runtime']) ? (float) $job['runtime'] : null;
            $runtime = JobRuntimeHelper::getFormattedRuntime(
                JobRuntimeHelper::getRuntimeSeconds($runtimeSeconds, $queuedAt, $processedAt, $failedAt)
            );

            $jobs->push((object) [
                'id' => $uuid,
                'job_uuid' => $uuid,
                'uuid' => $uuid,
                'queue' => $queue,
                'name' => $name,
                'status' => $status,
                'attempts' => $attempts,
                'queued_at' => $queuedAt,
                'processed_at' => $processedAt,
                'failed_at' => $failedAt,
                'runtime' => $runtime,
            ]);
        }
    }

    /**
     * Parse a job timestamp (ISO string or Unix float).
     *
     * @param mixed $value
     * @return \Carbon\Carbon|null
     */
    private function private__parseJobTimestamp($value): ?\Carbon\Carbon {
        if ($value === null || $value === '') {
            return null;
        }
        if (\is_numeric($value)) {
            $seconds = (float) $value;
            return \Carbon\Carbon::createFromTimestampMs((int) \round($seconds * 1000));
        }
        return \Carbon\Carbon::parse($value);
    }

    /**
     * Generate a unique API key for a service.
     *
     * @return string
     */
    private function private__generateApiKey(): string {
        do {
            $apiKey = \Str::random(64);
        } while (Service::where('api_key', $apiKey)->exists());

        return $apiKey;
    }
}
