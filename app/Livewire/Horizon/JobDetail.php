<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Services\AgentProxyService;
use Livewire\Component;

class JobDetail extends Component {
    public int $jobId;

    public bool $showDeleteModal = false;

    public function mount(int $job): void {
        $this->jobId = $job;
    }

    public function getJobProperty(): HorizonJob|HorizonFailedJob|null {
        return HorizonFailedJob::with('service')->find($this->jobId)
            ?? HorizonJob::with('service')->find($this->jobId);
    }

    public function confirmDelete(): void {
        $job = $this->getJobProperty();
        if (! $job || ! $job->service) {
            return;
        }
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void {
        $this->showDeleteModal = false;
    }

    /**
     * Exception message for display (from job or from horizon_failed_jobs when viewing HorizonJob failed).
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

    public function retry(AgentProxyService $agent): void {
        $job = $this->getJobProperty();
        if (! $job || ! $job->service) {
            return;
        }
        $result = $agent->retryJob($job->service, $job->job_uuid);
        if ($result['success']) {
            $this->dispatch('job-retried');
            $this->js('if(window.toast)window.toast.success(' . json_encode('Job retried.') . ')');
        } else {
            $msg = $result['message'] ?? 'Retry failed';
            $this->dispatch('job-action-failed', message: $msg);
            $this->js('if(window.toast)window.toast.error(' . json_encode($msg) . ')');
        }
    }

    public function delete(AgentProxyService $agent): void {
        $job = $this->getJobProperty();
        if (! $job || ! $job->service) {
            return;
        }
        $this->showDeleteModal = false;
        $result = $agent->deleteJob($job->service, $job->job_uuid);
        if ($result['success']) {
            $this->dispatch('toast', type: 'success', message: 'Job deleted.');
            $this->js('if(window.toast)window.toast.success(' . json_encode('Job deleted.') . ')');
            $this->redirect(route('horizon.index'), navigate: true);
        } else {
            $msg = $result['message'] ?? 'Delete failed';
            $this->dispatch('job-action-failed', message: $msg);
            $this->js('if(window.toast)window.toast.error(' . json_encode($msg) . ')');
        }
    }

    public function render() {
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
