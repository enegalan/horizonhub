<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\HorizonJob;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use App\Services\HorizonSyncService;
use App\Services\HorizonApiProxyService;
use App\Services\HorizonMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller {
    private HorizonSyncService $horizonSync;
    private HorizonMetricsService $metrics;

    public function __construct(HorizonSyncService $horizonSync, HorizonMetricsService $metrics) {
        $this->horizonSync = $horizonSync;
        $this->metrics = $metrics;
    }
    /**
     * Display the list of services and registration form.
     *
     * @return View
     */
    public function index(): View {
        $services = Service::withCount('horizonJobs')
            ->orderBy('name')
            ->get();

        $failedJobCounts = DB::table('horizon_failed_jobs')
            ->select('service_id', DB::raw('COUNT(DISTINCT job_uuid) as failed_jobs_count'))
            ->groupBy('service_id')
            ->pluck('failed_jobs_count', 'service_id');

        foreach ($services as $service) {
            $service->horizon_failed_jobs_count = (int) ($failedJobCounts[$service->id] ?? 0);
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

        $apiKey = $this->generateApiKey();

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
    public function show(Service $service, HorizonApiProxyService $horizonApi): View {
        $this->horizonSync->syncRecentJobs((int) $service->id);

        $serviceId = $service->id;

        $jobsPastMinute = $this->metrics->getJobsPastMinute($service);
        $jobsPastHour = $this->metrics->getJobsPastHour($service);
        $failedPastSevenDays = $this->metrics->getFailedPastSevenDays($service);
        $processedPast24Hours = $this->metrics->getProcessedPast24Hours($service);

        $totalProcesses = null;
        $maxWaitTimeSeconds = null;
        $queueWithMaxRuntime = null;
        $queueWithMaxThroughput = null;
        $supervisorGroups = \collect();
        $workloadQueues = \collect();

        $statsResponse = $horizonApi->getStats($service);
        $statsData = $statsResponse['data'] ?? null;

        if (($statsResponse['success'] ?? false) && \is_array($statsData)) {
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

                    if (! $supervisorGroups->has($groupName)) {
                        $supervisorGroups[$groupName] = \collect();
                    }

                    $supervisorGroups[$groupName]->push((object) [
                        'name' => $name,
                        'connection' => $connection,
                        'queues' => $queues,
                        'processes' => $processes,
                        'balancing' => $balancing,
                    ]);
                }
            }
            $supervisorGroups = $supervisorGroups->sortKeys();
        }

        $deadThreshold = \now()->subMinutes((int) \config('horizonhub.dead_service_minutes'));

        $supervisors = $service->horizonSupervisorStates()
            ->where('last_seen_at', '>=', $deadThreshold)
            ->orderBy('name')
            ->get();

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

        $jobsQuery = HorizonJob::query()
            ->where('service_id', $serviceId)
            ->orderByDesc('created_at');

        $perPage = (int) \config('horizonhub.jobs_per_page');
        $jobs = $jobsQuery->paginate($perPage)->withQueryString();

        return \view('horizon.services.show', [
            'service' => $service,
            'jobsPastMinute' => $jobsPastMinute,
            'jobsPastHour' => $jobsPastHour,
            'failedPastSevenDays' => $failedPastSevenDays,
            'processedPast24Hours' => $processedPast24Hours,
            'totalProcesses' => $totalProcesses,
            'maxWaitTimeSeconds' => $maxWaitTimeSeconds,
            'queueWithMaxRuntime' => $queueWithMaxRuntime,
            'queueWithMaxThroughput' => $queueWithMaxThroughput,
            'supervisors' => $supervisors,
            'supervisorGroups' => $supervisorGroups,
            'workloadQueues' => $workloadQueues,
            'jobs' => $jobs,
            'header' => "Horizon Hub – {$service->name}",
        ]);
    }

    /**
     * Generate a unique API key for a service.
     *
     * @return string
     */
    private function generateApiKey(): string {
        do {
            $apiKey = \Str::random(64);
        } while (Service::where('api_key', $apiKey)->exists());

        return $apiKey;
    }
}
