<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use Illuminate\Contracts\View\View;

class MetricsController extends Controller
{
    /**
     * Show the metrics dashboard.
     */
    public function index(ServiceRequest $request): View
    {
        $services = Service::query()->enabled()->orderBy('name')->get(['id', 'name']);
        $serviceIds = ServiceRequest::existingIdsFromRequest($request, ['service_id']);

        return \view('horizon.metrics.index', [
            'services' => $services,
            'serviceIds' => $serviceIds,
            'header' => 'Metrics',
            'defer' => true,
            'metricsChartData' => [],
        ]);
    }
}
