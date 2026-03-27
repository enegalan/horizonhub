<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use App\Services\Horizon\HorizonJobDetailService;
use App\Services\Horizon\HorizonJobListService;
use App\Services\Horizon\HorizonJobResolverService;
use App\Support\ConfigHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class JobController extends Controller
{
    /**
     * The job list service.
     */
    private HorizonJobListService $jobList;

    /**
     * The job detail service.
     */
    private HorizonJobDetailService $jobDetail;

    /**
     * The job resolver service.
     */
    private HorizonJobResolverService $jobResolver;

    /**
     * The constructor.
     */
    public function __construct(
        HorizonJobListService $jobList,
        HorizonJobDetailService $jobDetail,
        HorizonJobResolverService $jobResolver,
    ) {
        $this->jobList = $jobList;
        $this->jobDetail = $jobDetail;
        $this->jobResolver = $jobResolver;
    }

    /**
     * Show the jobs index.
     */
    public function index(Request $request): View
    {
        $serviceFilterIds = ServiceRequest::existingIdsFromRequest($request, ['serviceFilter']);
        $search = (string) $request->query('search', '');

        $perPage = (int) ConfigHelper::get('horizonhub.jobs_per_page');

        $servicesQuery = Service::query()->whereNotNull('base_url');
        if ($serviceFilterIds !== []) {
            $servicesQuery->whereIn('id', $serviceFilterIds);
        }

        /** @var Collection<int, Service> $servicesWithApi */
        $servicesWithApi = $servicesQuery->get();

        $pageProcessing = \max(1, (int) $request->query('page_processing', 1));
        $pageProcessed = \max(1, (int) $request->query('page_processed', 1));
        $pageFailed = \max(1, (int) $request->query('page_failed', 1));

        $paginators = $this->jobList->buildAggregatedStatusPaginators(
            $servicesWithApi,
            $search,
            $pageProcessing,
            $pageProcessed,
            $pageFailed,
            $perPage,
            $request->url(),
            $request->query(),
        );

        $services = Service::orderBy('name')->get();

        return \view('horizon.jobs.index', [
            'jobsProcessing' => $paginators['processing'],
            'jobsProcessed' => $paginators['processed'],
            'jobsFailed' => $paginators['failed'],
            'services' => $services,
            'filters' => [
                'serviceIds' => $serviceFilterIds,
                'search' => $search,
            ],
            'header' => 'Horizon Hub – Jobs',
        ]);
    }

    /**
     * Show a single job detail.
     */
    public function show(Request $request, string $job): View
    {
        $serviceId = (int) $request->query('service_id');
        if ($serviceId < 1) {
            $serviceId = null;
        }

        $resolved = $this->jobResolver->getJobAndService($job, $serviceId);

        if ($resolved === null) {
            \abort(404);
        }

        $service = $resolved['service'];
        $jobData = $resolved['job'];

        $viewData = $this->jobDetail->buildShowViewData($service, $jobData, $job);
        $jobView = $viewData['job'];

        $exception = $viewData['exception'] ? html_entity_decode($viewData['exception'], ENT_QUOTES | ENT_HTML401, 'UTF-8') : null;
        $exceptionTrace = $exception ? (\preg_split("/\r\n|\n|\r/", $exception) ?: []) : [];
        $retryHistory = isset($viewData['horizonJob']['retriedBy']) && \is_array($viewData['horizonJob']['retriedBy']) ? $viewData['horizonJob']['retriedBy'] : [];
        $payload = $jobView->payload ? json_encode($jobView->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $context = $viewData['horizonJob']['context'] ? json_encode($viewData['horizonJob']['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $commandData = $viewData['horizonJob']['commandData'] ? json_encode($viewData['horizonJob']['commandData'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        return \view('horizon.jobs.show', [
            'job' => $jobView,
            'exception' => $exceptionTrace,
            'horizonJob' => $viewData['horizonJob'],
            'retryHistory' => $retryHistory,
            'payload' => $payload,
            'context' => $context,
            'commandData' => $commandData,
            'header' => 'Job: '.($jobView->name ?? $jobView->uuid),
        ]);
    }
}
