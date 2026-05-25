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
    public function index(Request $request, ServiceFilterService $serviceFilter): View
    {
        return \view('horizon.queues.index', \array_merge([
            'queueCount' => 0,
            'queues' => \collect(),
            'services' => Service::query()->enabled()->orderBy('name')->get(),
            'totalJobs' => 0,
            'defer' => true,
            'header' => 'Queues',
        ], $serviceFilter->viewData($request)));
    }
}
