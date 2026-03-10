<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonFailedJob;
use App\Models\Service;
use App\Services\HorizonApiProxyService;
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
            'echo:horizonhub.dashboard,HorizonEvent' => 'refreshList',
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
     * @param HorizonApiProxyService $horizonApi
     * @return void
     */
    public function retryOne(int $id, HorizonApiProxyService $horizonApi): void {
        $job = HorizonFailedJob::with('service')->find($id);
        if (! $job || ! $job->service) {
            return;
        }
        $result = $horizonApi->retryJob($job->service, $job->job_uuid);
        if ($result['success']) {
            $this->dispatch('job-retried');
        } else {
            $message = $result['message'] ?? 'Retry failed';
            $this->dispatch('job-action-failed', message: $message);
        }
    }

    /**
     * Delete a failed job.
     *
     * @param int $id
     * @return void
     */
    public function deleteOne(int $id): void {}

    /**
     * Retry the selected failed jobs (one by one; result is granular).
     *
     * @param HorizonApiProxyService $horizonApi
     * @return void
     */
    public function retrySelected(HorizonApiProxyService $horizonApi): void {
        $jobs = HorizonFailedJob::with('service')->whereIn('id', $this->selectedIds)->get();
        $succeeded = 0;
        $failed = 0;
        $messages = [];
        foreach ($jobs as $job) {
            if (! $job->service) {
                $failed++;
                $messages[] = "Job {$job->id}: no service.";
                continue;
            }
            $result = $horizonApi->retryJob($job->service, $job->job_uuid);
            if ($result['success']) {
                $succeeded++;
            } else {
                $failed++;
                $msg = $result['message'] ?? 'Unknown error';
                $messages[] = "Job {$job->id}: " . $msg;
            }
        }
        $this->selectedIds = [];
        $this->dispatch('jobs-retried', succeeded: $succeeded, failed: $failed, messages: $messages);
    }

    /**
     * Delete the selected failed jobs.
     *
     * @return void
     */
    public function deleteSelected(): void {}

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
