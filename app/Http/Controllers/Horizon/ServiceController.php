<?php

namespace App\Http\Controllers\Horizon;

use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\UpsertServiceRequest;
use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Services\Horizon\HorizonClientCacheService;
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
    public function create(): View
    {
        return \view('horizon.services.form', [
            'service' => new Service,
            'header' => 'Register service',
            'existingTags' => Service::allTags(),
        ]);
    }

    /**
     * Delete a service.
     */
    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return $this->redirectToRoute('horizon.services.index', FlashStatus::success('Service deleted.'));
    }

    /**
     * Edit an existing service.
     */
    public function edit(Service $service): View
    {
        return \view('horizon.services.form', [
            'service' => $service,
            'header' => 'Edit service',
            'existingTags' => Service::allTags(),
        ]);
    }

    /**
     * Display the list of services.
     */
    public function index(Request $request): View
    {
        return \view('horizon.services.index', ServiceFilterService::indexViewData($request, [
            'services' => collect(),
            'header' => 'Services',
        ]));
    }

    /**
     * Show the service dashboard.
     */
    public function show(Request $request, Service $service): View
    {
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
            'search' => \trim((string) $request->query('search', '')),
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
            ...$this->private__attributesFromValidated($validated),
            'status' => ServiceStatus::Offline->value,
            'enabled' => true,
        ]);

        $this->private__storeHeaders($service, $validated['headers'] ?? []);

        return $this->redirectToRoute('horizon.services.index', FlashStatus::success('Service created.'));
    }

    /**
     * Test connectivity with the Horizon HTTP API for the given service.
     */
    public function testConnection(Service $service): RedirectResponse
    {
        $result = HorizonClientApiService::ping($service);

        if ($result['success']) {
            $service->update([
                'status' => ServiceStatus::Online->value,
                'last_seen_at' => now(),
            ]);

            return redirect()
                ->back()
                ->with('status', FlashStatus::success('Service Horizon API is reachable.'));
        }

        $service->update(['status' => ServiceStatus::Offline->value]);

        $message = $result['message'] ?? 'Connection test failed.';

        if (\str_contains(\strtolower((string) $message), 'timed out')) {
            $message .= \sprintf(
                ' Consider raising HORIZON_HUB_API_TIMEOUT (currently %ds) if this service is legitimately slow.',
                (int) config('horizonhub.api_timeout'),
            );

            return redirect()
                ->back()
                ->with('status', FlashStatus::warning($message));
        }

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
    public function update(UpsertServiceRequest $request, Service $service): RedirectResponse
    {
        $validated = $request->validated();

        $service->update($this->private__attributesFromValidated($validated));

        $service->headers()->delete();
        $this->private__storeHeaders($service, $validated['headers'] ?? []);

        HorizonClientCacheService::forgetFailureCooldown($service);

        return $this->redirectToRoute('horizon.services.index', FlashStatus::success('Service updated.'));
    }

    /**
     * Build the service attributes from validated input.
     *
     * @param array<string, mixed> $validated The validated input.
     *
     * @return array{name: string, base_url: string, public_url: string|null, tags: list<string>}
     */
    private function private__attributesFromValidated(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'base_url' => $validated['base_url'],
            'public_url' => $validated['public_url'] ?? null,
            'tags' => $validated['tags'] ?? [],
        ];
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

            if (blank($name)) {
                continue;
            }

            $value = isset($header['value']) ? \trim((string) $header['value']) : '';

            $service->headers()->create([
                'name' => $name,
                'value' => blank($value) ? null : $value,
            ]);
        }
    }
}
