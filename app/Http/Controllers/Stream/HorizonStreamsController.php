<?php

namespace App\Http\Controllers\Stream;

use App\Http\Controllers\StreamController;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Alert;
use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\HorizonJobDetailService;
use App\Services\Horizon\HorizonJobListService;
use App\Services\Horizon\HorizonMetricsService;
use App\Services\Horizon\MetricsDashboardDataService;
use App\Services\Horizon\ServiceShowPageDataService;
use App\Services\Horizon\ServiceStatsAttachmentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HorizonStreamsController extends StreamController
{
    public function __construct(
        private MetricsDashboardDataService $metricsDashboard,
        private HorizonMetricsService $metrics,
        private HorizonApiProxyService $horizonApi,
        private HorizonJobListService $jobList,
        private HorizonJobDetailService $jobDetail,
        private ServiceShowPageDataService $serviceShowPageData,
        private ServiceStatsAttachmentService $serviceStats,
    ) {}

    public function metrics(Request $request): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): ?string => $this->private__buildMetricsStreams($query));
    }

    public function queues(Request $request): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): ?string => $this->private__buildQueuesStreams($query));
    }

    public function serviceList(): StreamedResponse
    {
        return $this->runStream(fn (): ?string => $this->private__buildServicesStreams());
    }

    public function alerts(): StreamedResponse
    {
        return $this->runStream(fn (): ?string => $this->private__buildAlertsStreams());
    }

    public function jobs(Request $request): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): ?string => $this->private__buildJobsIndexStreams($query));
    }

    public function jobShow(Request $request, string $job): StreamedResponse
    {
        $serviceId = (int) $request->query('service_id');

        return $this->runStream(fn (): ?string => $this->private__buildJobShowStreams($job, $serviceId));
    }

    public function serviceShow(Request $request, Service $service): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): ?string => $this->private__buildServiceShowStreams($service, $query));
    }

    // ------------------------------------------------------------------
    //  Metrics
    // ------------------------------------------------------------------

    private function private__buildMetricsStreams(string $query): ?string
    {
        $serviceIds = $this->private__parseServiceIdsFromQuery($query);
        $d = $this->metricsDashboard->build($serviceIds);

        $streams = [];

        $streams[] = $this->private__turboStreamTag('update', 'metrics-value-jobs-minute', e($d['jobsPastMinute'] ?? '—'));
        $streams[] = $this->private__turboStreamTag('update', 'metrics-value-jobs-hour', e($d['jobsPastHour'] ?? '—'));
        $streams[] = $this->private__turboStreamTag('update', 'metrics-value-failed-seven', e($d['failedPastSevenDays'] ?? '—'));

        $failureRateHtml = \view('horizon.metrics.partials.failure-rate-value', [
            'failureRate24h' => $d['failureRate24h'],
        ])->render();
        $streams[] = $this->private__turboStreamTag('update', 'metrics-value-failure-rate', $failureRateHtml);

        $streams[] = $this->private__turboStreamTag('update', 'metrics-workload-summary', e($d['workloadSummary']));
        $streams[] = $this->private__turboStreamTag('update', 'metrics-supervisors-summary', e($d['supervisorsSummary']));

        $chartJson = \json_encode($d['metricsChartData'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $streams[] = $this->private__turboStreamTag(
            'replace',
            'metrics-chart-data',
            '<script type="application/json" id="metrics-chart-data">'.$chartJson.'</script>',
        );

        $workloadHtml = \view('horizon.metrics.partials.workload-tbody', [
            'workloadRows' => $d['workloadRows'],
        ])->render();
        $streams[] = $this->private__turboStreamTag('update', 'metrics-workload-body', $workloadHtml, 'morph');

        $supervisorsHtml = \view('horizon.metrics.partials.supervisors-tbody', [
            'supervisorsRows' => $d['supervisorsRows'],
        ])->render();
        $streams[] = $this->private__turboStreamTag('update', 'metrics-supervisors-body', $supervisorsHtml, 'morph');

        return \implode("\n", $streams);
    }

    // ------------------------------------------------------------------
    //  Queues
    // ------------------------------------------------------------------

    private function private__buildQueuesStreams(string $query): ?string
    {
        $serviceFilterIds = $this->private__parseServiceIdsFromQuery($query, 'queue_services');
        $queues = $this->metrics->buildQueuesCollectionForServiceFilter($serviceFilterIds);

        $html = \view('horizon.queues.partials.queue-tbody', ['queues' => $queues])->render();

        return $this->private__turboStreamTag('update', 'turbo-tbody-horizon-queue-list', $html, 'morph');
    }

    // ------------------------------------------------------------------
    //  Services index
    // ------------------------------------------------------------------

    private function private__buildServicesStreams(): ?string
    {
        $services = Service::query()->orderBy('name')->get();
        $this->serviceStats->attachHorizonStats($services, $this->horizonApi);

        $html = \view('horizon.services.partials.service-tbody', ['services' => $services])->render();

        return $this->private__turboStreamTag('update', 'turbo-tbody-horizon-service-list', $html, 'morph');
    }

    // ------------------------------------------------------------------
    //  Alerts index
    // ------------------------------------------------------------------

    private function private__buildAlertsStreams(): ?string
    {
        $alerts = Alert::query()
            ->withCount('alertLogs')
            ->withMax('alertLogs', 'sent_at')
            ->orderByDesc('created_at')
            ->get();

        $html = \view('horizon.alerts.partials.alert-tbody', ['alerts' => $alerts])->render();

        return $this->private__turboStreamTag('update', 'turbo-tbody-horizon-alerts-list', $html, 'morph');
    }

    // ------------------------------------------------------------------
    //  Jobs index
    // ------------------------------------------------------------------

    private function private__buildJobsIndexStreams(string $query): ?string
    {
        $url = \route('horizon.index', [], true);
        if ($query !== '') {
            $url .= "?$query";
        }
        $pageRequest = Request::create($url, 'GET');

        $index = $this->jobList->buildAggregatedJobsIndexFromRequest($pageRequest);

        $html = \view('horizon.jobs.partials.job-list-collapsible-stack', [
            'jobsProcessing' => $index['processing'],
            'jobsProcessed' => $index['processed'],
            'jobsFailed' => $index['failed'],
            'showServiceColumn' => true,
            'pageService' => null,
            'columnIds' => 'uuid,service,queue,job,attempts,queued_at,delayed_until,processed,failed_at,runtime,actions',
            'resizablePrefix' => 'horizon-job-list',
        ])->render();

        return $this->private__turboStreamTag('replace', 'horizon-jobs-stack', $html, 'morph');
    }

    // ------------------------------------------------------------------
    //  Job show
    // ------------------------------------------------------------------

    private function private__buildJobShowStreams(string $routeJobUuid, int $serviceId): ?string
    {
        $service = Service::query()
            ->whereKey($serviceId)
            ->whereNotNull('base_url')
            ->first();

        if ($service === null) {
            return null;
        }

        $response = $this->horizonApi->getJob($service, $routeJobUuid);
        $jobData = [];
        if (($response['success'] ?? false) && isset($response['data']) && \is_array($response['data']) && \count($response['data']) > 0) {
            $jobData = $response['data'];
        } else {
            return null;
        }

        $viewData = $this->jobDetail->buildShowViewData($service, $jobData, $routeJobUuid);
        $jobView = $viewData['job'];

        $exception = $viewData['exception'] ? html_entity_decode($viewData['exception'], ENT_QUOTES | ENT_HTML401, 'UTF-8') : null;
        $exceptionTrace = $exception ? (\preg_split("/\r\n|\n|\r/", $exception) ?: []) : [];
        $retryHistory = isset($viewData['horizonJob']['retriedBy']) && \is_array($viewData['horizonJob']['retriedBy']) ? $viewData['horizonJob']['retriedBy'] : [];
        $payload = $jobView->payload ? json_encode($jobView->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $context = $viewData['horizonJob']['context'] ? json_encode($viewData['horizonJob']['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $commandData = $viewData['horizonJob']['commandData'] ? json_encode($viewData['horizonJob']['commandData'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $vars = [
            'job' => $jobView,
            'exception' => $exceptionTrace,
            'horizonJob' => $viewData['horizonJob'],
            'retryHistory' => $retryHistory,
            'payload' => $payload,
            'context' => $context,
            'commandData' => $commandData,
        ];

        $streams = [];
        $streams[] = $this->private__turboStreamTag(
            'update',
            'horizon-job-detail-actions',
            \view('horizon.jobs.partials.job-show-stream-actions', $vars)->render(),
        );
        $streams[] = $this->private__turboStreamTag(
            'update',
            'horizon-job-detail-meta',
            \view('horizon.jobs.partials.job-show-stream-meta', $vars)->render(),
        );
        $streams[] = $this->private__turboStreamTag(
            'update',
            'horizon-job-detail-exception',
            \view('horizon.jobs.partials.job-show-stream-exception', $vars)->render(),
        );
        $streams[] = $this->private__turboStreamTag(
            'update',
            'horizon-job-detail-context',
            \view('horizon.jobs.partials.job-show-stream-context', $vars)->render(),
        );
        $streams[] = $this->private__turboStreamTag(
            'update',
            'horizon-job-detail-retry-history',
            \view('horizon.jobs.partials.job-show-stream-retry-history', $vars)->render(),
        );
        $streams[] = $this->private__turboStreamTag(
            'update',
            'horizon-job-detail-data',
            \view('horizon.jobs.partials.job-show-stream-data', $vars)->render(),
        );
        $streams[] = $this->private__turboStreamTag(
            'update',
            'horizon-job-detail-payload',
            \view('horizon.jobs.partials.job-show-stream-payload', $vars)->render(),
        );

        return \implode("\n", $streams);
    }

    // ------------------------------------------------------------------
    //  Service show
    // ------------------------------------------------------------------

    private function private__buildServiceShowStreams(Service $service, string $query): ?string
    {
        $url = \route('horizon.services.show', ['service' => $service->id], true);
        $queryParams = [];
        if ($query !== '') {
            \parse_str($query, $queryParams);
        }
        $pageRequest = Request::create($url, 'GET', $queryParams);

        $d = $this->serviceShowPageData->build($service, $pageRequest, $this->horizonApi);

        $streams = [];

        $streams[] = $this->private__turboStreamTag(
            'update',
            'service-show-stats-row-1',
            \view('horizon.services.partials.show-stats-row-1-inner', $d)->render(),
        );
        $streams[] = $this->private__turboStreamTag(
            'update',
            'service-show-stats-row-2',
            \view('horizon.services.partials.show-stats-row-2-inner', $d)->render(),
        );
        $streams[] = $this->private__turboStreamTag(
            'update',
            'service-show-supervisors-panel',
            \view('horizon.services.partials.show-supervisors-panel-inner', $d)->render(),
        );

        $workloadCount = $d['workloadQueues']->count();
        $streams[] = $this->private__turboStreamTag(
            'update',
            'service-show-workload-count',
            e($workloadCount > 0 ? $workloadCount.' queue(s)' : ''),
        );

        $streams[] = $this->private__turboStreamTag(
            'update',
            'service-show-workload-body',
            \view('horizon.services.partials.show-workload-tbody', ['workloadQueues' => $d['workloadQueues']])->render(),
            'morph',
        );

        $streams[] = $this->private__turboStreamTag(
            'update',
            'service-show-supervisor-groups',
            \view('horizon.services.partials.show-supervisor-groups', $d)->render(),
            'morph',
        );

        $jobsHtml = \view('horizon.jobs.partials.job-list-collapsible-stack', [
            'jobsProcessing' => $d['jobsProcessing'],
            'jobsProcessed' => $d['jobsProcessed'],
            'jobsFailed' => $d['jobsFailed'],
            'showServiceColumn' => false,
            'pageService' => $service,
            'columnIds' => 'uuid,queue,job,attempts,queued_at,delayed_until,processed,failed_at,runtime,actions',
            'resizablePrefix' => 'horizon-service-dashboard-jobs',
        ])->render();

        $streams[] = $this->private__turboStreamTag('replace', 'horizon-jobs-stack', $jobsHtml, 'morph');

        return \implode("\n", $streams);
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    private function private__turboStreamTag(string $action, string $target, string $content, ?string $streamMethod = null): string
    {
        $open = '<turbo-stream action="'.e($action).'" target="'.e($target).'"';
        if ($streamMethod !== null && $streamMethod !== '') {
            $open .= ' method="'.e($streamMethod).'"';
        }

        return $open.'><template>'.$content.'</template></turbo-stream>';
    }

    /**
     * @return list<int>
     */
    private function private__parseServiceIdsFromQuery(string $query, string $key = 'service_id'): array
    {
        if ($query === '') {
            return [];
        }

        \parse_str($query, $params);
        $raw = $params[$key] ?? null;

        return ServiceRequest::parseIds($raw);
    }
}
