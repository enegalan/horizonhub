<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use App\Services\Horizon\MetricsDashboardDataService;
use Illuminate\Contracts\View\View;

class MetricsController extends Controller
{
    public function __construct(
        private MetricsDashboardDataService $metricsDashboard,
    ) {}

    /**
     * Show the metrics dashboard.
     */
    public function index(ServiceRequest $request): View
    {
        $services = Service::orderBy('name')->get(['id', 'name']);
        $serviceIds = $request->getServiceIds();

        return \view('horizon.metrics.index', \array_merge($this->metricsDashboard->build($serviceIds), [
            'services' => $services,
            'serviceIds' => $serviceIds,
            'header' => 'Metrics',
        ]));
    }
}
