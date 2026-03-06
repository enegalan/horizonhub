<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonFailedJob;
use App\Models\Service;
use App\Services\AgentProxyService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Contracts\View\View;

class FailedJobList extends Component {
    use WithPagination;

    /**
     * The service filter to apply to the failed jobs.
     *
     * @var string|null
     */
    public ?string $serviceFilter = null;

    /**
     * The IDs of the selected failed jobs.
     *
     * @var array<int>
     */
    public array $selectedIds = [];

    /**
     * Get the listeners for the failed job list component.
     *
     * @return array<string, string>
     */
    public function getListeners(): array {
        return [
            'echo:horizon-hub.dashboard,HorizonEvent' => 'refreshList',
        ];
    }

    /**
     * Refresh the failed job list.
     *
     * @return void
     */
    public function refreshList(): void {
        $this->resetPage();
    }

    /**
     * Retry a failed job.
     *
     * @param int $id
     * @param AgentProxyService $agent
     * @return void
     */
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

    /**
     * Delete a failed job.
     *
     * @param int $id
     * @param AgentProxyService $agent
     * @return void
     */
    public function deleteOne(int $id, AgentProxyService $agent): void {
        $job = HorizonFailedJob::with('service')->find($id);
        if (! $job || ! $job->service) {
            return;
        }
        $agent->deleteJob($job->service, $job->job_uuid);
        $this->refreshList();
    }

    /**
     * Retry the selected failed jobs.
     *
     * @param AgentProxyService $agent
     * @return void
     */
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

    /**
     * Delete the selected failed jobs.
     *
     * @param AgentProxyService $agent
     * @return void
     */
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

    /**
     * Render the failed job list component.
     *
     * @return View
     */
    public function render(): View {
        $query = HorizonFailedJob::with('service')->orderByDesc('failed_at');
        if ($this->serviceFilter) {
            $query->where('service_id', $this->serviceFilter);
        }
        $failedJobs = $query->paginate(20);
        $services = Service::orderBy('name')->get();

        return \view('livewire.horizon.failed-job-list', [
            'failedJobs' => $failedJobs,
            'services' => $services,
        ])->layout('layouts.app', ['header' => 'Horizon Hub – Failed Jobs']);
    }
}
