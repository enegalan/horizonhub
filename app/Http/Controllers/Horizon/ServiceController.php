<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;
use App\Services\HorizonSyncService;
use App\Services\HorizonApiProxyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller {
    private HorizonSyncService $horizonSync;

    public function __construct(HorizonSyncService $horizonSync) {
        $this->horizonSync = $horizonSync;
    }
    /**
     * Display the list of services and registration form.
     *
     * @return View
     */
    public function index(): View {
        $services = Service::withCount(['horizonJobs', 'horizonFailedJobs'])
            ->orderBy('name')
            ->get();

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
     * @return View
     */
    public function show(Service $service): View {
        // Ensure we have fresh supervisors / jobs for this service using Horizon HTTP API.
        $this->horizonSync->syncRecentJobs((int) $service->id);

        $serviceId = $service->id;

        $jobsPastMinute = HorizonJob::where('service_id', $serviceId)
            ->where('status', 'processed')
            ->where('processed_at', '>=', \now()->subMinute())
            ->count();

        $jobsPastHour = HorizonJob::where('service_id', $serviceId)
            ->where('status', 'processed')
            ->where('processed_at', '>=', \now()->subHour())
            ->count();

        $failedPastSevenDays = HorizonFailedJob::where('service_id', $serviceId)
            ->where('failed_at', '>=', \now()->subDays(7))
            ->count();

        $processedPast24Hours = HorizonJob::where('service_id', $serviceId)
            ->where('status', 'processed')
            ->where('processed_at', '>=', \now()->subDay())
            ->count();

        $deadThreshold = \now()->subMinutes((int) \config('horizonhub.dead_service_minutes'));

        $supervisors = $service->horizonSupervisorStates()
            ->where('last_seen_at', '>=', $deadThreshold)
            ->orderBy('name')
            ->get();

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
            'supervisors' => $supervisors,
            'jobs' => $jobs,
            'header' => 'Horizon Hub – ' . $service->name,
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

