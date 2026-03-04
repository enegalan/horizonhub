<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Services\AgentProxyService;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class JobDetail extends Component {
    /**
     * The ID of the job.
     *
     * @var int
     */
    public int $jobId;

    /**
     * Whether to show the delete modal.
     *
     * @var bool
     */
    public bool $showDeleteModal = false;

    /**
     * Mount the job detail component.
     *
     * @param int $job
     * @return void
     */
    public function mount(int $job): void {
        $this->jobId = $job;
    }

    /**
     * Get the job property.
     *
     * @return HorizonJob|HorizonFailedJob|null
     */
    public function getJobProperty(): HorizonJob|HorizonFailedJob|null {
        return HorizonFailedJob::with('service')->find($this->jobId)
            ?? HorizonJob::with('service')->find($this->jobId);
    }

    /**
     * Confirm the deletion of a job.
     *
     * @return void
     */
    public function confirmDelete(): void {
        $job = $this->getJobProperty();
        if (! $job || ! $job->service) {
            return;
        }
        $this->showDeleteModal = true;
    }

    /**
     * Cancel the deletion of a job.
     *
     * @return void
     */
    public function cancelDelete(): void {
        $this->showDeleteModal = false;
    }

    /**
     * Exception message for display (from job or from horizon failed jobs when viewing HorizonJob failed).
     *
     * @return string|null
     */
    public function getExceptionProperty(): ?string {
        $job = $this->getJobProperty();
        if (! $job) {
            return null;
        }
        if (isset($job->exception) && (string) $job->exception !== '') {
            return (string) $job->exception;
        }
        if ($job instanceof HorizonJob && $job->status === 'failed' && $job->service_id && $job->job_uuid) {
            return HorizonFailedJob::where('service_id', $job->service_id)
                ->where('job_uuid', $job->job_uuid)
                ->value('exception');
        }
        return null;
    }

    /**
     * Retry a job.
     *
     * @param AgentProxyService $agent
     * @return void
     */
    public function retry(AgentProxyService $agent): void {
        $job = $this->getJobProperty();
        if (! $job || ! $job->service) {
            return;
        }
        $result = $agent->retryJob($job->service, $job->job_uuid);
        if ($result['success']) {
            $this->dispatch('job-retried');
        } else {
            $msg = $result['message'] ?? 'Retry failed';
            $this->dispatch('job-action-failed', message: $msg);
        }
    }

    /**
     * Delete a job.
     *
     * @param AgentProxyService $agent
     * @return void
     */
    public function delete(AgentProxyService $agent): void {
        $job = $this->getJobProperty();
        if (! $job || ! $job->service) {
            return;
        }
        $this->showDeleteModal = false;
        $result = $agent->deleteJob($job->service, $job->job_uuid);
        if ($result['success']) {
            if ($job instanceof HorizonJob) {
                HorizonFailedJob::where('service_id', $job->service_id)
                    ->where('job_uuid', $job->job_uuid)
                    ->delete();
            }
            if ($job instanceof HorizonFailedJob) {
                HorizonJob::where('service_id', $job->service_id)
                    ->where('job_uuid', $job->job_uuid)
                    ->delete();
            }
            $job->delete();
            $this->dispatch('toast', type: 'success', message: 'Job deleted.');
            $this->redirect(route('horizon.index'), navigate: true);
        } else {
            $msg = $result['message'] ?? 'Delete failed';
            $this->dispatch('job-action-failed', message: $msg);
        }
    }

    /**
     * Render the job detail component.
     *
     * @return View
     */
    public function render(): View {
        $job = $this->getJobProperty();
        if (! $job) {
            abort(404);
        }
        return view('livewire.horizon.job-detail', [
            'job' => $job,
            'exception' => $this->getExceptionProperty(),
        ])->layout('layouts.app', [
            'header' => 'Job: ' . ($job->name ?? $job->job_uuid),
        ]);
    }
}
