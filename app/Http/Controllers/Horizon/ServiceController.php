<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\ServiceShowPageDataService;
use App\Services\Horizon\ServiceStatsAttachmentService;
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
    public function index(HorizonApiProxyService $horizonApi, ServiceStatsAttachmentService $serviceStats): View
    {
        $services = Service::query()
            ->orderBy('name')
            ->get();

        $serviceStats->attachHorizonStats($services, $horizonApi);

        return \view('horizon.services.index', [
            'services' => $services,
            'header' => 'Services',
        ]);
    }

    /**
     * Show the service dashboard.
     */
    public function show(Request $request, Service $service, HorizonApiProxyService $horizonApi, ServiceShowPageDataService $serviceShowPageData): View
    {
        $data = $serviceShowPageData->build($service, $request, $horizonApi);

        return \view('horizon.services.show', \array_merge($data, [
            'service' => $service,
            'header' => $service->name,
        ]));
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

        if ($result['success'] ?? false) {
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
}
