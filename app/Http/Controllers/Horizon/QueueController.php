<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\HorizonJob;
use App\Models\HorizonQueueState;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller {
    /**
     * Normalize a queue name to avoid duplicates caused by different connection prefixes.
     *
     * @param string|null $queue
     * @return string|null
     */
    private function normalizeQueueName(?string $queue): ?string {
        if ($queue === null || $queue === '') {
            return $queue;
        }

        if (\str_starts_with($queue, 'redis.')) {
            $suffix = \substr($queue, \strlen('redis.'));

            return $suffix !== '' ? $suffix : $queue;
        }

        return $queue;
    }

    /**
     * Display the queue list.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View {
        $serviceFilter = (string) $request->query('service', '');

        $query = HorizonJob::query()
            ->select('service_id', 'queue', DB::raw('count(*) as job_count'))
            ->groupBy('service_id', 'queue')
            ->with('service');

        if ($serviceFilter !== '') {
            $query->where('service_id', (int) $serviceFilter);
        }

        $queuesFromJobsRaw = $query->get();

        $aggregatedQueues = \collect();
        foreach ($queuesFromJobsRaw as $row) {
            $normalizedQueue = $this->normalizeQueueName($row->queue ?? '');
            $key = $row->service_id . '|' . $normalizedQueue;

            if (! $aggregatedQueues->has($key)) {
                $aggregatedQueues[$key] = (object) [
                    'service_id' => $row->service_id,
                    'queue' => $normalizedQueue,
                    'job_count' => 0,
                    'service' => $row->service,
                ];
            }

            $aggregatedQueues[$key]->job_count += $row->job_count;
        }

        $queuesFromJobs = $aggregatedQueues->values();
        $queueKeys = $queuesFromJobs->keyBy(fn ($r) => "{$r->service_id}|{$r->queue}")->keys();

        $statesQuery = HorizonQueueState::query();
        if ($serviceFilter !== '') {
            $statesQuery->where('service_id', (int) $serviceFilter);
        }
        $queueStatesAll = $statesQuery->get();

        foreach ($queueStatesAll as $state) {
            $normalizedQueue = $this->normalizeQueueName($state->queue);
            $key = "{$state->service_id}|{$normalizedQueue}";
            if ($queueKeys->contains($key)) {
                continue;
            }
            $queueKeys->push($key);
            $queuesFromJobs->push((object) [
                'service_id' => $state->service_id,
                'queue' => $normalizedQueue,
                'job_count' => 0,
                'service' => Service::find($state->service_id),
            ]);
        }

        $queues = $queuesFromJobs->sortBy(fn ($r) => $r->queue)->values();
        $services = Service::orderBy('name')->get();
        $totalJobs = $queues->sum('job_count');

        $serviceIds = $queues->pluck('service_id')->unique()->filter()->values()->all();
        $queueStates = empty($serviceIds)
            ? \collect()
            : HorizonQueueState::whereIn('service_id', $serviceIds)
                ->get()
                ->map(function ($state) {
                    $state->queue = $this->normalizeQueueName($state->queue);

                    return $state;
                })
                ->keyBy(fn ($s) => "$s->service_id|$s->queue");

        return \view('horizon.queues.index', [
            'queueCount' => $queues->count(),
            'queues' => $queues,
            'queueStates' => $queueStates,
            'services' => $services,
            'totalJobs' => $totalJobs,
            'serviceFilter' => $serviceFilter,
            'header' => 'Horizon Hub – Queues',
        ]);
    }
}
