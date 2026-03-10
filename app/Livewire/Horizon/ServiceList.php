<?php

namespace App\Livewire\Horizon;

use App\Models\Service;
use App\Services\HorizonApiProxyService;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class ServiceList extends Component {
    /**
     * The name of the service.
     *
     * @var string
     */
    public string $name = '';

    /**
     * The base URL of the service.
     *
     * @var string
     */
    public string $baseUrl = '';

    /**
     * The public URL of the service.
     *
     * @var string
     */
    public string $publicUrl = '';

    /**
     * The ID of the service being edited.
     *
     * @var int|null
     */
    public ?int $editingServiceId = null;

    /**
     * The name of the service being edited.
     *
     * @var string
     */
    public string $editName = '';

    /**
     * The base URL of the service being edited.
     *
     * @var string
     */
    public string $editBaseUrl = '';

    /**
     * The public URL of the service being edited.
     *
     * @var string
     */
    public string $editPublicUrl = '';

    /**
     * The ID of the service being confirmed for deletion.
     *
     * @var int|null
     */
    public ?int $confirmingServiceId = null;

    /**
     * The name of the service being confirmed for deletion.
     *
     * @var string|null
     */
    public ?string $confirmingServiceName = null;

    /**
     * The message of the service being confirmed for deletion.
     *
     * @var string|null
     */
    public ?string $confirmingServiceMessage = null;

    /**
     * The rules for the service list component.
     *
     * @var array<string, string>
     */
    protected $rules = [
        'name' => 'required|string|max:255|unique:services,name',
        'baseUrl' => 'required|url',
        'publicUrl' => 'nullable|url',
    ];

    /**
     * Generate a new API key.
     *
     * @return string
     */
    private function generateApiKey(): string {
        do {
            $apiKey = \Str::random(64);
        } while (Service::where('api_key', $apiKey)->exists());
        return $apiKey;
    }

    /**
     * Save the service.
     *
     * @return void
     */
    public function save(): void {
        $this->validate();
        $apiKey = $this->generateApiKey();
        Service::create([
            'name' => $this->name,
            'api_key' => $apiKey,
            'base_url' => \rtrim($this->baseUrl, '/'),
            'public_url' => $this->publicUrl !== '' ? \rtrim($this->publicUrl, '/') : null,
            'status' => 'online',
        ]);
        $this->reset('name', 'baseUrl', 'publicUrl');
        $this->dispatch('service-created');
    }

    /**
     * Open the edit modal.
     *
     * @param int $serviceId
     * @return void
     */
    public function openEdit(int $serviceId): void {
        $service = Service::find($serviceId);
        if (! $service) {
            return;
        }
        $this->editingServiceId = $serviceId;
        $this->editName = $service->name;
        $this->editBaseUrl = $service->base_url ?? '';
        $this->editPublicUrl = $service->public_url ?? '';
    }

    /**
     * Cancel the edit.
     *
     * @return void
     */
    public function cancelEdit(): void {
        $this->reset('editingServiceId', 'editName', 'editBaseUrl', 'editPublicUrl');
    }

    /**
     * Update the service.
     *
     * @return void
     */
    public function updateService(): void {
        $this->validate([
            'editName' => 'required|string|max:255|unique:services,name,' . (int) $this->editingServiceId,
            'editBaseUrl' => 'required|url',
            'editPublicUrl' => 'nullable|url',
        ]);
        $service = Service::find($this->editingServiceId);
        if (! $service) {
            return;
        }
        $service->update([
            'name' => $this->editName,
            'base_url' => \rtrim($this->editBaseUrl, '/'),
            'public_url' => $this->editPublicUrl !== '' ? \rtrim($this->editPublicUrl, '/') : null,
        ]);
        $this->cancelEdit();
        $this->dispatch('toast', type: 'success', message: 'Service updated.');
    }

    /**
     * Delete the service.
     *
     * @param int $serviceId
     * @return void
     */
    public function deleteService(int $serviceId): void {
        $service = Service::find($serviceId);
        if (! $service) {
            return;
        }

        if ($this->editingServiceId === $serviceId) {
            $this->cancelEdit();
        }

        $service->delete();

        $this->dispatch('toast', type: 'success', message: 'Service deleted.');
    }

    /**
     * Test connectivity with the Horizon HTTP API for the given service.
     *
     * @param int $serviceId
     * @return void
     */
    public function testConnection(HorizonApiProxyService $horizonApi, int $serviceId): void {
        $service = Service::find($serviceId);
        if (! $service) {
            return;
        }

        if (! $service->base_url) {
            $this->dispatch('toast', type: 'error', message: 'Service has no base URL configured.');
            return;
        }

        $result = $horizonApi->ping($service);

        if ($result['success'] ?? false) {
            $service->update([
                'status' => 'online',
                'last_seen_at' => now(),
            ]);
            $this->dispatch('toast', type: 'success', message: 'Service Horizon API is reachable.');
            $this->dispatch('horizonhub-refresh');
            return;
        }

        $service->update(['status' => 'offline']);

        $message = $result['message'] ?? 'Connection test failed.';
        $this->dispatch('toast', type: 'error', message: $message);
        $this->dispatch('horizonhub-refresh');
    }

    /**
     * Confirm the deletion of the service.
     *
     * @param int $serviceId
     * @return void
     */
    public function confirmDeleteService(int $serviceId): void {
        $service = Service::find($serviceId);

        $this->confirmingServiceId = $serviceId;
        $this->confirmingServiceName = $service ? $service->name : ('Service #' . $serviceId);
        $this->confirmingServiceMessage = 'Are you sure you want to delete service ' . $this->confirmingServiceName . '? Jobs using it will stop reporting through it.';
    }

    /**
     * Cancel the deletion of the service.
     *
     * @return void
     */
    public function cancelDeleteService(): void {
        $this->confirmingServiceId = null;
        $this->confirmingServiceName = null;
        $this->confirmingServiceMessage = null;
    }

    /**
     * Perform the deletion of the service.
     *
     * @return void
     */
    public function performDeleteService(): void {
        if ($this->confirmingServiceId === null) {
            return;
        }

        $serviceId = $this->confirmingServiceId;
        $this->confirmingServiceId = null;
        $this->confirmingServiceName = null;
        $this->confirmingServiceMessage = null;

        $this->deleteService($serviceId);
    }

    /**
     * Render the service list component.
     *
     * @return View
     */
    public function render(): View {
        $services = Service::withCount(['horizonJobs', 'horizonFailedJobs'])->orderBy('name')->get();

        return \view('livewire.horizon.service-list', [
            'services' => $services,
        ])->layout('layouts.app', ['header' => 'Horizon Hub – Services']);
    }
}
