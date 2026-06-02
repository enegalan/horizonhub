<?php

namespace App\Http\Controllers\Stream;

use App\Http\Controllers\Stream\Concerns\BuildsAlertStreams;
use App\Http\Controllers\Stream\Concerns\BuildsDashboardStreams;
use App\Http\Controllers\Stream\Concerns\BuildsJobListSectionStreams;
use App\Http\Controllers\Stream\Concerns\BuildsJobStreams;
use App\Http\Controllers\Stream\Concerns\BuildsMetricsStreams;
use App\Http\Controllers\Stream\Concerns\BuildsProviderStreams;
use App\Http\Controllers\Stream\Concerns\BuildsQueueStreams;
use App\Http\Controllers\Stream\Concerns\BuildsServiceStreams;
use App\Http\Controllers\StreamController;
use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\AlertChartDataService;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class HorizonStreamsController extends StreamController
{
    use BuildsAlertStreams;
    use BuildsDashboardStreams;
    use BuildsJobListSectionStreams;
    use BuildsJobStreams;
    use BuildsMetricsStreams;
    use BuildsProviderStreams;
    use BuildsQueueStreams;
    use BuildsServiceStreams;

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
     * @param ServiceFilterService $serviceFilter The service filter service.
     */
    public function __construct(DashboardDataService $dashboardData, MetricsDataService $metrics, HorizonClientService $horizonApi, JobListService $jobList, JobDetailService $jobDetail, JobServiceResolver $jobServiceResolver, ServiceDetailService $serviceDetail, ServiceStatsAttachmentService $serviceStats, AlertChartDataService $alertChartData, ServiceFilterService $serviceFilter)
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
}
