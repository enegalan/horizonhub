<?php

namespace App\Http\Controllers\Stream;

use App\Http\Controllers\StreamController;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\AlertChartDataService;
use App\Services\Horizon\DashboardDataService;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\HorizonJobDetailService;
use App\Services\Horizon\HorizonJobListService;
use App\Services\Horizon\HorizonMetricsService;
use App\Services\Horizon\MetricsDashboardDataService;
use App\Services\Horizon\ServiceShowPageDataService;
use App\Services\Horizon\ServiceStatsAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HorizonStreamsController extends StreamController
{
    /**
     * The alert chart data service.
     */
    private AlertChartDataService $alertChartData;

    /**
     * The dashboard data service.
     */
    private DashboardDataService $dashboardData;

    /**
     * The horizon api proxy service.
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * The horizon job detail service.
     */
    private HorizonJobDetailService $jobDetail;

    /**
     * The horizon job list service.
     */
    private HorizonJobListService $jobList;

    /**
     * The metrics service.
     */
    private HorizonMetricsService $metrics;

    /**
     * The metrics dashboard data service.
     */
    private MetricsDashboardDataService $metricsDashboard;

    /**
     * The service show page data service.
     */
    private ServiceShowPageDataService $serviceShowPageData;

    /**
     * The service stats attachment service.
     */
    private ServiceStatsAttachmentService $serviceStats;

    /**
     * The constructor.
     */
    public function __construct(MetricsDashboardDataService $metricsDashboard, DashboardDataService $dashboardData, HorizonMetricsService $metrics, HorizonApiProxyService $horizonApi, HorizonJobListService $jobList, HorizonJobDetailService $jobDetail, ServiceShowPageDataService $serviceShowPageData, ServiceStatsAttachmentService $serviceStats, AlertChartDataService $alertChartData)
    {
        $this->metricsDashboard = $metricsDashboard;
        $this->dashboardData = $dashboardData;
        $this->metrics = $metrics;
        $this->horizonApi = $horizonApi;
        $this->jobList = $jobList;
        $this->jobDetail = $jobDetail;
        $this->serviceShowPageData = $serviceShowPageData;
        $this->serviceStats = $serviceStats;
        $this->alertChartData = $alertChartData;
    }

    public function alerts(): StreamedResponse
    {
        return $this->runStream(fn (): ?string => $this->private__buildAlertsStreams());
    }

    public function alertShow(Alert $alert): StreamedResponse
    {
        return $this->runStream(fn (): ?string => $this->private__buildAlertShowStreams($alert));
    }

    public function dashboard(): StreamedResponse
    {
        return $this->runStream(fn (): ?string => $this->private__buildDashboardStreams());
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

    public function serviceShow(Request $request, Service $service): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): ?string => $this->private__buildServiceShowStreams($service, $query));
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

    private function private__buildAlertShowStreams(Alert $alert): ?string
    {
        $chartData = [
            'chart24h' => $this->alertChartData->buildChart($alert, 1),
            'chart7d' => $this->alertChartData->buildChart($alert, 7),
            'chart30d' => $this->alertChartData->buildChart($alert, 30),
        ];

        $streams = [];

        $statsHtml = \view('horizon.alerts.partials.detail-stats', [
            'chartData' => $chartData,
        ])->render();
        $streams[] = $this->private__turboStreamTag('update', 'alert-detail-stats', $statsHtml, 'morph');

        $chartJson = \json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $streams[] = $this->private__turboStreamTag('replace', 'alert-detail-chart-data', '<div id="alert-detail-chart-data"><script type="application/json" id="alert-detail-chart-data-json">' . $chartJson . '</script></div>');

        return \implode("\n", $streams);
    }

    // ------------------------------------------------------------------
    //  Dashboard
    // ------------------------------------------------------------------

    private function private__buildDashboardStreams(): ?string
    {
        $d = $this->dashboardData->build($this->horizonApi);

        $updates = [
            'dashboard-value-jobs-minute' => e($d['jobsPastMinute'] ?? '—'),
            'dashboard-value-jobs-hour' => e($d['jobsPastHour'] ?? '—'),
            'dashboard-value-failed-seven' => e($d['failedPastSevenDays'] ?? '—'),
        ];
        $streams = [];
        $this->private__pushStreamUpdates($streams, $updates);

        $views = [
            'dashboard-services-kpi-inner' => ['view' => 'horizon.dashboard.partials.kpi-services-online-inner', 'data' => [
                'servicesHealthDotClass' => $d['servicesHealthDotClass'] ?? 'bg-slate-400',
                'servicesOnlineCount' => $d['servicesOnlineCount'] ?? 0,
                'servicesTotal' => $d['servicesTotal'] ?? 0,
            ]],
            'dashboard-service-health-grid' => ['view' => 'horizon.dashboard.partials.service-health-grid', 'data' => ['services' => $d['services'] ?? collect()]],
            'dashboard-recent-alerts-body' => ['view' => 'horizon.dashboard.partials.recent-alerts-tbody', 'data' => ['recentAlertLogs' => $d['recentAlertLogs'] ?? collect()]],
            'dashboard-workload-summary-body' => ['view' => 'horizon.dashboard.partials.workload-summary-tbody', 'data' => ['workloadRows' => $d['workloadRows'] ?? []]],
        ];
        $this->private__pushViewStreams($streams, $views, 'morph');

        return \implode("\n", $streams);
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

        $jobView = $this->jobDetail->buildShowViewData($service, $jobData);

        $exception = ($jobView->exception ?? null) ? html_entity_decode((string) $jobView->exception, ENT_QUOTES | ENT_HTML401, 'UTF-8') : null;
        $exceptionTrace = $exception ? (\preg_split("/\r\n|\n|\r/", $exception) ?: []) : [];
        $retryHistory = \is_array($jobView->retried_by ?? null) ? $jobView->retried_by : [];
        $payload = $jobView->payload ? json_encode($jobView->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $context = ($jobView->context ?? null) ? json_encode($jobView->context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $commandData = ($jobView->command_data ?? null) ? json_encode($jobView->command_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $vars = [
            'job' => $jobView,
            'exception' => $exceptionTrace,
            'retryHistory' => $retryHistory,
            'payload' => $payload,
            'context' => $context,
            'commandData' => $commandData,
        ];

        $views = [
            'horizon-job-detail-actions-stream' => ['view' => 'horizon.jobs.partials.job-show-stream-actions', 'data' => $vars],
            'horizon-job-detail-meta' => ['view' => 'horizon.jobs.partials.job-show-stream-meta', 'data' => $vars],
            'horizon-job-detail-exception' => ['view' => 'horizon.jobs.partials.job-show-stream-exception', 'data' => $vars],
            'horizon-job-detail-context' => ['view' => 'horizon.jobs.partials.job-show-stream-context', 'data' => $vars],
            'horizon-job-detail-retry-history' => ['view' => 'horizon.jobs.partials.job-show-stream-retry-history', 'data' => $vars],
            'horizon-job-detail-data' => ['view' => 'horizon.jobs.partials.job-show-stream-data', 'data' => $vars],
            'horizon-job-detail-payload' => ['view' => 'horizon.jobs.partials.job-show-stream-payload', 'data' => $vars],
        ];

        $streams = [];
        $this->private__pushViewStreams($streams, $views);

        return \implode("\n", $streams);
    }

    // ------------------------------------------------------------------
    //  Jobs index
    // ------------------------------------------------------------------

    private function private__buildJobsIndexStreams(string $query): ?string
    {
        $url = \route('horizon.jobs.index', [], true);

        if ($query !== '') {
            $url .= "?$query";
        }
        $pageRequest = Request::create($url, 'GET');

        $index = $this->jobList->buildAggregatedJobsIndexFromRequest($pageRequest);

        return \implode("\n", $this->private__streamsForJobListSections(
            [
                'processing' => $index['processing'],
                'processed' => $index['processed'],
                'failed' => $index['failed'],
            ],
            'horizon-job-list',
            true,
            null,
        ));
    }

    // ------------------------------------------------------------------
    //  Metrics
    // ------------------------------------------------------------------

    private function private__buildMetricsStreams(string $query): ?string
    {
        $d = $this->metricsDashboard->build($this->private__parseServiceIdsFromQuery($query));

        $updates = [
            'metrics-value-jobs-minute' => e($d['jobsPastMinute'] ?? '—'),
            'metrics-value-jobs-hour' => e($d['jobsPastHour'] ?? '—'),
            'metrics-value-failed-seven' => e($d['failedPastSevenDays'] ?? '—'),
            'metrics-workload-summary' => e($d['workloadSummary']),
            'metrics-supervisors-summary' => e($d['supervisorsSummary']),
        ];

        $streams = [];
        $this->private__pushStreamUpdates($streams, $updates);

        $failureRateHtml = \view('horizon.metrics.partials.failure-rate-value', [
            'failureRate24h' => $d['failureRate24h'],
        ])->render();
        $streams[] = $this->private__turboStreamTag('update', 'metrics-value-failure-rate', $failureRateHtml);

        $chartJson = \json_encode($d['metricsChartData'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $streams[] = $this->private__turboStreamTag('replace', 'metrics-chart-data', '<script type="application/json" id="metrics-chart-data">' . $chartJson . '</script>');

        $views = [
            'metrics-workload-body' => ['view' => 'horizon.metrics.partials.workload-tbody', 'data' => ['workloadRows' => $d['workloadRows']]],
            'metrics-supervisors-body' => ['view' => 'horizon.metrics.partials.supervisors-tbody', 'data' => ['supervisorsRows' => $d['supervisorsRows']]],
        ];
        $this->private__pushViewStreams($streams, $views, 'morph');

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

        $views = [
            'service-show-stats-row-1' => ['view' => 'horizon.services.partials.show-stats-row-1-inner', 'data' => $d],
            'service-show-stats-row-2' => ['view' => 'horizon.services.partials.show-stats-row-2-inner', 'data' => $d],
            'service-show-supervisors-panel' => ['view' => 'horizon.services.partials.show-supervisors-panel-inner', 'data' => $d],
        ];
        $this->private__pushViewStreams($streams, $views);

        $workloadCount = $d['workloadQueues']->count();
        $this->private__pushStreamUpdates($streams, [
            'service-show-workload-count' => e($workloadCount > 0 ? $workloadCount . ' queue(s)' : ''),
        ]);

        $viewsMorph = [
            'service-show-workload-body' => ['view' => 'horizon.services.partials.show-workload-tbody', 'data' => ['workloadQueues' => $d['workloadQueues']]],
            'service-show-supervisor-groups' => ['view' => 'horizon.services.partials.show-supervisor-groups', 'data' => $d],
        ];
        $this->private__pushViewStreams($streams, $viewsMorph, 'morph');

        foreach ($this->private__streamsForJobListSections(
            [
                'processing' => $d['jobsProcessing'],
                'processed' => $d['jobsProcessed'],
                'failed' => $d['jobsFailed'],
            ],
            'horizon-service-dashboard-jobs',
            false,
            $service,
        ) as $jobStream) {
            $streams[] = $jobStream;
        }

        return \implode("\n", $streams);
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

    private function private__pushStreamUpdates(array &$streams, array $updates, ?string $streamMethod = null): void
    {
        foreach ($updates as $target => $content) {
            $streams[] = $this->private__turboStreamTag('update', $target, $content, $streamMethod);
        }
    }

    private function private__pushViewStreams(array &$streams, array $views, ?string $streamMethod = null): void
    {
        $updates = [];

        foreach ($views as $target => $viewData) {
            $updates[$target] = \view($viewData['view'], $viewData['data'] ?? [])->render();
        }
        $this->private__pushStreamUpdates($streams, $updates, $streamMethod);
    }

    /**
     * Turbo streams for the three job list section tbodies, badge counts, and pagination (no thead replace).
     *
     * @param array{processing: LengthAwarePaginator, processed: LengthAwarePaginator, failed: LengthAwarePaginator} $jobsIndex
     *
     * @return list<string>
     */
    private function private__streamsForJobListSections(array $jobsIndex, string $resizablePrefix, bool $showServiceColumn, ?Service $pageService): array
    {
        $streams = [];

        foreach (['processing', 'processed', 'failed'] as $kind) {
            $paginator = $jobsIndex[$kind];
            $bodyKey = "$resizablePrefix-$kind";
            $tbodyHtml = \view('horizon.jobs.partials.job-list-tbody-rows', [
                'kind' => $kind,
                'paginator' => $paginator,
                'showServiceColumn' => $showServiceColumn,
                'pageService' => $pageService,
            ])->render();
            $streams[] = $this->private__turboStreamTag('update', "turbo-tbody-$bodyKey", $tbodyHtml, 'morph');
            $streams[] = $this->private__turboStreamTag('update', "job-count-$bodyKey", \e((string) $paginator->total()));
            $paginationHtml = \view('horizon.jobs.partials.job-list-section-pagination', [
                'paginator' => $paginator,
            ])->render();
            $streams[] = $this->private__turboStreamTag('update', "job-pagination-$bodyKey", $paginationHtml, 'morph');
        }

        return $streams;
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    private function private__turboStreamTag(string $action, string $target, string $content, ?string $streamMethod = null): string
    {
        $open = '<turbo-stream action="' . e($action) . '" target="' . e($target) . '"';

        if ($streamMethod !== null && $streamMethod !== '') {
            $open .= ' method="' . e($streamMethod) . '"';
        }

        return "$open><template>$content</template></turbo-stream>";
    }
}
