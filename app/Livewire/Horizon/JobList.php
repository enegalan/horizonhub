<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Contracts\View\View;

class JobList extends Component {
    use WithPagination;

    /**
     * The query string parameters.
     *
     * @var array<string, array<string, string>>
     */
    protected $queryString = array(
        'serviceFilter' => array('except' => ''),
        'queueFilter' => array('except' => ''),
        'statusFilter' => array('except' => ''),
        'jobTypeFilter' => array('except' => ''),
    );

    /**
     * The service filter.
     *
     * @var string
     */
    public string $serviceFilter = '';

    /**
     * The queue filter.
     *
     * @var string
     */
    public string $queueFilter = '';

    /**
     * The status filter.
     *
     * @var string
     */
    public string $statusFilter = '';

    /**
     * The job type filter.
     *
     * @var string
     */
    public string $jobTypeFilter = '';

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
     * Get the listeners for the job list component.
     *
     * @return array<string, string>
     */
    public function getListeners(): array {
        return [
            'echo:horizon-hub.dashboard,HorizonEvent' => 'refreshJobs',
        ];
    }

    /**
     * Refresh the jobs.
     *
     * @return void
     */
    public function refreshJobs(): void {
        $this->resetPage();
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
        $this->resetPage();
        $msg = $count . ' job(s) cleaned.';
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    /**
     * Render the job list component.
     *
     * @return View
     */
    public function render(): View {
        $query = HorizonJob::with('service')
            ->orderByDesc('created_at');

        if ($this->serviceFilter) {
            $query->where('service_id', $this->serviceFilter);
        }
        if ($this->queueFilter) {
            $query->where('queue', $this->queueFilter);
        }
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->jobTypeFilter) {
            $query->where('name', 'like', '%' . $this->jobTypeFilter . '%');
        }

        $appendQuery = array();
        if ($this->serviceFilter !== '') {
            $appendQuery['serviceFilter'] = $this->serviceFilter;
        }
        if ($this->queueFilter !== '') {
            $appendQuery['queueFilter'] = $this->queueFilter;
        }
        if ($this->statusFilter !== '') {
            $appendQuery['statusFilter'] = $this->statusFilter;
        }
        if ($this->jobTypeFilter !== '') {
            $appendQuery['jobTypeFilter'] = $this->jobTypeFilter;
        }

        $jobs = $query->paginate(20)->appends($appendQuery);
        $services = Service::orderBy('name')->get();

        return view('livewire.horizon.job-list', [
            'jobs' => $jobs,
            'services' => $services,
        ])->layout('layouts.app', [
            'header' => 'Horizon Hub – Jobs',
        ]);
    }
}
