<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Services\Horizon\DashboardDataService;
use App\Services\Horizon\HorizonApiProxyService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /**
     * The dashboard data service.
     */
    private DashboardDataService $dashboardData;

    /**
     * The constructor.
     */
    public function __construct(DashboardDataService $dashboardData)
    {
        $this->dashboardData = $dashboardData;
    }

    /**
     * Show the Horizon Hub dashboard.
     */
    public function index(HorizonApiProxyService $horizonApi): View
    {
        return \view(
            'horizon.dashboard.index',
            \array_merge($this->dashboardData->build($horizonApi), [
                'header' => 'Dashboard',
            ]),
        );
    }
}
