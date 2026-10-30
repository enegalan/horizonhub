<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     */
    public function index(): View
    {
        return \view('pages.dashboard.index', [
            'defer' => true,
            'hasEnabledServices' => Service::query()->enabled()->exists(),
            'header' => 'Dashboard',
            'services' => Service::enabledNamed(),
        ]);
    }
}
