<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    /**
     * Display the queue list.
     */
    public function index(Request $request): View
    {
        $serviceFilterIds = ServiceRequest::existingIdsFromRequest($request, ['queue_services']);

        return \view('horizon.queues.index', [
            'queueCount' => 0,
            'queues' => \collect(),
            'services' => Service::query()->enabled()->orderBy('name')->get(),
            'totalJobs' => 0,
            'serviceIds' => $serviceFilterIds,
            'defer' => true,
            'header' => 'Queues',
        ]);
    }
}
