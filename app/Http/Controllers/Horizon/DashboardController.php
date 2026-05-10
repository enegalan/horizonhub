<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     */
    public function index(): View
    {
        return \view('horizon.dashboard.index', [
            'header' => 'Dashboard',
            'defer' => true,
        ]);
    }
}
