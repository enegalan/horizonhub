<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use App\Services\Horizon\HorizonMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    /**
     * The Horizon metrics service.
     */
    private HorizonMetricsService $metrics;

    /**
     * The constructor.
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
        $queues = $this->metrics->buildQueuesCollectionForServiceFilter($serviceFilterIds);

        return \view('horizon.queues.index', [
            'queueCount' => $queues->count(),
            'queues' => $queues,
            'services' => Service::orderBy('name')->get(),
            'totalJobs' => $queues->sum('job_count'),
            'serviceIds' => $serviceFilterIds,
            'header' => 'Queues',
        ]);
    }
}
