<?php

namespace Tests\Feature;

use App\Http\Controllers\Stream\HorizonStreamsController;
use App\Http\Controllers\StreamController;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\Alerts\AlertChartDataService;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Horizon\HorizonClientService;
use App\Services\Notifiers\EmailNotifierService;
use App\Services\Notifiers\SlackNotifierService;
use App\Services\Services\ServiceFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurboStreamSseTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_alert_show_streams_returns_chart_targets(): void
    {
        $alert = Alert::create([
            'name' => 'stream-alert-detail',
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);

        $this->mock(AlertChartDataService::class, function ($mock): void {
            $mock->shouldReceive('buildChart')
                ->andReturn([
                    'xAxis' => [],
                    'sent' => [],
                    'failed' => [],
                ]);
        });

        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'buildAlertShow');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, $alert);

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="alert-detail-stats" method="morph"', $result);
        $this->assertStringContainsString('target="alert-detail-chart-data"', $result);
        $this->assertStringContainsString('action="replace"', $result);
    }

    public function test_build_alerts_streams_returns_tbody_update(): void
    {
        $includedService = Service::create([
            'name' => 'alpha-service',
            'base_url' => 'https://alpha.test',
            'status' => 'online',
        ]);

        Alert::create([
            'name' => 'enabled-alert',
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
            'service_ids' => [$includedService->id, 9999],
        ]);
        Alert::create([
            'name' => 'disabled-alert',
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => false,
            'service_ids' => [],
        ]);

        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'buildAlerts');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller);

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="turbo-horizon-alert-stats" method="morph"', $result);
        $this->assertStringContainsString('target="turbo-tbody-horizon-alerts-list" method="morph"', $result);
        $this->assertStringContainsString('action="update"', $result);
        $this->assertStringContainsString('alpha-service', $result);
        $this->assertStringContainsString('enabled-alert', $result);
        $this->assertStringContainsString('disabled-alert', $result);
        $this->assertMatchesRegularExpression('/Total.*?<span>2<\/span>/s', $result);
        $this->assertMatchesRegularExpression('/Enabled.*?<span>1<\/span>/s', $result);
        $this->assertMatchesRegularExpression('/Disabled.*?<span>1<\/span>/s', $result);
    }

    public function test_build_dashboard_streams_returns_expected_targets(): void
    {
        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'buildDashboard');
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

        $this->mock(HorizonClientService::class, function ($mock) use ($jobUuid): void {
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

        $reflection = new \ReflectionMethod($controller, 'buildJobShow');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, $jobUuid);

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="horizon-job-detail-meta"', $result);
        $this->assertStringContainsString('target="horizon-job-detail-actions-stream"', $result);
        $this->assertStringContainsString('data-job-detail-retry-url', $result);
        $this->assertStringContainsString('action="update"', $result);
    }

    public function test_build_job_show_streams_returns_null_for_missing_service_or_api_failure(): void
    {
        $controller = $this->app->make(HorizonStreamsController::class);
        $reflection = new \ReflectionMethod($controller, 'buildJobShow');
        $reflection->setAccessible(true);

        $this->assertNull($reflection->invoke($controller, 'uuid-x'));

        Service::create([
            'name' => 'stream-job-null',
            'base_url' => 'https://horizon-api-stream-null.test',
            'status' => 'online',
        ]);
        $this->mock(HorizonClientService::class, function ($mock): void {
            $mock->shouldReceive('getJob')->andReturn(['success' => false]);
        });
        $controller = $this->app->make(HorizonStreamsController::class);
        $reflection = new \ReflectionMethod($controller, 'buildJobShow');
        $reflection->setAccessible(true);

        $this->assertNull($reflection->invoke($controller, 'uuid-y'));
    }

    public function test_build_jobs_index_streams_returns_per_section_tbody_updates(): void
    {
        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'buildJobsIndex');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, '');

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="turbo-tbody-horizon-job-list-processing" method="morph"', $result);
        $this->assertStringContainsString('target="job-count-horizon-job-list-processing"', $result);
        $this->assertStringContainsString('target="job-pagination-horizon-job-list-processing"', $result);
        $this->assertStringContainsString('action="update"', $result);
        $this->assertStringNotContainsString('target="horizon-jobs-stack"', $result);
    }

    public function test_build_jobs_index_streams_with_query_preserves_section_updates(): void
    {
        $controller = $this->app->make(HorizonStreamsController::class);
        $reflection = new \ReflectionMethod($controller, 'buildJobsIndex');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, 'search=abc');

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="turbo-tbody-horizon-job-list-failed"', $result);
        $this->assertStringContainsString('target="job-pagination-horizon-job-list-processed"', $result);
    }

    public function test_build_metrics_streams_returns_granular_targets(): void
    {
        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'buildMetrics');
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

    public function test_build_providers_streams_counts_emitted_alert_logs_by_provider_type(): void
    {
        $service = Service::create([
            'name' => 'stats-svc',
            'base_url' => 'https://stats.test',
            'status' => 'online',
        ]);

        $slackProvider = NotificationProvider::query()->create([
            'name' => 'slack-stats',
            'type' => SlackNotifierService::type(),
            'config' => ['webhook_url' => 'https://hooks.slack.test/services/T/B'],
        ]);
        $emailProvider = NotificationProvider::query()->create([
            'name' => 'email-stats',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['ops@example.test']],
        ]);

        $slackAlert = Alert::create([
            'name' => 'slack-alert',
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);
        $slackAlert->notificationProviders()->sync([$slackProvider->id]);

        $dualAlert = Alert::create([
            'name' => 'dual-alert',
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);
        $dualAlert->notificationProviders()->sync([$slackProvider->id, $emailProvider->id]);

        $emailOnlyAlert = Alert::create([
            'name' => 'email-alert',
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);
        $emailOnlyAlert->notificationProviders()->sync([$emailProvider->id]);

        foreach ([$slackAlert, $dualAlert, $emailOnlyAlert] as $alert) {
            AlertLog::create([
                'alert_id' => $alert->id,
                'service_id' => $service->id,
                'trigger_count' => 1,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }

        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'buildProviders');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller);

        $this->assertNotNull($result);
        $this->assertMatchesRegularExpression('/Total.*?<span>3<\/span>/s', $result);
        $this->assertMatchesRegularExpression('/Slack.*?<span>2<\/span>/s', $result);
        $this->assertMatchesRegularExpression('/Email.*?<span>2<\/span>/s', $result);
        $this->assertMatchesRegularExpression('/Discord.*?<span>0<\/span>/s', $result);
    }

    public function test_build_providers_streams_returns_tbody_morph_update(): void
    {
        NotificationProvider::query()->create([
            'name' => 'stream-provider',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['ops@example.test']],
        ]);

        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'buildProviders');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller);

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="turbo-horizon-provider-stats" method="morph"', $result);
        $this->assertStringContainsString('target="turbo-tbody-horizon-provider-list" method="morph"', $result);
        $this->assertStringContainsString('action="update"', $result);
        $this->assertStringContainsString('stream-provider', $result);
    }

    public function test_build_queues_streams_returns_tbody_update(): void
    {
        Service::create([
            'name' => 'test-svc',
            'base_url' => 'https://test-svc.test',
            'status' => 'online',
        ]);

        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'buildQueues');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, '');

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="turbo-horizon-queue-stats" method="morph"', $result);
        $this->assertStringContainsString('target="turbo-tbody-horizon-queue-list" method="morph"', $result);
        $this->assertStringContainsString('action="update"', $result);
    }

    public function test_build_service_show_streams_returns_expected_targets(): void
    {
        $service = Service::create([
            'name' => 'svc-stream-show',
            'base_url' => 'https://svc-stream-show.test',
            'status' => 'online',
        ]);

        $controller = $this->app->make(HorizonStreamsController::class);
        $reflection = new \ReflectionMethod($controller, 'buildServiceShow');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($controller, $service, '');

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="service-show-stats-row-1"', $result);
        $this->assertStringContainsString('target="service-show-workload-body" method="morph"', $result);
        $this->assertStringContainsString('target="job-count-horizon-service-dashboard-jobs-processing"', $result);
    }

    public function test_build_services_streams_returns_tbody_morph_update(): void
    {
        Service::create([
            'name' => 'test-svc-stream',
            'base_url' => 'https://test-svc.test',
            'status' => 'online',
        ]);

        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'buildServices');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, '');

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="turbo-horizon-service-stats" method="morph"', $result);
        $this->assertStringContainsString('target="turbo-tbody-horizon-service-list" method="morph"', $result);
        $this->assertStringContainsString('action="update"', $result);
        $this->assertStringContainsString('data-horizon-stream-sig="', $result);
    }

    public function test_parse_service_ids_and_turbo_stream_tag_helper_branches(): void
    {
        $controller = $this->app->make(HorizonStreamsController::class);
        $filter = $this->app->make(ServiceFilterService::class);

        $this->assertSame([], $filter->resolveFromQuery(''));
        $this->assertSame([], $filter->resolveFromQuery('service_id=abc'));

        $service = Service::create([
            'name' => 'parse-svc',
            'base_url' => 'https://parse.test',
            'status' => 'online',
        ]);
        $this->assertSame([$service->id], $filter->resolveFromQuery('service_id[]=' . $service->id));

        $service->update(['tags' => ['production']]);
        $this->assertSame([$service->id], $filter->resolveFromQuery('service_tag[]=production'));

        $tag = new \ReflectionMethod(StreamController::class, 'private__turboStreamTag');
        $tag->setAccessible(true);
        $withoutMethod = $tag->invoke($controller, 'update', 'x', '<div>a</div>');
        $withMethod = $tag->invoke($controller, 'update', 'x', '<div>a</div>', 'morph');

        $this->assertStringNotContainsString('method="', $withoutMethod);
        $this->assertStringContainsString('method="morph"', $withMethod);
    }

    public function test_providers_index_marks_stream_patch_children_on_list_container(): void
    {
        $response = $this->get(route('horizon.providers.index'));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('data-turbo-stream-patch-children="true"', $html);
        $this->assertStringContainsString('id="turbo-horizon-provider-stats"', $html);
        $this->assertStringContainsString('id="turbo-tbody-horizon-provider-list"', $html);
    }

    public function test_services_index_marks_stream_patch_children_on_list_container(): void
    {
        Service::create([
            'name' => 'merge-markup-svc',
            'base_url' => 'https://test-svc.test',
            'status' => 'online',
        ]);

        $response = $this->get(route('horizon.services.index'));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('data-turbo-stream-patch-children="true"', $html);
        $this->assertStringContainsString('id="turbo-horizon-service-stats"', $html);
        $this->assertStringContainsString('id="turbo-tbody-horizon-service-list"', $html);
    }

    public function test_streams_alert_show_returns_sse_content_type(): void
    {
        $alert = Alert::create([
            'name' => 'stream-alert-detail-sse',
            'rule_type' => FailureCount::type(),
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

        $this->mock(HorizonClientService::class, function ($mock) use ($jobUuid): void {
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

        $response = $this->get('/horizon/streams/horizon/jobs/' . $jobUuid);

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
            'base_url' => 'https://test-svc.test',
            'status' => 'online',
        ]);

        $response = $this->get(route('horizon.streams.service-show', ['service' => $service->id]));

        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }
}
