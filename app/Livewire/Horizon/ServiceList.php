<?php

namespace App\Livewire\Horizon;

use App\Models\Service;
use Illuminate\Support\Str;
use Livewire\Component;

class ServiceList extends Component {
    public string $name = '';

    public string $baseUrl = '';

    public ?string $newApiKey = null;

    public ?int $editingServiceId = null;

    public string $editName = '';

    public string $editBaseUrl = '';

    public ?int $confirmingServiceId = null;

    public ?string $confirmingServiceName = null;

    public ?string $confirmingServiceMessage = null;

    protected $rules = [
        'name' => 'required|string|max:255|unique:services,name',
        'baseUrl' => 'required|url',
    ];

    public function save(): void {
        $this->validate();
        $apiKey = Str::random(64);
        Service::create([
            'name' => $this->name,
            'api_key' => $apiKey,
            'base_url' => rtrim($this->baseUrl, '/'),
            'status' => 'online',
        ]);
        $this->newApiKey = $apiKey;
        $this->reset('name', 'baseUrl');
        $this->dispatch('service-created');
        $this->js('if(window.toast)window.toast.success(' . json_encode('Service registered.') . ')');
    }

    public function openEdit(int $serviceId): void {
        $service = Service::find($serviceId);
        if (! $service) {
            return;
        }
        $this->editingServiceId = $serviceId;
        $this->editName = $service->name;
        $this->editBaseUrl = $service->base_url ?? '';
    }

    public function cancelEdit(): void {
        $this->reset('editingServiceId', 'editName', 'editBaseUrl');
    }

    public function updateService(): void {
        $this->validate([
            'editName' => 'required|string|max:255|unique:services,name,' . (int) $this->editingServiceId,
            'editBaseUrl' => 'required|url',
        ]);
        $service = Service::find($this->editingServiceId);
        if (! $service) {
            return;
        }
        $service->update([
            'name' => $this->editName,
            'base_url' => rtrim($this->editBaseUrl, '/'),
        ]);
        $this->cancelEdit();
        $this->dispatch('toast', type: 'success', message: 'Service updated.');
        $this->dispatch('service-created');
        $this->js('if(window.toast)window.toast.success(' . json_encode('Service updated.') . ')');
    }

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
        $this->dispatch('service-created');
        $this->js('if(window.toast)window.toast.success(' . json_encode('Service deleted.') . ')');
    }

    public function confirmDeleteService(int $serviceId): void {
        $service = Service::find($serviceId);

        $this->confirmingServiceId = $serviceId;
        $this->confirmingServiceName = $service ? $service->name : ('Service #' . $serviceId);
        $name = $this->confirmingServiceName;
        $this->confirmingServiceMessage = 'Are you sure you want to delete service ' . $name . '? Jobs using it will stop reporting through it.';
    }

    public function cancelDeleteService(): void {
        $this->confirmingServiceId = null;
        $this->confirmingServiceName = null;
        $this->confirmingServiceMessage = null;
    }

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

    public function render() {
        $services = Service::withCount(['horizonJobs', 'horizonFailedJobs'])->orderBy('name')->get();

        return view('livewire.horizon.service-list', [
            'services' => $services,
        ])->layout('layouts.app', ['header' => 'Horizon Hub – Services']);
    }
}
