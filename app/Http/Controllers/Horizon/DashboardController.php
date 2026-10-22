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
    public function index(Request $request): View
    {
        return \view('pages.dashboard.index', ServiceFilterService::indexViewData($request, [
            'header' => 'Dashboard',
            'services' => Service::enabledNamed(),
        ]));
    }
}
