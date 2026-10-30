<?php

namespace App\Http\Controllers\Stream;

use App\Http\Controllers\Stream\Concerns\BuildsAlertStreams;
use App\Http\Controllers\Stream\Concerns\BuildsBatchStreams;
use App\Http\Controllers\Stream\Concerns\BuildsDashboardStreams;
use App\Http\Controllers\Stream\Concerns\BuildsInstanceStreams;
use App\Http\Controllers\Stream\Concerns\BuildsJobListSectionStreams;
use App\Http\Controllers\Stream\Concerns\BuildsJobStreams;
use App\Http\Controllers\Stream\Concerns\BuildsMetricsStreams;
use App\Http\Controllers\Stream\Concerns\BuildsProviderStreams;
use App\Http\Controllers\Stream\Concerns\BuildsQueueStreams;
use App\Http\Controllers\Stream\Concerns\BuildsServiceStreams;
use App\Http\Controllers\Stream\Concerns\BuildsSupervisorStreams;
use App\Http\Controllers\StreamController;
use App\Models\Alert;
use App\Models\Service;
use App\Services\Jobs\JobServiceResolverService;
use App\Services\Metrics\Calculators\JobsThroughputMetricsCalculator;
use App\Services\Metrics\Calculators\WorkloadMetricsCalculator;
use App\Services\Metrics\MetricsDataService;
use App\Services\Queues\QueueOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HorizonStreamsController extends StreamController
{
    use BuildsAlertStreams;
    use BuildsBatchStreams;
    use BuildsDashboardStreams;
    use BuildsInstanceStreams;
    use BuildsJobListSectionStreams;
    use BuildsJobStreams;
    use BuildsMetricsStreams;
    use BuildsProviderStreams;
    use BuildsQueueStreams;
    use BuildsServiceStreams;
    use BuildsSupervisorStreams;

    /**
     * The throughput metrics calculator.
     */
    private JobsThroughputMetricsCalculator $jobsThroughputMetrics;

    /**
     * The metrics data service.
     */
    private MetricsDataService $metrics;

    /**
     * The queue overview service.
     */
    private QueueOverviewService $queueOverview;

    /**
     * The workload metrics calculator.
     */
    private WorkloadMetricsCalculator $workloadMetrics;

    /**
     * The constructor.
     */
    public function __construct(
        MetricsDataService $metrics,
        JobsThroughputMetricsCalculator $jobsThroughputMetrics,
        WorkloadMetricsCalculator $workloadMetrics,
        QueueOverviewService $queueOverview,
    ) {
        $this->metrics = $metrics;
        $this->jobsThroughputMetrics = $jobsThroughputMetrics;
        $this->workloadMetrics = $workloadMetrics;
        $this->queueOverview = $queueOverview;
    }

    public function alerts(Request $request): StreamedResponse
    {
        return $this->private__streamFromRequest($request, fn (string $query): string => $this->buildAlerts($query));
    }

    public function alertShow(Alert $alert): StreamedResponse
    {
        return $this->runStream(fn (): string => $this->buildAlertShow($alert));
    }

    public function batches(Request $request): StreamedResponse
    {
        return $this->private__streamFromRequest($request, fn (string $query): string => $this->buildBatches($query));
    }

    public function batchShow(Service $service, string $batch): StreamedResponse
    {
        return $this->runStream(fn (): string => $this->buildBatchShow($service, $batch));
    }

    public function dashboard(): StreamedResponse
    {
        return $this->runStream(fn (): string => $this->buildDashboard());
    }

    public function instances(Request $request): StreamedResponse
    {
        return $this->private__streamFromRequest($request, fn (string $query): string => $this->buildInstances($query));
    }

    public function jobs(Request $request): StreamedResponse
    {
        return $this->private__streamFromRequest($request, fn (string $query): string => $this->buildJobsIndex($query));
    }

    public function jobShow(string $job): StreamedResponse|JsonResponse
    {
        if (JobServiceResolverService::resolve($job) === null) {
            return response()->json(['message' => 'Job not found or no longer available.'], 404);
        }

        return $this->runStream(fn (): ?string => $this->buildJobShow($job));
    }

    public function metrics(Request $request): StreamedResponse
    {
        return $this->private__streamFromRequest($request, fn (string $query): string => $this->buildMetrics($query));
    }

    public function providerList(Request $request): StreamedResponse
    {
        return $this->private__streamFromRequest($request, fn (string $query): string => $this->buildProviders($query));
    }

    public function queues(Request $request): StreamedResponse
    {
        return $this->private__streamFromRequest($request, fn (string $query): string => $this->buildQueues($query));
    }

    public function queueShow(Request $request, string $queue): StreamedResponse
    {
        return $this->private__streamFromRequest($request, fn (string $query): string => $this->buildQueueShow($queue, $query));
    }

    public function serviceList(Request $request): StreamedResponse
    {
        return $this->private__streamFromRequest($request, fn (string $query): string => $this->buildServices($query));
    }

    public function serviceShow(Request $request, Service $service): StreamedResponse
    {
        return $this->private__streamFromRequest($request, fn (string $query): string => $this->buildServiceShow($service, $query));
    }

    public function supervisorShow(Service $service, string $supervisor): StreamedResponse
    {
        return $this->runStream(fn (): string => $this->buildSupervisorShow($service, $supervisor));
    }

    /**
     * Run a stream originating from a request query string.
     *
     * @param Request $request The request.
     * @param callable(string): string $builder The stream builder.
     */
    private function private__streamFromRequest(Request $request, callable $builder): StreamedResponse
    {
        $query = $request->getQueryString() ?? '';

        return $this->runStream(fn (): string => $builder($query));
    }
}
