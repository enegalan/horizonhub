<?php

namespace App\Http\Controllers\Horizon;

use App\Contracts\HorizonHubStore;
use App\Http\Controllers\Controller;
use App\Services\Services\ServiceFilterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     */
    public function index(Request $request, ServiceFilterService $serviceFilter, HorizonHubStore $store): View
    {
        return \view('horizon.dashboard.index', \array_merge([
            'header' => 'Dashboard',
            'defer' => true,
            'services' => $store->enabledServicesOrdered(),
        ], $serviceFilter->viewData($request)));
    }
}
