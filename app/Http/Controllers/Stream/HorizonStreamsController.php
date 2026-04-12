<?php

namespace App\Http\Controllers\Stream;

use App\Http\Controllers\StreamController;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Alert;
use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\HorizonJobListService;
use App\Services\Horizon\HorizonMetricsService;
use App\Services\Horizon\MetricsDashboardDataService;
use App\Services\Horizon\ServiceShowPageDataService;
use App\Support\ConfigHelper;
use App\Support\Horizon\QueueNameNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HorizonStreamsController extends StreamController
{
    public function __construct(
        private MetricsDashboardDataService $metricsDashboard,
        private HorizonMetricsService $metrics,
        private HorizonApiProxyService $horizonApi,
        private HorizonJobListService $jobList,
        private ServiceShowPageDataService $serviceShowPageData,
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
        $streams[] = $this->private__turboStreamTag('update', 'metrics-workload-body', $workloadHtml);

        $supervisorsHtml = \view('horizon.metrics.partials.supervisors-tbody', [
            'supervisorsRows' => $d['supervisorsRows'],
        ])->render();
        $streams[] = $this->private__turboStreamTag('update', 'metrics-supervisors-body', $supervisorsHtml);

        return \implode("\n", $streams);
    }

    // ------------------------------------------------------------------
    //  Queues
    // ------------------------------------------------------------------

    private function private__buildQueuesStreams(string $query): ?string
    {
        $serviceFilterIds = $this->private__parseServiceIdsFromQuery($query, 'queue_services');
        $workloadRows = $this->metrics->getWorkloadData($serviceFilterIds);

        if ($serviceFilterIds !== []) {
            $allowedServiceIds = \array_fill_keys($serviceFilterIds, true);
            $workloadRows = \array_values(\array_filter(
                $workloadRows,
                static function (array $row) use ($allowedServiceIds): bool {
                    return isset($allowedServiceIds[(int) ($row['service_id'] ?? 0)]);
                }
            ));
        }

        $serviceIds = \array_values(\array_unique(\array_map(
            static fn (array $row): int => (int) $row['service_id'],
            $workloadRows
        )));

        $servicesById = $serviceIds === []
            ? \collect()
            : Service::whereIn('id', $serviceIds)->get()->keyBy('id');

        $queues = \collect($workloadRows)
            ->map(function (array $row) use ($servicesById) {
                $queueRaw = $row['queue'] ?? '';
                $normalizedQueue = QueueNameNormalizer::normalize($queueRaw);

                return (object) [
                    'service_id' => (int) $row['service_id'],
                    'queue' => $normalizedQueue ?? $queueRaw,
                    'job_count' => (int) $row['jobs'],
                    'service' => $servicesById->get((int) $row['service_id']),
                ];
            })
            ->sortBy(fn ($r) => $r->queue)
            ->values();

        $html = \view('horizon.queues.partials.queue-tbody', ['queues' => $queues])->render();

        return $this->private__turboStreamTag('update', 'turbo-tbody-horizon-queue-list', $html);
    }

    // ------------------------------------------------------------------
    //  Services index
    // ------------------------------------------------------------------

    private function private__buildServicesStreams(): ?string
    {
        $services = Service::query()->orderBy('name')->get();

        /** @var Service $service */
        foreach ($services as $service) {
            if (! $service->base_url) {
                $service->horizon_failed_jobs_count = 0;
                $service->horizon_jobs_count = 0;
                $service->horizon_status = null;

                continue;
            }

            $response = $this->horizonApi->getStats($service);

            if (($response['success'] ?? false) && \is_array($response['data'])) {
                $service->horizon_failed_jobs_count = isset($response['data']['failedJobs']) ? (int) $response['data']['failedJobs'] : 0;
                $service->horizon_jobs_count = isset($response['data']['recentJobs']) ? (int) $response['data']['recentJobs'] : 0;
                $service->horizon_status = isset($response['data']['status']) && (string) $response['data']['status'] !== '' ? (string) $response['data']['status'] : null;
            } else {
                $service->horizon_failed_jobs_count = 0;
                $service->horizon_jobs_count = 0;
                $service->horizon_status = null;
            }
        }

        $html = \view('horizon.services.partials.service-tbody', ['services' => $services])->render();

        return $this->private__turboStreamTag('update', 'turbo-tbody-horizon-service-list', $html);
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

        return $this->private__turboStreamTag('update', 'turbo-tbody-horizon-alerts-list', $html);
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

        $serviceFilterIds = ServiceRequest::existingIdsFromRequest($pageRequest, ['serviceFilter']);
        $search = (string) $pageRequest->query('search', '');

        $perPage = (int) ConfigHelper::get('horizonhub.jobs_per_page');

        $pageProcessing = \max(1, (int) $pageRequest->query('page_processing', 1));
        $pageProcessed = \max(1, (int) $pageRequest->query('page_processed', 1));
        $pageFailed = \max(1, (int) $pageRequest->query('page_failed', 1));

        $servicesQuery = Service::query()->whereNotNull('base_url');
        if ($serviceFilterIds !== []) {
            $servicesQuery->whereIn('id', $serviceFilterIds);
        }

        /** @var Collection<int, Service> $servicesWithApi */
        $servicesWithApi = $servicesQuery->get();

        $paginators = $this->jobList->buildAggregatedStatusPaginators(
            $servicesWithApi,
            $search,
            $pageProcessing,
            $pageProcessed,
            $pageFailed,
            $perPage,
            $pageRequest->url(),
            $pageRequest->query(),
        );

        $html = \view('horizon.jobs.partials.job-list-collapsible-stack', [
            'jobsProcessing' => $paginators['processing'],
            'jobsProcessed' => $paginators['processed'],
            'jobsFailed' => $paginators['failed'],
            'showServiceColumn' => true,
            'pageService' => null,
            'columnIds' => 'uuid,service,queue,job,attempts,queued_at,processed,failed_at,runtime,actions',
            'resizablePrefix' => 'horizon-job-list',
        ])->render();

        return $this->private__turboStreamTag('replace', 'horizon-jobs-stack', $html);
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
        );

        $streams[] = $this->private__turboStreamTag(
            'update',
            'service-show-supervisor-groups',
            \view('horizon.services.partials.show-supervisor-groups', $d)->render(),
        );

        $jobsHtml = \view('horizon.jobs.partials.job-list-collapsible-stack', [
            'jobsProcessing' => $d['jobsProcessing'],
            'jobsProcessed' => $d['jobsProcessed'],
            'jobsFailed' => $d['jobsFailed'],
            'showServiceColumn' => false,
            'pageService' => $service,
            'columnIds' => 'uuid,queue,job,attempts,queued_at,processed,failed_at,runtime,actions',
            'resizablePrefix' => 'horizon-service-dashboard-jobs',
        ])->render();

        $streams[] = $this->private__turboStreamTag('replace', 'horizon-jobs-stack', $jobsHtml);

        return \implode("\n", $streams);
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    private function private__turboStreamTag(string $action, string $target, string $content): string
    {
        return '<turbo-stream action="'.e($action).'" target="'.e($target).'"><template>'.$content.'</template></turbo-stream>';
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
