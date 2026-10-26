<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Jobs\JobListService;
use App\Services\Services\ServiceFilterService;
use App\Support\Queues\QueueNameNormalizer;
use App\Support\StringFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    /**
     * Display the queue list.
     */
    public function index(Request $request): View
    {
        return \view('pages.queues.index', ServiceFilterService::indexViewData($request, [
            'queueCount' => 0,
            'queues' => \collect(),
            'services' => Service::enabledNamed(),
            'totalJobs' => 0,
            'header' => 'Queues',
        ]));
    }

    /**
     * Display one aggregated queue across services.
     */
    public function show(Request $request, string $queue): View
    {
        $queue = QueueNameNormalizer::normalize($queue);
        $paginators = JobListService::emptyStatusPaginators($request->url(), $request->query());

        return \view('pages.queues.show', ServiceFilterService::indexViewData($request, [
            'header' => $queue,
            'jobsFailed' => $paginators['failed'],
            'jobsProcessed' => $paginators['processed'],
            'jobsProcessing' => $paginators['processing'],
            'queueName' => $queue,
            'search' => StringFormatter::trim($request->query('search', '')),
            'services' => Service::enabledNamed(),
        ]));
    }
}
