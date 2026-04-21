<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\HorizonJobDetailService;
use App\Services\Horizon\HorizonJobListService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /**
     * The Horizon API proxy service.
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * The job detail service.
     */
    private HorizonJobDetailService $jobDetail;

    /**
     * The job list service.
     */
    private HorizonJobListService $jobList;

    /**
     * The constructor.
     */
    public function __construct(HorizonJobListService $jobList, HorizonJobDetailService $jobDetail, HorizonApiProxyService $horizonApi)
    {
        $this->jobList = $jobList;
        $this->jobDetail = $jobDetail;
        $this->horizonApi = $horizonApi;
    }

    /**
     * Show the jobs index.
     */
    public function index(Request $request): View
    {
        $index = $this->jobList->buildAggregatedJobsIndexFromRequest($request);

        return \view('horizon.jobs.index', [
            'jobsProcessing' => $index['processing'],
            'jobsProcessed' => $index['processed'],
            'jobsFailed' => $index['failed'],
            'services' => Service::orderBy('name')->get(),
            'filters' => [
                'serviceIds' => $index['serviceFilterIds'],
                'search' => $index['search'],
            ],
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

        $response = $this->horizonApi->getJob($service, $job);
        $jobData = [];

        if (($response['success'] ?? false) && isset($response['data']) && \is_array($response['data']) && \count($response['data']) > 0) {
            $jobData = $response['data'];
        } else {
            abort(404);
        }

        $jobView = $this->jobDetail->buildShowViewData($service, $jobData);

        $exception = ($jobView->exception ?? null) ? html_entity_decode((string) $jobView->exception, ENT_QUOTES | ENT_HTML401, 'UTF-8') : null;
        $exceptionTrace = $exception ? (\preg_split("/\r\n|\n|\r/", $exception) ?: []) : [];
        $retryHistory = \is_array($jobView->retried_by ?? null) ? $jobView->retried_by : [];
        $payload = $jobView->payload ? json_encode($jobView->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $context = ($jobView->context ?? null) ? json_encode($jobView->context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $commandData = ($jobView->command_data ?? null) ? json_encode($jobView->command_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        return \view('horizon.jobs.show', [
            'job' => $jobView,
            'exception' => $exceptionTrace,
            'retryHistory' => $retryHistory,
            'payload' => $payload,
            'context' => $context,
            'commandData' => $commandData,
            'header' => "Job: {$jobView->name}",
        ]);
    }
}
