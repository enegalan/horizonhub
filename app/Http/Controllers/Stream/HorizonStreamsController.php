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

        $chartDataHtml = \view('components.horizon.alert-detail-chart-data', [
            'chartData' => $chartData,
        ])->render();

        return $this->buildStreams([
            ['update', 'alert-detail-stats', $statsHtml, 'morph'],
            ['replace', 'alert-detail-chart-data', $chartDataHtml, null],
        ]);
    }

    // ------------------------------------------------------------------
    //  Alerts index
    // ------------------------------------------------------------------

    private function private__buildAlertsStreams(): string
    {
        $payload = $this->alertIndexStreamData->build();

        return $this->buildStreams([
            ['update', 'turbo-horizon-alert-stats', \view('horizon.alerts.partials.index.stats', ['alertStats' => $payload['alertStats']])->render(), 'morph'],
            ['update', 'turbo-tbody-horizon-alerts-list', \view('horizon.alerts.partials.index.tbody', ['alerts' => $payload['alerts'], 'serviceLabelsByAlertId' => $payload['serviceLabelsByAlertId']])->render(), 'morph'],
        ]);
    }

    // ------------------------------------------------------------------
    //  Dashboard
    // ------------------------------------------------------------------

    private function private__buildDashboardStreams(): string
    {
        $d = $this->dashboardData->build($this->horizonApi);

        return $this->buildStreams([
            ['update', 'dashboard-value-jobs-minute', e($d['jobsPastMinute'] ?? '—'), null],
            ['update', 'dashboard-value-jobs-hour', e($d['jobsPastHour'] ?? '—'), null],
            ['update', 'dashboard-value-failed-seven', e($d['failedPastSevenDays'] ?? '—'), null],
            ['update', 'dashboard-services-kpi-inner', \view('horizon.dashboard.partials.index.kpi-services-online', [
                'servicesHealthDotClass' => $d['servicesHealthDotClass'] ?? 'bg-slate-400',
                'servicesOnlineCount' => $d['servicesOnlineCount'] ?? 0,
                'servicesTotal' => $d['servicesTotal'] ?? 0,
            ])->render(), 'morph'],
            ['update', 'dashboard-service-health-grid', \view('horizon.dashboard.partials.index.service-health-grid', ['services' => $d['services'] ?? collect()])->render(), 'morph'],
            ['update', 'dashboard-recent-alerts-body', \view('horizon.dashboard.partials.index.recent-alerts-tbody', ['recentAlertLogs' => $d['recentAlertLogs'] ?? collect()])->render(), 'morph'],
            ['update', 'dashboard-workload-summary-body', \view('horizon.dashboard.partials.index.workload-summary-tbody', ['workloadRows' => $d['workloadRows'] ?? []])->render(), 'morph'],
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

        return $this->buildStreams([
            ['update', 'horizon-job-detail-actions-stream', \view('horizon.jobs.partials.show.actions', $vars)->render(), null],
            ['update', 'horizon-job-detail-meta', \view('horizon.jobs.partials.show.meta', $vars)->render(), null],
            ['update', 'horizon-job-detail-exception', \view('horizon.jobs.partials.show.exception', $vars)->render(), null],
            ['update', 'horizon-job-detail-context', \view('horizon.jobs.partials.show.context', $vars)->render(), null],
            ['update', 'horizon-job-detail-retry-history', \view('horizon.jobs.partials.show.retry-history', $vars)->render(), null],
            ['update', 'horizon-job-detail-data', \view('horizon.jobs.partials.show.data', $vars)->render(), null],
            ['update', 'horizon-job-detail-payload', \view('horizon.jobs.partials.show.payload', $vars)->render(), null],
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

        $failureRateHtml = \view('horizon.metrics.partials.index.failure-rate-value', [
            'failureRate24h' => $d['failureRate24h'],
        ])->render();

        $chartJson = \json_encode($d['metricsChartData'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->buildStreams([
            ['update', 'metrics-value-jobs-minute', e($d['jobsPastMinute'] ?? '—'), null],
            ['update', 'metrics-value-jobs-hour', e($d['jobsPastHour'] ?? '—'), null],
            ['update', 'metrics-value-failed-seven', e($d['failedPastSevenDays'] ?? '—'), null],
            ['update', 'metrics-workload-summary', e($d['workloadSummary']), null],
            ['update', 'metrics-supervisors-summary', e($d['supervisorsSummary']), null],
            ['update', 'metrics-value-failure-rate', $failureRateHtml, null],
            ['replace', 'metrics-chart-data', '<script type="application/json" id="metrics-chart-data">' . $chartJson . '</script>', null],
            ['update', 'metrics-workload-body', \view('horizon.metrics.partials.index.workload-tbody', ['workloadRows' => $d['workloadRows']])->render(), 'morph'],
            ['update', 'metrics-supervisors-body', \view('horizon.metrics.partials.index.supervisors-tbody', ['supervisorsRows' => $d['supervisorsRows']])->render(), 'morph'],
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
            ['update', 'turbo-horizon-provider-stats', \view('horizon.providers.partials.index.stats', ['deliveryStats' => $deliveryStats])->render(), 'morph'],
            ['update', 'turbo-tbody-horizon-provider-list', \view('horizon.providers.partials.index.tbody', ['providers' => $providers])->render(), 'morph'],
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
            ['update', 'turbo-horizon-queue-stats', $statsHtml, 'morph'],
            ['update', 'turbo-tbody-horizon-queue-list', $tbodyHtml, 'morph'],
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

        $workloadCount = $d['workloadQueues']->count();

        $streams = [];
        $streams[] = $this->buildStreams([
            ['update', 'service-show-stats-row-1', \view('horizon.services.partials.show.stats-row-1', $d)->render(), null],
            ['update', 'service-show-stats-row-2', \view('horizon.services.partials.show.stats-row-2', $d)->render(), null],
            ['update', 'service-show-supervisors-panel', \view('horizon.services.partials.show.supervisors-panel', $d)->render(), null],
            ['update', 'service-show-workload-count', e($workloadCount > 0 ? $workloadCount . ' queue(s)' : ''), null],
            ['update', 'service-show-workload-body', \view('horizon.services.partials.show.workload-tbody', ['workloadQueues' => $d['workloadQueues']])->render(), 'morph'],
            ['update', 'service-show-supervisor-groups', \view('horizon.services.partials.show.supervisor-groups', $d)->render(), 'morph'],
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
            ['update', 'turbo-horizon-service-stats', \view('horizon.services.partials.index.stats', ['serviceStats' => $serviceStats])->render(), 'morph'],
            ['update', 'turbo-tbody-horizon-service-list', \view('horizon.services.partials.index.tbody', ['services' => $services])->render(), 'morph'],
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
            $operations[] = ['update', "turbo-tbody-$bodyKey", \view('horizon.jobs.partials.index.list-tbody-rows', [
                'kind' => $kind,
                'paginator' => $paginator,
                'showServiceColumn' => $showServiceColumn,
                'pageService' => $pageService,
            ])->render(), 'morph'];
            $operations[] = ['update', "job-count-$bodyKey", \e((string) $paginator->total()), null];
            $operations[] = ['update', "job-pagination-$bodyKey", \view('horizon.jobs.partials.index.list-section-pagination', [
                'paginator' => $paginator,
            ])->render(), 'morph'];
        }

        return $this->buildStreams($operations);
    }
}
