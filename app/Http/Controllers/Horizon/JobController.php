<?php

namespace App\Http\Controllers\Horizon;

use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Services\ServiceFilterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /**
     * Show the jobs index.
     */
    public function index(Request $request): View
    {
        return \view('horizon.jobs.index', ServiceFilterService::indexViewData($request, [
            'jobsProcessing' => [],
            'jobsProcessed' => [],
            'jobsFailed' => [],
            'services' => Service::enabledNamed(),
            'search' => \trim((string) $request->query('search', '')),
            'header' => 'Jobs',
        ]));
    }

    /**
     * Show a single job detail.
     */
    public function show(string $job): View
    {
        return \view('horizon.jobs.show', [
            'job' => (object) [
                'uuid' => $job,
                'name' => null,
                'queue' => '—',
                'status' => JobStatus::Pending->value,
                'attempts' => 0,
                'connection' => '—',
                'retries' => null,
                'tags' => [],
                'retried_by' => [],
                'queued_at' => null,
                'processed_at' => null,
                'failed_at' => null,
                'runtime' => '—',
                'available_at' => null,
                'exception' => null,
                'context' => null,
                'command_data' => [],
                'payload' => [],
                'service' => null,
            ],
            'defer' => true,
            'exception' => [],
            'retryHistory' => [],
            'payload' => null,
            'context' => null,
            'commandData' => null,
            'header' => 'Job',
        ]);
    }
}
