<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Services\ServiceFilterService;
use App\Support\StringFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    /**
     * Display recent batches across Horizon services.
     */
    public function index(Request $request): View
    {
        return \view('pages.batches.index', ServiceFilterService::indexViewData($request, [
            'activeCount' => 0,
            'batchCount' => 0,
            'batches' => \collect(),
            'failedCount' => 0,
            'mayBeTruncated' => false,
            'search' => StringFormatter::trim($request->query('search')),
            'services' => Service::enabledNamed(),
            'header' => 'Batches',
        ]));
    }

    /**
     * Display one batch nested under a service.
     */
    public function show(Service $service, string $batch): View
    {
        return \view('pages.batches.show', [
            'batchId' => $batch,
            'defer' => true,
            'header' => 'Batch',
            'service' => $service,
        ]);
    }
}
