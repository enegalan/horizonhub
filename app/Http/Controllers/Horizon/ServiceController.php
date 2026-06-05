<?php

namespace App\Http\Controllers\Horizon;

use App\Contracts\HorizonHubStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\UpsertServiceRequest;
use App\Models\Service;
use App\Services\Horizon\Contracts\HorizonClientApi;
use App\Services\Services\ServiceFilterService;
use App\Support\FlashStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Show the form to register a new service.
     */
    public function create(HorizonHubStore $store): View
    {
        return \view('horizon.services.form', [
            'service' => new Service,
            'header' => 'Register service',
            'existingTags' => $store->allServiceTags(),
        ]);
    }

    /**
     * Delete a service.
     */
    public function destroy(Service $service, HorizonHubStore $store): RedirectResponse
    {
        $store->deleteService($service);

        return redirect()
            ->route('horizon.services.index')
            ->with('status', FlashStatus::success('Service deleted.'));
    }

    /**
     * Edit an existing service.
     */
    public function edit(Service $service, HorizonHubStore $store): View
    {
        return \view('horizon.services.form', [
            'service' => $service,
            'header' => 'Edit service',
            'existingTags' => $store->allServiceTags(),
        ]);
    }

    /**
     * Display the list of services.
     */
    public function index(Request $request, ServiceFilterService $serviceFilter): View
    {
        return \view('horizon.services.index', \array_merge([
            'services' => collect(),
            'defer' => true,
            'header' => 'Services',
        ], $serviceFilter->viewData($request)));
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
    public function store(UpsertServiceRequest $request, HorizonHubStore $store): RedirectResponse
    {
        $validated = $request->validated();

        $store->createService([
            'name' => $validated['name'],
            'base_url' => \rtrim($validated['base_url'], '/'),
            'public_url' => ! empty($validated['public_url']) ? \rtrim($validated['public_url'], '/') : null,
            'status' => 'offline',
            'enabled' => true,
            'tags' => $validated['tags'] ?? [],
        ], $validated['headers'] ?? []);

        return redirect()
            ->route('horizon.services.index')
            ->with('status', FlashStatus::success('Service created.'));
    }

    /**
     * Test connectivity with the Horizon HTTP API for the given service.
     */
    public function testConnection(Service $service, HorizonClientApi $horizonApi, HorizonHubStore $store): RedirectResponse
    {
        $result = $horizonApi->ping($service);

        if ($result['success']) {
            $store->updateServiceConnectionState($service, 'online', now());

            return redirect()
                ->back()
                ->with('status', FlashStatus::success('Service Horizon API is reachable.'));
        }

        $store->updateServiceConnectionState($service, 'offline');

        $message = $result['message'] ?? 'Connection test failed.';

        return redirect()
            ->back()
            ->with('status', FlashStatus::error($message));
    }

    /**
     * Toggle whether a service is enabled.
     */
    public function toggleEnabled(Service $service, HorizonHubStore $store): JsonResponse
    {
        $store->toggleServiceEnabled($service);

        return \response()->json([
            'service_id' => $service->id,
            'enabled' => $service->enabled,
        ]);
    }

    /**
     * Update an existing service.
     */
    public function update(UpsertServiceRequest $request, Service $service, HorizonClientApi $horizonApi, HorizonHubStore $store): RedirectResponse
    {
        $validated = $request->validated();

        $store->updateService($service, [
            'name' => $validated['name'],
            'base_url' => \rtrim($validated['base_url'], '/'),
            'public_url' => ! empty($validated['public_url']) ? \rtrim($validated['public_url'], '/') : null,
            'tags' => $validated['tags'] ?? [],
        ], $validated['headers'] ?? []);

        $horizonApi->resetFailureCooldown($service);

        return redirect()
            ->route('horizon.services.index')
            ->with('status', FlashStatus::success('Service updated.'));
    }
}
