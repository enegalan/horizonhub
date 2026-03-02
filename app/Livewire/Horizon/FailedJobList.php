<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonFailedJob;
use App\Models\Service;
use App\Services\AgentProxyService;
use Livewire\Component;
use Livewire\WithPagination;

class FailedJobList extends Component {
    use WithPagination;

    public ?string $serviceFilter = null;
    public array $selectedIds = [];

    public function getListeners(): array {
        return [
            'echo:horizon-hub.dashboard,HorizonEvent' => 'refreshList',
        ];
    }

    public function refreshList(): void {
        $this->resetPage();
    }

    public function retryOne(int $id, AgentProxyService $agent): void {
        $job = HorizonFailedJob::with('service')->find($id);
        if (! $job || ! $job->service) {
            return;
        }
        $result = $agent->retryJob($job->service, $job->job_uuid);
        if ($result['success']) {
            $this->dispatch('job-retried');
        }
    }

    public function deleteOne(int $id, AgentProxyService $agent): void {
        $job = HorizonFailedJob::with('service')->find($id);
        if (! $job || ! $job->service) {
            return;
        }
        $agent->deleteJob($job->service, $job->job_uuid);
        $this->refreshList();
    }

    public function retrySelected(AgentProxyService $agent): void {
        $jobs = HorizonFailedJob::with('service')->whereIn('id', $this->selectedIds)->get();
        foreach ($jobs as $job) {
            if ($job->service) {
                $agent->retryJob($job->service, $job->job_uuid);
            }
        }
        $this->selectedIds = [];
        $this->dispatch('jobs-retried');
    }

    public function deleteSelected(AgentProxyService $agent): void {
        $jobs = HorizonFailedJob::with('service')->whereIn('id', $this->selectedIds)->get();
        foreach ($jobs as $job) {
            if ($job->service) {
                $agent->deleteJob($job->service, $job->job_uuid);
            }
        }
        $this->selectedIds = [];
        $this->refreshList();
    }

    public function render() {
        $query = HorizonFailedJob::with('service')->orderByDesc('failed_at');
        if ($this->serviceFilter) {
            $query->where('service_id', $this->serviceFilter);
        }
        $failedJobs = $query->paginate(20);
        $services = Service::orderBy('name')->get();

        return view('livewire.horizon.failed-job-list', [
            'failedJobs' => $failedJobs,
            'services' => $services,
        ])->layout('layouts.app', ['header' => 'Horizon Hub – Failed Jobs']);
    }
}
