<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Horizon\ServiceFilterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    /**
     * Show the metrics dashboard.
     */
    public function index(Request $request, ServiceFilterService $serviceFilter): View
    {
        return \view('horizon.metrics.index', \array_merge([
            'services' => Service::query()->enabled()->orderBy('name')->get(['id', 'name']),
            'serviceIds' => $serviceFilter->resolveServiceIds($request),
            'header' => 'Metrics',
            'defer' => true,
            'metricsChartData' => [],
        ], $serviceFilter->viewData($request)));
    }
}
