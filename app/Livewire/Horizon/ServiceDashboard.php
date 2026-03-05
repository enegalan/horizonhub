<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Contracts\View\View;

class ServiceDashboard extends Component {
    use WithPagination;

    /**
     * The service.
     *
     * @var Service
     */
    public Service $service;

    /**
     * Minutes without signal after which a supervisor is considered stale (orange).
     *
     * @var int
     */
    public int $stale_minutes = 5;

    /**
     * Minutes without signal after which a supervisor is considered dead (red / removed).
     *
     * @var int
     */
    public int $dead_minutes = 15;

    /**
     * Mount the component.
     *
     * @return void
     */
    public function mount(): void {
        $this->stale_minutes = config('horizonhub.stale_minutes');
        $this->dead_minutes = config('horizonhub.dead_service_minutes');
    }

    /**
     * Get the listeners for the service dashboard component.
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
     * Render the service dashboard component.
     *
     * @return View
     */
    public function render(): View {
        $serviceId = $this->service->id;
        $jobs = HorizonJob::where('service_id', $serviceId)
            ->orderByDesc('created_at')
            ->paginate(20);

        $jobsPastMinute = HorizonJob::where('service_id', $serviceId)
            ->where('status', 'processed')
            ->where('processed_at', '>=', now()->subMinute())
            ->count();
        $jobsPastHour = HorizonJob::where('service_id', $serviceId)
            ->where('status', 'processed')
            ->where('processed_at', '>=', now()->subHour())
            ->count();
        $failedPastSevenDays = HorizonFailedJob::where('service_id', $serviceId)
            ->where('failed_at', '>=', now()->subDays(7))
            ->count();
        $processedPast24Hours = HorizonJob::where('service_id', $serviceId)
            ->where('status', 'processed')
            ->where('processed_at', '>=', now()->subDay())
            ->count();

        $dead_threshold = now()->subMinutes($this->dead_minutes);
        $supervisors = $this->service->horizonSupervisorStates()
            ->where('last_seen_at', '>=', $dead_threshold)
            ->orderBy('name')
            ->get();

        return view('livewire.horizon.service-dashboard', [
            'jobs' => $jobs,
            'jobsPastMinute' => $jobsPastMinute,
            'jobsPastHour' => $jobsPastHour,
            'failedPastSevenDays' => $failedPastSevenDays,
            'processedPast24Hours' => $processedPast24Hours,
            'supervisors' => $supervisors,
        ])->layout('layouts.app', [
            'header' => 'Horizon Hub – ' . $this->service->name,
        ]);
    }
}
