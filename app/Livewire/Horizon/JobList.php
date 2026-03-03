<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;
use Livewire\Component;
use Livewire\WithPagination;

class JobList extends Component {
    use WithPagination;

    protected $queryString = array(
        'serviceFilter' => array('except' => ''),
        'queueFilter' => array('except' => ''),
        'statusFilter' => array('except' => ''),
        'jobTypeFilter' => array('except' => ''),
    );

    public string $serviceFilter = '';

    public string $queueFilter = '';

    public string $statusFilter = '';

    public string $jobTypeFilter = '';

    public bool $showCleanModal = false;

    public int $cleanStep = 1;

    public ?string $cleanServiceId = null;

    public ?string $cleanStatus = null;

    public ?string $cleanJobType = null;

    public function getListeners(): array {
        return [
            'echo:horizon-hub.dashboard,HorizonEvent' => 'refreshJobs',
        ];
    }

    public function refreshJobs(): void {
        $this->resetPage();
    }

    public function openCleanModal(): void {
        $this->showCleanModal = true;
        $this->cleanStep = 1;
        $this->cleanServiceId = null;
        $this->cleanStatus = null;
        $this->cleanJobType = null;
    }

    public function closeCleanModal(): void {
        $this->showCleanModal = false;
        $this->cleanStep = 1;
    }

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

    public function confirmCleanJobs(): void {
        if ($this->getCleanCountProperty() === 0) {
            return;
        }
        $this->cleanStep = 2;
    }

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

    public function render() {
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
