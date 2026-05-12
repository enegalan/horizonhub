<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\UpsertServiceRequest;
use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
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
     * Display the list of services and registration form.
     */
    public function index(): View
    {
        return \view('horizon.services.index', [
            'services' => collect(),
            'defer' => true,
            'header' => 'Services',
        ]);
    }

    /**
     * Show the service dashboard.
     */
    public function show(Request $request, Service $service): View
    {
        $search = (string) $request->query('search', '');

        return \view('horizon.services.show', [
            'service' => $service,
            'header' => $service->name,
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
            'jobsProcessing' => [],
            'jobsProcessed' => [],
            'jobsFailed' => [],
            'filters' => [
                'search' => $search,
            ],
            'defer' => true,
        ]);
    }

    /**
     * Store a new service.
     */
    public function store(UpsertServiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Service::create([
            'name' => $validated['name'],
            'base_url' => \rtrim($validated['base_url'], '/'),
            'public_url' => ! empty($validated['public_url']) ? \rtrim($validated['public_url'], '/') : null,
            'status' => 'offline',
        ]);

        return redirect()
            ->route('horizon.services.index')
            ->with('status', 'Service created.');
    }

    /**
     * Test connectivity with the Horizon HTTP API for the given service.
     */
    public function testConnection(Service $service, HorizonApiProxyService $horizonApi): RedirectResponse
    {
        if (! $service->base_url) {
            return redirect()
                ->back()
                ->with('status', 'Service has no base URL configured.');
        }

        $result = $horizonApi->ping($service);

        if ($result['success']) {
            $service->update([
                'status' => 'online',
                'last_seen_at' => now(),
            ]);

            return redirect()
                ->back()
                ->with('status', 'Service Horizon API is reachable.');
        }

        $service->update(['status' => 'offline']);

        $message = $result['message'] ?? 'Connection test failed.';

        return redirect()
            ->back()
            ->with('status', $message);
    }

    /**
     * Update an existing service.
     */
    public function update(UpsertServiceRequest $request, Service $service, HorizonApiProxyService $horizonApi): RedirectResponse
    {
        $validated = $request->validated();

        $service->update([
            'name' => $validated['name'],
            'base_url' => \rtrim($validated['base_url'], '/'),
            'public_url' => ! empty($validated['public_url']) ? \rtrim($validated['public_url'], '/') : null,
        ]);
        $horizonApi->resetFailureCooldown($service);

        return redirect()
            ->route('horizon.services.index')
            ->with('status', 'Service updated.');
    }
}
