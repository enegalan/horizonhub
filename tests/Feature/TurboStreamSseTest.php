<?php

namespace Tests\Feature;

use App\Http\Controllers\Stream\HorizonStreamsController;
use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\AlertChartDataService;
use App\Services\Horizon\HorizonApiProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurboStreamSseTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_alert_show_streams_returns_chart_targets(): void
    {
        $alert = Alert::create([
            'name' => 'stream-alert-detail',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);

        $this->mock(AlertChartDataService::class, function ($mock): void {
            $mock->shouldReceive('buildChart')
                ->times(3)
                ->andReturn([
                    'xAxis' => [],
                    'sent' => [],
                    'failed' => [],
                ]);
        });

        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'private__buildAlertShowStreams');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, $alert);

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="alert-detail-stats" method="morph"', $result);
        $this->assertStringContainsString('target="alert-detail-chart-data"', $result);
        $this->assertStringContainsString('action="replace"', $result);
    }

    public function test_build_alerts_streams_returns_tbody_update(): void
    {
        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'private__buildAlertsStreams');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller);

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="turbo-tbody-horizon-alerts-list" method="morph"', $result);
        $this->assertStringContainsString('action="update"', $result);
    }

    public function test_build_dashboard_streams_returns_expected_targets(): void
    {
        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'private__buildDashboardStreams');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller);

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="dashboard-value-jobs-minute"', $result);
        $this->assertStringContainsString('target="dashboard-service-health-grid" method="morph"', $result);
        $this->assertStringContainsString('target="dashboard-recent-alerts-body" method="morph"', $result);
        $this->assertStringContainsString('target="dashboard-workload-summary-body" method="morph"', $result);
    }

    public function test_build_job_show_streams_returns_granular_detail_updates(): void
    {
        $service = Service::create([
            'name' => 'stream-job-svc',
            'base_url' => 'https://horizon-api-stream-job.test',
            'status' => 'online',
        ]);

        $jobUuid = '763dc9c2-a7cd-4b95-9da5-77beff5c264e';

        $this->mock(HorizonApiProxyService::class, function ($mock) use ($jobUuid): void {
            $mock->shouldReceive('getJob')
                ->zeroOrMoreTimes()
                ->andReturn([
                    'success' => true,
                    'data' => [
                        'id' => $jobUuid,
                        'name' => 'App\\Jobs\\Demo',
                        'queue' => 'default',
                        'status' => 'failed',
                        'payload' => [],
                        'connection' => 'database',
                    ],
                ]);
        });

        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'private__buildJobShowStreams');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, $jobUuid, (int) $service->id);

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="horizon-job-detail-meta"', $result);
        $this->assertStringContainsString('target="horizon-job-detail-actions-stream"', $result);
        $this->assertStringContainsString('action="update"', $result);
    }

    public function test_build_jobs_index_streams_returns_per_section_tbody_updates(): void
    {
        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'private__buildJobsIndexStreams');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, '');

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="turbo-tbody-horizon-job-list-processing" method="morph"', $result);
        $this->assertStringContainsString('target="job-count-horizon-job-list-processing"', $result);
        $this->assertStringContainsString('target="job-pagination-horizon-job-list-processing"', $result);
        $this->assertStringContainsString('action="update"', $result);
        $this->assertStringNotContainsString('target="horizon-jobs-stack"', $result);
    }

    public function test_build_metrics_streams_returns_granular_targets(): void
    {
        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'private__buildMetricsStreams');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, '');

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="metrics-value-jobs-minute"', $result);
        $this->assertStringContainsString('target="metrics-value-jobs-hour"', $result);
        $this->assertStringContainsString('target="metrics-value-failed-seven"', $result);
        $this->assertStringContainsString('target="metrics-chart-data"', $result);
        $this->assertStringContainsString('target="metrics-workload-body" method="morph"', $result);
        $this->assertStringContainsString('target="metrics-supervisors-body" method="morph"', $result);
        $this->assertStringContainsString('action="update"', $result);
        $this->assertStringContainsString('action="replace"', $result);
    }

    public function test_build_queues_streams_returns_tbody_update(): void
    {
        Service::create([
            'name' => 'test-svc',
            'base_url' => '',
            'status' => 'online',
        ]);

        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'private__buildQueuesStreams');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, '');

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="turbo-tbody-horizon-queue-list" method="morph"', $result);
        $this->assertStringContainsString('action="update"', $result);
    }

    public function test_build_services_streams_returns_tbody_morph_update(): void
    {
        Service::create([
            'name' => 'test-svc-stream',
            'base_url' => '',
            'status' => 'online',
        ]);

        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'private__buildServicesStreams');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller);

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="turbo-tbody-horizon-service-list" method="morph"', $result);
        $this->assertStringContainsString('action="update"', $result);
    }

    public function test_services_index_marks_stream_patch_children_on_list_container(): void
    {
        Service::create([
            'name' => 'merge-markup-svc',
            'base_url' => '',
            'status' => 'online',
        ]);

        $response = $this->get(route('horizon.services.index'));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('data-turbo-stream-patch-children="true"', $html);
        $this->assertStringContainsString('data-stream-row-id="svc-', $html);
    }

    public function test_streams_alert_show_returns_sse_content_type(): void
    {
        $alert = Alert::create([
            'name' => 'stream-alert-detail-sse',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);

        $response = $this->get(route('horizon.streams.alerts.show', ['alert' => $alert->id]));

        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_streams_dashboard_returns_sse_content_type(): void
    {
        $response = $this->get(route('horizon.streams.dashboard'));

        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_streams_job_show_returns_sse_content_type_when_resolvable(): void
    {
        $service = Service::create([
            'name' => 'stream-job-svc-2',
            'base_url' => 'https://horizon-api-stream-job-2.test',
            'status' => 'online',
        ]);

        $jobUuid = '863dc9c2-a7cd-4b95-9da5-77beff5c264e';

        $this->mock(HorizonApiProxyService::class, function ($mock) use ($jobUuid): void {
            $mock->shouldReceive('getJob')
                ->zeroOrMoreTimes()
                ->andReturn([
                    'success' => true,
                    'data' => [
                        'id' => $jobUuid,
                        'name' => 'App\\Jobs\\Demo',
                        'queue' => 'default',
                        'status' => 'failed',
                        'payload' => [],
                    ],
                ]);
        });

        $response = $this->get('/horizon/streams/horizon/jobs/' . $jobUuid . '?service_id=' . $service->id);

        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_streams_metrics_returns_sse_content_type(): void
    {
        $response = $this->get(route('horizon.streams.metrics'));

        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_streams_service_show_returns_sse_content_type(): void
    {
        $service = Service::create([
            'name' => 'test-svc',
            'base_url' => '',
            'status' => 'online',
        ]);

        $response = $this->get(route('horizon.streams.service-show', ['service' => $service->id]));

        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }
}
