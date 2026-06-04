<?php

namespace App\Http\Controllers\Horizon;

use App\Contracts\HorizonHubStore;
use App\Http\Controllers\Controller;
use App\Services\Services\ServiceFilterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    /**
     * Show the metrics dashboard.
     */
    public function index(Request $request, ServiceFilterService $serviceFilter, HorizonHubStore $store): View
    {
        return \view('horizon.metrics.index', \array_merge([
            'services' => $store->enabledServicesOrdered(),
            'header' => 'Metrics',
            'defer' => true,
            'metricsChartData' => [],
        ], $serviceFilter->viewData($request)));
    }
}
