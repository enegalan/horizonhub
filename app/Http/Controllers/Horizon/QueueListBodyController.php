<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\HorizonJob;
use App\Models\HorizonQueueState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QueueListBodyController extends Controller {
    public function __invoke(Request $request): View {
        $serviceFilter = (string) $request->query('service_filter', '');

        $query = HorizonJob::query()
            ->select('service_id', 'queue', DB::raw('count(*) as job_count'))
            ->groupBy('service_id', 'queue')
            ->with('service');

        if ($serviceFilter !== '') {
            $query->where('service_id', (int) $serviceFilter);
        }

        $queues = $query->get();
        $serviceIds = $queues->pluck('service_id')->unique()->filter()->values()->all();
        $queueStates = empty($serviceIds)
            ? collect()
            : HorizonQueueState::whereIn('service_id', $serviceIds)
                ->get()
                ->keyBy(fn ($s) => $s->service_id . '|' . $s->queue);

        return view('livewire.horizon.partials.queue-list-tbody', [
            'queues' => $queues,
            'queueStates' => $queueStates,
        ]);
    }
}
