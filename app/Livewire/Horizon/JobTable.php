<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;
use App\Services\HorizonApiProxyService;
use App\Services\HorizonSyncService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Contracts\View\View;

class JobTable extends Component {
    use WithPagination;

    /**
     * When set, scope jobs to this service and hide the service filter/column (service dashboard context).
     *
     * @var int|null
     */
    public ?int $serviceId = null;

    /**
     * Query string parameters.
     *
     * @var array<string, array<string, string>>
     */
    protected $queryString = [
        'serviceFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    /**
     * Service filter (only used when serviceId is null).
     *
     * @var string
     */
    public string $serviceFilter = '';

    /**
     * Status filter.
     *
     * @var string
     */
    public string $statusFilter = '';

    /**
     * Search term (queue, job name or UUID).
     *
     * @var string
     */
    public string $search = '';

    /**
     * When true, show "Retry jobs" and "Clean jobs" buttons that dispatch events to the parent.
     *
     * @var bool
     */
    public bool $showListActions = false;

    /**
     * Get the listeners for the component.
     *
     * @return array<string, string>
     */
    public function getListeners(): array {
        return [
            'echo:horizonhub.dashboard,HorizonEvent' => 'refreshJobs',
            'jobs-cleaned' => 'refreshJobs',
        ];
    }

    /**
     * Refresh and reset page.
     *
     * @return void
     */
    public function refreshJobs(): void {
        $this->resetPage();
    }

    /**
     * Retry a single job (by HorizonJob or HorizonFailedJob id).
     *
     * @param int $id
     * @param HorizonApiProxyService $horizonApi
     * @return void
     */
    public function retryJob(int $id, HorizonApiProxyService $horizonApi): void {
        $job = HorizonJob::with('service')->find($id) ?? HorizonFailedJob::with('service')->find($id);
        if (! $job || ! $job->service) {
            $this->dispatch('job-action-failed', message: 'Job or service not found.');
            return;
        }
        $result = $horizonApi->retryJob($job->service, $job->job_uuid);
        if ($result['success']) {
            $this->dispatch('job-retried');
        } else {
            $this->dispatch('job-action-failed', message: $result['message'] ?? 'Retry failed.');
        }
    }

    /**
     * Render the job table component.
     *
     * @param HorizonSyncService $sync
     * @return View
     */
    public function render(HorizonSyncService $sync): View {
        $sync->syncRecentJobs($this->serviceId);

        $query = HorizonJob::query()
            ->orderByDesc('created_at');

        if ($this->serviceId !== null) {
            $query->where('service_id', $this->serviceId);
        } else {
            $query->with('service');
            if ($this->serviceFilter !== '') {
                $query->where('service_id', (int) $this->serviceFilter);
            }
        }
        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }
        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('queue', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('job_uuid', 'like', "%{$search}%");
            });
        }

        $path = $this->serviceId !== null
            ? route('horizon.services.show', $this->serviceId)
            : route('horizon.index');
        $appendQuery = [];
        if ($this->serviceId === null && $this->serviceFilter !== '') {
            $appendQuery['serviceFilter'] = $this->serviceFilter;
        }
        if ($this->statusFilter !== '') {
            $appendQuery['statusFilter'] = $this->statusFilter;
        }
        if ($this->search !== '') {
            $appendQuery['search'] = $this->search;
        }

        $jobs = $query->paginate(20)->withPath($path)->appends($appendQuery);
        $services = $this->serviceId === null ? Service::orderBy('name')->get() : null;

        return \view('livewire.horizon.job-table', [
            'jobs' => $jobs,
            'services' => $services,
            'showServiceColumn' => $this->serviceId === null,
        ]);
    }
}
