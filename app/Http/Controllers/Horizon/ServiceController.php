<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\UpsertServiceRequest;
use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\ServiceFilterService;
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
    public function create(): View
    {
        return \view('horizon.services.form', [
            'service' => new Service,
            'header' => 'Register service',
            'existingTags' => Service::query()->get(['tags'])->pluck('tags')->flatten()->unique()->sort()->values()->all(),
        ]);
    }

    /**
     * Delete a service.
     */
    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()
            ->route('horizon.services.index')
            ->with('status', FlashStatus::success('Service deleted.'));
    }

    /**
     * Edit an existing service.
     */
    public function edit(Service $service): View
    {
        return \view('horizon.services.form', [
            'service' => $service,
            'header' => 'Edit service',
            'existingTags' => Service::query()->get(['tags'])->pluck('tags')->flatten()->unique()->sort()->values()->all(),
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
    public function store(UpsertServiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $service = Service::create([
            'name' => $validated['name'],
            'base_url' => \rtrim($validated['base_url'], '/'),
            'public_url' => ! empty($validated['public_url']) ? \rtrim($validated['public_url'], '/') : null,
            'status' => 'offline',
            'enabled' => true,
            'tags' => $validated['tags'] ?? [],
        ]);

        $this->private__storeHeaders($service, $validated['headers'] ?? []);

        return redirect()
            ->route('horizon.services.index')
            ->with('status', FlashStatus::success('Service created.'));
    }

    /**
     * Test connectivity with the Horizon HTTP API for the given service.
     */
    public function testConnection(Service $service, HorizonApiProxyService $horizonApi): RedirectResponse
    {
        if (! $service->base_url) {
            return redirect()
                ->back()
                ->with('status', FlashStatus::warning('Service has no base URL configured.'));
        }

        $result = $horizonApi->ping($service);

        if ($result['success']) {
            $service->update([
                'status' => 'online',
                'last_seen_at' => now(),
            ]);

            return redirect()
                ->back()
                ->with('status', FlashStatus::success('Service Horizon API is reachable.'));
        }

        $service->update(['status' => 'offline']);

        $message = $result['message'] ?? 'Connection test failed.';

        return redirect()
            ->back()
            ->with('status', FlashStatus::error($message));
    }

    /**
     * Toggle whether a service is enabled.
     */
    public function toggleEnabled(Service $service): JsonResponse
    {
        $service->enabled = ! $service->enabled;
        $service->save();

        return \response()->json([
            'service_id' => $service->id,
            'enabled' => $service->enabled,
        ]);
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
            'tags' => $validated['tags'] ?? [],
        ]);

        $service->headers()->delete();
        $this->private__storeHeaders($service, $validated['headers'] ?? []);

        $horizonApi->resetFailureCooldown($service);

        return redirect()
            ->route('horizon.services.index')
            ->with('status', FlashStatus::success('Service updated.'));
    }

    /**
     * Store the headers for a service.
     */
    private function private__storeHeaders(Service $service, array $headers): void
    {
        foreach ($headers as $header) {
            if (! \is_array($header)) {
                continue;
            }

            $name = \trim((string) ($header['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $value = isset($header['value']) ? \trim((string) $header['value']) : '';

            $service->headers()->create([
                'name' => $name,
                'value' => $value === '' ? null : $value,
            ]);
        }
    }
}
