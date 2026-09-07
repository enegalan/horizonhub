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
use App\Services\Horizon\HorizonClientService;
use App\Services\Jobs\JobListService;
use App\Services\Jobs\JobServiceResolverService;
use App\Services\Metrics\MetricsDataService;
use App\Services\Services\ServiceFilterService;
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
     * The horizon api proxy service.
     */
    private HorizonClientService $horizonApi;

    /**
     * The job list service.
     */
    private JobListService $jobList;

    /**
     * The job service resolver.
     */
    private JobServiceResolverService $jobServiceResolver;

    /**
     * The metrics data service.
     */
    private MetricsDataService $metrics;

    /**
     * The service filter service.
     */
    private ServiceFilterService $serviceFilter;

    /**
     * The constructor.
     *
     * @param MetricsDataService $metrics The metrics data service.
     * @param HorizonClientService $horizonApi The horizon API client.
     * @param JobListService $jobList The job list service.
     * @param JobServiceResolverService $jobServiceResolver The job service resolver.
     * @param ServiceFilterService $serviceFilter The service filter service.
     */
    public function __construct(MetricsDataService $metrics, HorizonClientService $horizonApi, JobListService $jobList, JobServiceResolverService $jobServiceResolver, ServiceFilterService $serviceFilter)
    {
        $this->metrics = $metrics;
        $this->horizonApi = $horizonApi;
        $this->jobList = $jobList;
        $this->jobServiceResolver = $jobServiceResolver;
        $this->serviceFilter = $serviceFilter;
    }

    public function alerts(Request $request): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): string => $this->buildAlerts($query));
    }

    public function alertShow(Alert $alert): StreamedResponse
    {
        return $this->runStream(fn (): string => $this->buildAlertShow($alert));
    }

    public function dashboard(Request $request): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): string => $this->buildDashboard($query));
    }

    public function jobs(Request $request): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): string => $this->buildJobsIndex($query));
    }

    public function jobShow(string $job): StreamedResponse
    {
        return $this->runStream(fn (): ?string => $this->buildJobShow($job));
    }

    public function metrics(Request $request): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): string => $this->buildMetrics($query));
    }

    public function providerList(Request $request): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): string => $this->buildProviders($query));
    }

    public function queues(Request $request): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): string => $this->buildQueues($query));
    }

    public function serviceList(Request $request): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): string => $this->buildServices($query));
    }

    public function serviceShow(Request $request, Service $service): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): string => $this->buildServiceShow($service, $query));
    }
}
