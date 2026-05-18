<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Services\Horizon\ServiceFilterService;
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
        ], $serviceFilter->viewData($request)));
    }
}
