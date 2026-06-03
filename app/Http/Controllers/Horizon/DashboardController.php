<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Services\ServiceFilterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     */
    public function index(Request $request, ServiceFilterService $serviceFilter): View
    {
        return \view('horizon.dashboard.index', \array_merge([
            'header' => 'Dashboard',
            'defer' => true,
            'services' => Service::enabled()->orderBy('name')->get(),
        ], $serviceFilter->viewData($request)));
    }
}
