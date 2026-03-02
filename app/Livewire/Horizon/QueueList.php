<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonJob;
use App\Models\HorizonQueueState;
use App\Models\Service;
use App\Services\AgentProxyService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class QueueList extends Component {
    public string $serviceFilter = '';

    public function pauseQueue(AgentProxyService $agent, int $serviceId, string $queueName): void {
        $service = Service::find($serviceId);
        if (! $service) {
            return;
        }
        $result = $agent->pauseQueue($service, $queueName);
        if ($result['success'] ?? false) {
            HorizonQueueState::updateOrCreate(
                ['service_id' => $serviceId, 'queue' => $queueName],
                ['is_paused' => true]
            );
        }
        $this->dispatch('queue-updated');
    }

    public function resumeQueue(AgentProxyService $agent, int $serviceId, string $queueName): void {
        $service = Service::find($serviceId);
        if (! $service) {
            return;
        }
        $result = $agent->resumeQueue($service, $queueName);
        if ($result['success'] ?? false) {
            HorizonQueueState::updateOrCreate(
                ['service_id' => $serviceId, 'queue' => $queueName],
                ['is_paused' => false]
            );
        }
        $this->dispatch('queue-updated');
    }

    public function render() {
        $query = HorizonJob::query()
            ->select('service_id', 'queue', DB::raw('count(*) as job_count'))
            ->groupBy('service_id', 'queue')
            ->with('service');

        if ($this->serviceFilter !== '') {
            $query->where('service_id', (int) $this->serviceFilter);
        }

        $queues = $query->get();
        $services = Service::orderBy('name')->get();
        $totalJobs = $queues->sum('job_count');

        $serviceIds = $queues->pluck('service_id')->unique()->filter()->values()->all();
        $queueStates = empty($serviceIds)
            ? collect()
            : HorizonQueueState::whereIn('service_id', $serviceIds)
                ->get()
                ->keyBy(fn ($s) => $s->service_id . '|' . $s->queue);

        return view('livewire.horizon.queue-list', [
            'queueCount' => $queues->count(),
            'queues' => $queues,
            'queueStates' => $queueStates,
            'services' => $services,
            'totalJobs' => $totalJobs,
        ])->layout('layouts.app', ['header' => 'Horizon Hub – Queues']);
    }
}
