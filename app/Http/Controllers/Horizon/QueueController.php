<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\HorizonMetricsService;
use App\Support\Horizon\QueueNameNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class QueueController extends Controller {

    /**
     * The Horizon metrics service.
     *
     * @var HorizonMetricsService
     */
    private HorizonMetricsService $metrics;

    /**
     * Construct the queue controller.
     *
     * @param HorizonMetricsService $metrics
     */
    public function __construct(HorizonMetricsService $metrics) {
        $this->metrics = $metrics;
    }

    /**
     * Display the queue list.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View {
        $serviceFilter = (string) $request->query('service', '');

        $serviceIdFilter = $serviceFilter !== '' ? (int) $serviceFilter : null;

        $workloadRows = $this->metrics->getWorkloadData($serviceIdFilter);

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
            'serviceFilter' => $serviceFilter,
            'header' => 'Horizon Hub – Queues',
        ]);
    }


}
