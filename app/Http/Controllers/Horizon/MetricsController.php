<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Services\ServiceFilterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    /**
     * Show the metrics dashboard.
     */
    public function index(Request $request): View
    {
        return \view('horizon.metrics.index', ServiceFilterService::indexViewData($request, [
            'services' => Service::enabledNamed(['id', 'name']),
            'header' => 'Metrics',
            'metricsChartData' => [],
        ]));
    }
}
