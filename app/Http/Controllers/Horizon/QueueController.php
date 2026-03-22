<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use App\Services\HorizonMetricsService;
use App\Support\Horizon\QueueNameNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    /**
     * The Horizon metrics service.
     */
    private HorizonMetricsService $metrics;

    /**
     * Construct the queue controller.
     */
    public function __construct(HorizonMetricsService $metrics)
    {
        $this->metrics = $metrics;
    }

    /**
     * Display the queue list.
     */
    public function index(Request $request): View
    {
        $serviceFilterIds = ServiceRequest::existingIdsFromRequest($request, ['queue_services']);

        $workloadRows = $this->metrics->getWorkloadData($serviceFilterIds);

        if ($serviceFilterIds !== []) {
            $allowedServiceIds = \array_fill_keys($serviceFilterIds, true);
            $workloadRows = \array_values(\array_filter(
                $workloadRows,
                static function (array $row) use ($allowedServiceIds): bool {
                    return isset($allowedServiceIds[(int) ($row['service_id'] ?? 0)]);
                }
            ));
        }

        $serviceIds = \array_values(\array_unique(\array_map(
            static fn (array $row): int => (int) $row['service_id'],
            $workloadRows
        )));

        $servicesById = $serviceIds === []
            ? \collect()
            : Service::whereIn('id', $serviceIds)->get()->keyBy('id');

        $queues = \collect($workloadRows)
            ->map(function (array $row) use ($servicesById) {
                $queueRaw = $row['queue'] ?? '';
                $normalizedQueue = QueueNameNormalizer::normalize($queueRaw);
                $queueLabel = $normalizedQueue ?? $queueRaw;

                return (object) [
                    'service_id' => (int) $row['service_id'],
                    'queue' => $queueLabel,
                    'job_count' => (int) $row['jobs'],
                    'service' => $servicesById->get((int) $row['service_id']),
                ];
            });

        $queues = $queues->sortBy(fn ($r) => $r->queue)->values();

        $services = Service::orderBy('name')->get();
        $totalJobs = $queues->sum('job_count');

        return \view('horizon.queues.index', [
            'queueCount' => $queues->count(),
            'queues' => $queues,
            'services' => $services,
            'totalJobs' => $totalJobs,
            'serviceIds' => $serviceFilterIds,
            'header' => 'Horizon Hub – Queues',
        ]);
    }
}
