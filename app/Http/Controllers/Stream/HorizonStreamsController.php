<?php

namespace App\Http\Controllers\Stream;

use App\Http\Controllers\StreamController;
use App\Models\Alert;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\Alerts\AlertChartDataService;
use App\Services\Alerts\AlertDataService;
use App\Services\Dashboard\DashboardDataService;
use App\Services\Horizon\HorizonClientService;
use App\Services\Jobs\JobDetailService;
use App\Services\Jobs\JobListService;
use App\Services\Jobs\JobServiceResolver;
use App\Services\Metrics\MetricsDataService;
use App\Services\Services\ServiceDetailService;
use App\Services\Services\ServiceFilterService;
use App\Services\Services\ServiceStatsAttachmentService;
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
     * The alert index stream data service.
     */
    private AlertDataService $alertIndexStreamData;

    /**
     * The dashboard data service.
     */
    private DashboardDataService $dashboardData;

    /**
     * The horizon api proxy service.
     */
    private HorizonClientService $horizonApi;

    /**
     * The job detail service.
     */
    private JobDetailService $jobDetail;

    /**
     * The job list service.
     */
    private JobListService $jobList;

    /**
     * The job service resolver.
     */
    private JobServiceResolver $jobServiceResolver;

    /**
     * The metrics data service.
     */
    private MetricsDataService $metrics;

    /**
     * The service detail service.
     */
    private ServiceDetailService $serviceDetail;

    /**
     * The service filter service.
     */
    private ServiceFilterService $serviceFilter;

    /**
     * The service stats attachment service.
     */
    private ServiceStatsAttachmentService $serviceStats;

    /**
     * The constructor.
     *
     * @param DashboardDataService $dashboardData The dashboard data service.
     * @param MetricsDataService $metrics The metrics data service.
     * @param HorizonClientService $horizonApi The horizon API client.
     * @param JobListService $jobList The job list service.
     * @param JobDetailService $jobDetail The job detail service.
     * @param JobServiceResolver $jobServiceResolver The job service resolver.
     * @param ServiceDetailService $serviceDetail The service detail service.
     * @param ServiceStatsAttachmentService $serviceStats The service stats attachment service.
     * @param AlertChartDataService $alertChartData The alert chart data service.
     * @param AlertDataService $alertIndexStreamData The alert index stream data service.
     * @param ServiceFilterService $serviceFilter The service filter service.
     */
    public function __construct(DashboardDataService $dashboardData, MetricsDataService $metrics, HorizonClientService $horizonApi, JobListService $jobList, JobDetailService $jobDetail, JobServiceResolver $jobServiceResolver, ServiceDetailService $serviceDetail, ServiceStatsAttachmentService $serviceStats, AlertChartDataService $alertChartData, AlertDataService $alertIndexStreamData, ServiceFilterService $serviceFilter)
    {
        $this->dashboardData = $dashboardData;
        $this->metrics = $metrics;
        $this->horizonApi = $horizonApi;
        $this->jobList = $jobList;
        $this->jobDetail = $jobDetail;
        $this->jobServiceResolver = $jobServiceResolver;
        $this->serviceDetail = $serviceDetail;
        $this->serviceStats = $serviceStats;
        $this->alertChartData = $alertChartData;
        $this->alertIndexStreamData = $alertIndexStreamData;
        $this->serviceFilter = $serviceFilter;
    }

    public function alerts(): StreamedResponse
    {
        return $this->runStream(fn (): string => $this->private__buildAlertsStreams());
    }

    public function alertShow(Alert $alert): StreamedResponse
    {
        return $this->runStream(fn (): string => $this->private__buildAlertShowStreams($alert));
    }

    public function dashboard(): StreamedResponse
    {
        return $this->runStream(fn (): string => $this->private__buildDashboardStreams());
    }

    public function jobs(Request $request): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): string => $this->private__buildJobsIndexStreams($query));
    }

    public function jobShow(string $job): StreamedResponse
    {
        return $this->runStream(fn (): ?string => $this->private__buildJobShowStreams($job));
    }

    public function metrics(Request $request): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): string => $this->private__buildMetricsStreams($query));
    }

    public function providerList(): StreamedResponse
    {
        return $this->runStream(fn (): string => $this->private__buildProvidersStreams());
    }

    public function queues(Request $request): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): string => $this->private__buildQueuesStreams($query));
    }

    public function serviceList(Request $request): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): string => $this->private__buildServicesStreams($query));
    }

    public function serviceShow(Request $request, Service $service): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): string => $this->private__buildServiceShowStreams($service, $query));
    }

    private function private__buildAlertShowStreams(Alert $alert): string
    {
        $chartData = [
            'chart24h' => $this->alertChartData->buildChart($alert, 1),
            'chart7d' => $this->alertChartData->buildChart($alert, 7),
            'chart30d' => $this->alertChartData->buildChart($alert, 30),
        ];

        $statsHtml = \view('horizon.alerts.partials.show.stats', [
            'chartData' => $chartData,
        ])->render();

        $chartJson = \json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->buildStreams([
            [self::MODE_APPEND_CONTENT, 'update', 'alert-detail-stats', $statsHtml, 'morph'],
            [self::MODE_APPEND_CONTENT, 'replace', 'alert-detail-chart-data', '<div id="alert-detail-chart-data"><script type="application/json" id="alert-detail-chart-data-json">' . $chartJson . '</script></div>'],
        ]);
    }

    // ------------------------------------------------------------------
    //  Alerts index
    // ------------------------------------------------------------------

    private function private__buildAlertsStreams(): string
    {
        $payload = $this->alertIndexStreamData->build();

        return $this->buildStreams([
            [self::MODE_PUSH_VIEW, [
                'turbo-horizon-alert-stats' => ['view' => 'horizon.alerts.partials.index.stats', 'data' => ['alertStats' => $payload['alertStats']]],
                'turbo-tbody-horizon-alerts-list' => ['view' => 'horizon.alerts.partials.index.tbody', 'data' => ['alerts' => $payload['alerts'], 'serviceLabelsByAlertId' => $payload['serviceLabelsByAlertId']]],
            ], 'morph'],
        ]);
    }

    // ------------------------------------------------------------------
    //  Dashboard
    // ------------------------------------------------------------------

    private function private__buildDashboardStreams(): string
    {
        $d = $this->dashboardData->build($this->horizonApi);

        $updates = [
            'dashboard-value-jobs-minute' => e($d['jobsPastMinute'] ?? '—'),
            'dashboard-value-jobs-hour' => e($d['jobsPastHour'] ?? '—'),
            'dashboard-value-failed-seven' => e($d['failedPastSevenDays'] ?? '—'),
        ];

        $views = [
            'dashboard-services-kpi-inner' => ['view' => 'horizon.dashboard.partials.index.kpi-services-online', 'data' => [
                'servicesHealthDotClass' => $d['servicesHealthDotClass'] ?? 'bg-slate-400',
                'servicesOnlineCount' => $d['servicesOnlineCount'] ?? 0,
                'servicesTotal' => $d['servicesTotal'] ?? 0,
            ]],
            'dashboard-service-health-grid' => ['view' => 'horizon.dashboard.partials.index.service-health-grid', 'data' => ['services' => $d['services'] ?? collect()]],
            'dashboard-recent-alerts-body' => ['view' => 'horizon.dashboard.partials.index.recent-alerts-tbody', 'data' => ['recentAlertLogs' => $d['recentAlertLogs'] ?? collect()]],
            'dashboard-workload-summary-body' => ['view' => 'horizon.dashboard.partials.index.workload-summary-tbody', 'data' => ['workloadRows' => $d['workloadRows'] ?? []]],
        ];

        return $this->buildStreams([
            [self::MODE_PUSH_STREAM, $updates, null],
            [self::MODE_PUSH_VIEW, $views, 'morph'],
        ]);
    }

    // ------------------------------------------------------------------
    //  Job show
    // ------------------------------------------------------------------

    private function private__buildJobShowStreams(string $routeJobUuid): ?string
    {
        $resolved = $this->jobServiceResolver->resolve($routeJobUuid);

        if ($resolved === null) {
            return null;
        }

        $jobView = $this->jobDetail->buildShowViewData($resolved['service'], $resolved['data']);

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
            'horizon-job-detail-actions-stream' => ['view' => 'horizon.jobs.partials.show.actions', 'data' => $vars],
            'horizon-job-detail-meta' => ['view' => 'horizon.jobs.partials.show.meta', 'data' => $vars],
            'horizon-job-detail-exception' => ['view' => 'horizon.jobs.partials.show.exception', 'data' => $vars],
            'horizon-job-detail-context' => ['view' => 'horizon.jobs.partials.show.context', 'data' => $vars],
            'horizon-job-detail-retry-history' => ['view' => 'horizon.jobs.partials.show.retry-history', 'data' => $vars],
            'horizon-job-detail-data' => ['view' => 'horizon.jobs.partials.show.data', 'data' => $vars],
            'horizon-job-detail-payload' => ['view' => 'horizon.jobs.partials.show.payload', 'data' => $vars],
        ];

        return $this->buildStreams([
            [self::MODE_PUSH_VIEW, $views, null],
        ]);
    }

    // ------------------------------------------------------------------
    //  Jobs index
    // ------------------------------------------------------------------

    private function private__buildJobsIndexStreams(string $query): string
    {
        $url = \route('horizon.jobs.index', [], true);

        if ($query !== '') {
            $url .= "?$query";
        }
        $pageRequest = Request::create($url, 'GET');

        $index = $this->jobList->buildAggregatedJobsIndexFromRequest($pageRequest);

        return $this->private__streamsForJobListSections(
            [
                'processing' => $index['processing'],
                'processed' => $index['processed'],
                'failed' => $index['failed'],
            ],
            'horizon-job-list',
            true,
            null,
        );
    }

    // ------------------------------------------------------------------
    //  Metrics
    // ------------------------------------------------------------------

    private function private__buildMetricsStreams(string $query): string
    {
        $d = $this->metrics->buildMetricsDashboardData($this->serviceFilter->resolveFromQuery($query));

        $updates = [
            'metrics-value-jobs-minute' => e($d['jobsPastMinute'] ?? '—'),
            'metrics-value-jobs-hour' => e($d['jobsPastHour'] ?? '—'),
            'metrics-value-failed-seven' => e($d['failedPastSevenDays'] ?? '—'),
            'metrics-workload-summary' => e($d['workloadSummary']),
            'metrics-supervisors-summary' => e($d['supervisorsSummary']),
        ];

        $failureRateHtml = \view('horizon.metrics.partials.index.failure-rate-value', [
            'failureRate24h' => $d['failureRate24h'],
        ])->render();

        $chartJson = \json_encode($d['metricsChartData'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $views = [
            'metrics-workload-body' => ['view' => 'horizon.metrics.partials.index.workload-tbody', 'data' => ['workloadRows' => $d['workloadRows']]],
            'metrics-supervisors-body' => ['view' => 'horizon.metrics.partials.index.supervisors-tbody', 'data' => ['supervisorsRows' => $d['supervisorsRows']]],
        ];

        return $this->buildStreams([
            [self::MODE_PUSH_STREAM, $updates, null],
            [self::MODE_APPEND_CONTENT, 'update', 'metrics-value-failure-rate', $failureRateHtml, null],
            [self::MODE_APPEND_CONTENT, 'replace', 'metrics-chart-data', '<script type="application/json" id="metrics-chart-data">' . $chartJson . '</script>', null],
            [self::MODE_PUSH_VIEW, $views, 'morph'],
        ]);
    }

    // ------------------------------------------------------------------
    //  Providers index
    // ------------------------------------------------------------------

    private function private__buildProvidersStreams(): string
    {
        $providers = NotificationProvider::query()
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $deliveryStats = $this->alertIndexStreamData->countsByProviderType();

        return $this->buildStreams([
            [self::MODE_PUSH_VIEW, [
                'turbo-horizon-provider-stats' => ['view' => 'horizon.providers.partials.index.stats', 'data' => ['deliveryStats' => $deliveryStats]],
                'turbo-tbody-horizon-provider-list' => ['view' => 'horizon.providers.partials.index.tbody', 'data' => ['providers' => $providers]],
            ], 'morph'],
        ]);
    }

    // ------------------------------------------------------------------
    //  Queues
    // ------------------------------------------------------------------

    private function private__buildQueuesStreams(string $query): string
    {
        $serviceFilterIds = $this->serviceFilter->resolveFromQuery($query);
        $queues = $this->metrics->buildQueuesCollectionForServiceFilter($serviceFilterIds);

        $totalJobs = (int) $queues->sum(static fn ($r): int => (int) ($r->job_count ?? 0));

        $statsHtml = \view('horizon.queues.partials.index.stats', [
            'queueCount' => $queues->count(),
            'totalJobs' => $totalJobs,
        ])->render();

        $tbodyHtml = \view('horizon.queues.partials.index.tbody', ['queues' => $queues])->render();

        return $this->buildStreams([
            [self::MODE_APPEND_CONTENT, 'update', 'turbo-horizon-queue-stats', $statsHtml, 'morph'],
            [self::MODE_APPEND_CONTENT, 'update', 'turbo-tbody-horizon-queue-list', $tbodyHtml, 'morph'],
        ]);
    }

    // ------------------------------------------------------------------
    //  Service show
    // ------------------------------------------------------------------

    private function private__buildServiceShowStreams(Service $service, string $query): string
    {
        $url = \route('horizon.services.show', ['service' => $service->id], true);
        $queryParams = [];

        \parse_str($query, $queryParams);
        $pageRequest = Request::create($url, 'GET', $queryParams);

        $d = $this->serviceDetail->build($service, $pageRequest, $this->horizonApi);

        $views = [
            'service-show-stats-row-1' => ['view' => 'horizon.services.partials.show.stats-row-1', 'data' => $d],
            'service-show-stats-row-2' => ['view' => 'horizon.services.partials.show.stats-row-2', 'data' => $d],
            'service-show-supervisors-panel' => ['view' => 'horizon.services.partials.show.supervisors-panel', 'data' => $d],
        ];

        $workloadCount = $d['workloadQueues']->count();
        $updates = [
            'service-show-workload-count' => e($workloadCount > 0 ? $workloadCount . ' queue(s)' : ''),
        ];

        $viewsMorph = [
            'service-show-workload-body' => ['view' => 'horizon.services.partials.show.workload-tbody', 'data' => ['workloadQueues' => $d['workloadQueues']]],
            'service-show-supervisor-groups' => ['view' => 'horizon.services.partials.show.supervisor-groups', 'data' => $d],
        ];

        $streams = [];
        $streams[] = $this->buildStreams([
            [self::MODE_PUSH_VIEW, $views, null],
            [self::MODE_PUSH_STREAM, $updates, null],
            [self::MODE_PUSH_VIEW, $viewsMorph, 'morph'],
        ]);

        $streams[] = $this->private__streamsForJobListSections(
            [
                'processing' => $d['jobsProcessing'],
                'processed' => $d['jobsProcessed'],
                'failed' => $d['jobsFailed'],
            ],
            'horizon-service-dashboard-jobs',
            false,
            $service,
        );

        return \implode("\n", $streams);
    }

    // ------------------------------------------------------------------
    //  Services index
    // ------------------------------------------------------------------

    private function private__buildServicesStreams(string $query): string
    {
        $serviceIds = $this->serviceFilter->resolveFromQuery($query);
        $servicesQuery = Service::query()->orderBy('name');

        if (! empty($serviceIds)) {
            $servicesQuery->whereIn('id', $serviceIds);
        }

        $services = $servicesQuery->get();
        $this->serviceStats->attachHorizonStats($services, $this->horizonApi);
        $serviceStats = $this->serviceStats->buildListSummaryCounts($services);

        return $this->buildStreams([
            [self::MODE_PUSH_VIEW, [
                'turbo-horizon-service-stats' => ['view' => 'horizon.services.partials.index.stats', 'data' => ['serviceStats' => $serviceStats]],
                'turbo-tbody-horizon-service-list' => ['view' => 'horizon.services.partials.index.tbody', 'data' => ['services' => $services]],
            ], 'morph'],
        ]);
    }

    /**
     * Turbo streams for the three job list section tbodies, badge counts, and pagination (no thead replace).
     *
     * @param array{processing: LengthAwarePaginator, processed: LengthAwarePaginator, failed: LengthAwarePaginator} $jobsIndex
     */
    private function private__streamsForJobListSections(array $jobsIndex, string $resizablePrefix, bool $showServiceColumn, ?Service $pageService): string
    {
        $operations = [];

        foreach (['processing', 'processed', 'failed'] as $kind) {
            $paginator = $jobsIndex[$kind];
            $bodyKey = "$resizablePrefix-$kind";
            $operations[] = [self::MODE_APPEND_CONTENT, 'update', "turbo-tbody-$bodyKey", \view('horizon.jobs.partials.index.list-tbody-rows', [
                'kind' => $kind,
                'paginator' => $paginator,
                'showServiceColumn' => $showServiceColumn,
                'pageService' => $pageService,
            ])->render(), 'morph'];
            $operations[] = [self::MODE_APPEND_CONTENT, 'update', "job-count-$bodyKey", \e((string) $paginator->total()), null];
            $operations[] = [self::MODE_APPEND_CONTENT, 'update', "job-pagination-$bodyKey", \view('horizon.jobs.partials.index.list-section-pagination', [
                'paginator' => $paginator,
            ])->render(), 'morph'];
        }

        return $this->buildStreams($operations);
    }
}
