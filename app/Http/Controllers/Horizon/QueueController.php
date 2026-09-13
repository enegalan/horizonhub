<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Services\ServiceFilterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    /**
     * Display the queue list.
     */
    public function index(Request $request): View
    {
        return \view('horizon.queues.index', ServiceFilterService::indexViewData($request, [
            'queueCount' => 0,
            'queues' => \collect(),
            'services' => Service::enabledNamed(),
            'totalJobs' => 0,
            'header' => 'Queues',
        ]));
    }
}
