<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Horizon\ServiceFilterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /**
     * Show the jobs index.
     */
    public function index(Request $request, ServiceFilterService $serviceFilter): View
    {
        return \view('horizon.jobs.index', \array_merge([
            'jobsProcessing' => [],
            'jobsProcessed' => [],
            'jobsFailed' => [],
            'services' => Service::query()->enabled()->orderBy('name')->get(),
            'filters' => [
                'serviceIds' => $serviceFilter->resolveServiceIds($request),
                'search' => (string) $request->query('search', ''),
            ],
            'defer' => true,
            'header' => 'Jobs',
        ], $serviceFilter->viewData($request)));
    }

    /**
     * Show a single job detail.
     */
    public function show(Request $request, string $job): View
    {
        $serviceId = (int) $request->query('service_id');

        $service = Service::query()
            ->whereKey($serviceId)
            ->whereNotNull('base_url')
            ->first();

        if ($service === null) {
            \abort(404);
        }

        return \view('horizon.jobs.show', [
            'job' => (object) [
                'uuid' => $job,
                'name' => null,
                'queue' => '—',
                'status' => 'pending',
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
                'service' => $service,
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
