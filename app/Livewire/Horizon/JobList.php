<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;
use App\Services\HorizonApiProxyService;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class JobList extends Component {
    /**
     * Whether to show the clean modal.
     *
     * @var bool
     */
    public bool $showCleanModal = false;

    /**
     * The clean step.
     *
     * @var int
     */
    public int $cleanStep = 1;

    /**
     * The clean service ID.
     *
     * @var string|null
     */
    public ?string $cleanServiceId = null;

    /**
     * The clean status.
     *
     * @var string|null
     */
    public ?string $cleanStatus = null;

    /**
     * The clean job type.
     *
     * @var string|null
     */
    public ?string $cleanJobType = null;

    /**
     * Whether the retry-jobs modal is open.
     *
     * @var bool
     */
    public bool $showRetryModal = false;

    /**
     * Service filter inside the retry modal (empty = all services).
     *
     * @var string
     */
    public string $retryModalServiceFilter = '';

    /**
     * Search term in the retry modal (queue, job name or UUID).
     *
     * @var string
     */
    public string $retryModalSearch = '';

    /**
     * Start date for failed_at filter in the retry modal (Y-m-d).
     *
     * @var string
     */
    public string $retryModalDateFrom = '';

    /**
     * End date for failed_at filter in the retry modal (Y-m-d).
     *
     * @var string
     */
    public string $retryModalDateTo = '';

    /**
     * Selected failed job IDs in the retry modal (for group retry).
     *
     * @var array<int>
     */
    public array $retryModalSelectedIds = [];

    /**
     * Cached list of failed jobs for the retry modal (only refreshed when modal opens or filters change).
     * Each item: id, service_name, queue, name, failed_at_formatted, has_service.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $retryModalFailedJobsList = [];

    /**
     * Get the listeners for the job list component.
     *
     * @return array<string, string>
     */
    public function getListeners(): array {
        return [
            'echo:horizonhub.dashboard,HorizonEvent' => 'refreshJobs',
            'openRetryModal' => 'openRetryModal',
            'openCleanModal' => 'openCleanModal',
        ];
    }

    /**
     * Refresh the jobs (no-op; child JobTable handles pagination).
     *
     * @return void
     */
    public function refreshJobs(): void {
        // JobTable component listens and resets its own page.
    }

    /**
     * Open the clean modal.
     *
     * @return void
     */
    public function openCleanModal(): void {
        $this->showCleanModal = true;
        $this->cleanStep = 1;
        $this->cleanServiceId = null;
        $this->cleanStatus = null;
        $this->cleanJobType = null;
    }

    /**
     * Close the clean modal.
     *
     * @return void
     */
    public function closeCleanModal(): void {
        $this->showCleanModal = false;
        $this->cleanStep = 1;
    }

    /**
     * Open the retry-jobs modal.
     *
     * @return void
     */
    public function openRetryModal(): void {
        $this->showRetryModal = true;
        $this->retryModalSelectedIds = [];
        $this->retryModalSearch = '';
        $this->retryModalDateFrom = '';
        $this->retryModalDateTo = '';
        $this->refreshRetryModalList();
    }

    /**
     * Close the retry-jobs modal.
     *
     * @return void
     */
    public function closeRetryModal(): void {
        $this->showRetryModal = false;
        $this->retryModalSelectedIds = [];
        $this->retryModalFailedJobsList = [];
    }

    /**
     * Refresh the cached failed jobs list (runs query once; only call when modal opens or filters change).
     *
     * @return void
     */
    public function refreshRetryModalList(): void {
        if (! $this->showRetryModal) {
            $this->retryModalFailedJobsList = [];
            return;
        }
        $query = HorizonFailedJob::with('service')->orderByDesc('failed_at')->limit(50);
        if ($this->retryModalServiceFilter !== '') {
            $query->where('service_id', (int) $this->retryModalServiceFilter);
        }
        $search = \trim($this->retryModalSearch);
        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('queue', 'like', $term)
                    ->orWhere('job_uuid', 'like', $term)
                    ->orWhere('payload->displayName', 'like', $term);
            });
        }
        if ($this->retryModalDateFrom !== '') {
            $query->whereDate('failed_at', '>=', $this->retryModalDateFrom);
        }
        if ($this->retryModalDateTo !== '') {
            $query->whereDate('failed_at', '<=', $this->retryModalDateTo);
        }
        $rows = $query->get();
        $this->retryModalFailedJobsList = $rows->map(function (HorizonFailedJob $j): array {
            return [
                'id' => $j->id,
                'service_name' => $j->service?->name,
                'queue' => $j->queue,
                'name' => $j->name ?? $j->job_uuid,
                'failed_at_formatted' => $j->failed_at?->format('Y-m-d H:i'),
                'failed_at_iso' => $j->failed_at?->toIso8601String(),
                'has_service' => $j->service !== null,
            ];
        })->all();
    }

    public function updatedRetryModalServiceFilter(): void {
        if ($this->showRetryModal) {
            $this->refreshRetryModalList();
        }
    }

    public function updatedRetryModalSearch(): void {
        if ($this->showRetryModal) {
            $this->refreshRetryModalList();
        }
    }

    public function updatedRetryModalDateFrom(): void {
        if ($this->showRetryModal) {
            $this->refreshRetryModalList();
        }
    }

    public function updatedRetryModalDateTo(): void {
        if ($this->showRetryModal) {
            $this->refreshRetryModalList();
        }
    }

    /**
     * Select all failed jobs currently shown in the retry modal.
     *
     * @return void
     */
    public function selectAllInRetryModal(): void {
        $this->retryModalSelectedIds = array_column($this->retryModalFailedJobsList, 'id');
    }

    /**
     * Clear selection in the retry modal.
     *
     * @return void
     */
    public function clearRetryModalSelection(): void {
        $this->retryModalSelectedIds = [];
    }

    /**
     * Retry the selected failed jobs in the modal (one by one).
     * Accepts optional $ids from client (Alpine) to avoid round-trips on each checkbox change.
     *
     * @param HorizonApiProxyService $horizonApi
     * @param array<int> $ids
     * @return void
     */
    public function retrySelectedInModal(HorizonApiProxyService $horizonApi, array $ids = []): void {
        $idList = $ids !== [] ? $ids : $this->retryModalSelectedIds;
        $jobs = HorizonFailedJob::with('service')->whereIn('id', $idList)->get();
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
                $messages[] = "Job {$job->id}: " . ($result['message'] ?? 'Unknown error');
            }
        }
        $this->closeRetryModal();
        $this->dispatch('jobs-retried', succeeded: $succeeded, failed: $failed, messages: $messages);
    }

    /**
     * Get the clean count property.
     *
     * @return int
     */
    public function getCleanCountProperty(): int {
        $query = HorizonJob::query();
        if ($this->cleanServiceId !== null && $this->cleanServiceId !== '') {
            $query->where('service_id', (int) $this->cleanServiceId);
        }
        if ($this->cleanStatus !== null && $this->cleanStatus !== '') {
            $query->where('status', $this->cleanStatus);
        }
        if ($this->cleanJobType !== null && $this->cleanJobType !== '') {
            $query->where('name', 'like', '%' . $this->cleanJobType . '%');
        }
        return $query->count();
    }

    /**
     * Confirm the clean jobs.
     *
     * @return void
     */
    public function confirmCleanJobs(): void {
        if ($this->getCleanCountProperty() === 0) {
            return;
        }
        $this->cleanStep = 2;
    }

    /**
     * Run the clean jobs.
     *
     * @return void
     */
    public function runCleanJobs(): void {
        $count = $this->getCleanCountProperty();
        $query = HorizonJob::query();
        if ($this->cleanServiceId !== null && $this->cleanServiceId !== '') {
            $query->where('service_id', (int) $this->cleanServiceId);
        }
        if ($this->cleanStatus !== null && $this->cleanStatus !== '') {
            $query->where('status', $this->cleanStatus);
        }
        if ($this->cleanJobType !== null && $this->cleanJobType !== '') {
            $query->where('name', 'like', '%' . $this->cleanJobType . '%');
        }
        $query->delete();

        $failedQuery = HorizonFailedJob::query();
        if ($this->cleanServiceId !== null && $this->cleanServiceId !== '') {
            $failedQuery->where('service_id', (int) $this->cleanServiceId);
        }
        if ($this->cleanStatus === null || $this->cleanStatus === '' || $this->cleanStatus === 'failed') {
            $failedCount = $failedQuery->count();
            $failedQuery->delete();
            $count += $failedCount;
        }

        $this->closeCleanModal();
        $this->dispatch('jobs-cleaned');
        $msg = "$count job(s) cleaned.";
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    /**
     * Render the job list component.
     *
     * @return View
     */
    public function render(): View {
        $services = Service::orderBy('name')->get();

        return \view('livewire.horizon.job-list', [
            'services' => $services,
        ])->layout('layouts.app', [
            'header' => 'Horizon Hub – Jobs',
        ]);
    }
}
