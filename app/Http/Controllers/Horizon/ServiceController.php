<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\HorizonApiProxyService;
use App\Services\HorizonJobListService;
use App\Services\HorizonMetricsService;
use App\Support\Horizon\ConfigHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * The Horizon metrics service.
     */
    private HorizonMetricsService $metrics;

    /**
     * The job list service.
     */
    private HorizonJobListService $jobList;

    /**
     * Construct the service controller.
     */
    public function __construct(HorizonMetricsService $metrics, HorizonJobListService $jobList)
    {
        $this->metrics = $metrics;
        $this->jobList = $jobList;
    }

    /**
     * Display the list of services and registration form.
     */
    public function index(HorizonApiProxyService $horizonApi): View
    {
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
     */
    public function store(Request $request): RedirectResponse
    {
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
     */
    public function edit(Service $service): View
    {
        return \view('horizon.services.edit', [
            'service' => $service,
            'header' => 'Edit service',
        ]);
    }

    /**
     * Update an existing service.
     */
    public function update(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:services,name,'.(int) $service->id,
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
     */
    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()
            ->route('horizon.services.index')
            ->with('status', 'Service deleted.');
    }

    /**
     * Test connectivity with the Horizon HTTP API for the given service.
     */
    public function testConnection(Service $service, HorizonApiProxyService $horizonApi): RedirectResponse
    {
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
     */
    public function show(Request $request, Service $service, HorizonApiProxyService $horizonApi): View
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

        $perPage = (int) ConfigHelper::get('horizonhub.jobs_per_page');

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
            'jobsProcessing' => $paginators['processing'],
            'jobsProcessed' => $paginators['processed'],
            'jobsFailed' => $paginators['failed'],
            'filters' => [
                'search' => $search,
            ],
            'header' => "Horizon Hub – {$service->name}",
        ]);
    }

    /**
     * Generate a unique API key for a service.
     */
    private function private__generateApiKey(): string
    {
        do {
            $apiKey = \Str::random(64);
            $apiKeyHash = Service::hashApiKey($apiKey);
        } while (Service::where('api_key_hash', $apiKeyHash)->exists());

        return $apiKey;
    }
}
