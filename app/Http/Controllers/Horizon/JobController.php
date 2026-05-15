<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /**
     * Show the jobs index.
     */
    public function index(Request $request): View
    {
        $serviceFilterIds = ServiceRequest::existingIdsFromRequest($request, ['serviceFilter']);
        $search = (string) $request->query('search', '');

        return \view('horizon.jobs.index', [
            'jobsProcessing' => [],
            'jobsProcessed' => [],
            'jobsFailed' => [],
            'services' => Service::query()->enabled()->orderBy('name')->get(),
            'filters' => [
                'serviceIds' => $serviceFilterIds,
                'search' => $search,
            ],
            'defer' => true,
            'header' => 'Jobs',
        ]);
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
