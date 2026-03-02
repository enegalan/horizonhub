<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;
use Livewire\Component;
use Livewire\WithPagination;

class ServiceDashboard extends Component {
    use WithPagination;

    public Service $service;

    public function getListeners(): array {
        return [
            'echo:horizon-hub.dashboard,HorizonEvent' => 'refreshJobs',
        ];
    }

    public function refreshJobs(): void {
        $this->resetPage();
    }

    public function render() {
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

        $supervisors = $this->service->horizonSupervisorStates()
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
