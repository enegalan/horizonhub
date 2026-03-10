<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Services\HorizonApiProxyService;
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
        $failed = HorizonFailedJob::with('service')->find($this->jobId);
        if ($failed) {
            $matchingJob = null;
            if ($failed->service_id && $failed->job_uuid) {
                $matchingJob = HorizonJob::with('service')
                    ->where('service_id', $failed->service_id)
                    ->where('job_uuid', $failed->job_uuid)
                    ->first();
            }

            return $matchingJob ?? $failed;
        }

        return HorizonJob::with('service')->find($this->jobId);
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
     * @param HorizonApiProxyService $horizonApi
     * @return void
     */
    public function retry(HorizonApiProxyService $horizonApi): void {
        $job = $this->getJobProperty();
        if (! $job || ! $job->service) {
            return;
        }
        $result = $horizonApi->retryJob($job->service, $job->job_uuid);
        if ($result['success']) {
            $this->dispatch('job-retried');
        } else {
            $msg = $result['message'] ?? 'Retry failed';
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
            \abort(404);
        }
        return \view('livewire.horizon.job-detail', [
            'job' => $job,
            'exception' => $this->getExceptionProperty(),
        ])->layout('layouts.app', [
            'header' => 'Job: ' . ($job->name ?? $job->job_uuid),
        ]);
    }
}
