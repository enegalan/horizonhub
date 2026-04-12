<?php

namespace Tests\Feature;

use App\Http\Controllers\Stream\HorizonStreamsController;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurboStreamSseTest extends TestCase
{
    use RefreshDatabase;

    public function test_streams_metrics_returns_sse_content_type(): void
    {
        $response = $this->get(route('horizon.streams.metrics'));

        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_streams_service_show_returns_sse_content_type(): void
    {
        $service = Service::create([
            'name' => 'test-svc',
            'base_url' => null,
            'api_key' => 'key123',
            'status' => 'online',
        ]);

        $response = $this->get(route('horizon.streams.service-show', ['service' => $service->id]));

        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
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
        $this->assertStringContainsString('target="metrics-workload-body"', $result);
        $this->assertStringContainsString('target="metrics-supervisors-body"', $result);
        $this->assertStringContainsString('action="update"', $result);
        $this->assertStringContainsString('action="replace"', $result);
    }

    public function test_build_queues_streams_returns_tbody_update(): void
    {
        Service::create([
            'name' => 'test-svc',
            'base_url' => null,
            'api_key' => 'key123',
            'status' => 'online',
        ]);

        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'private__buildQueuesStreams');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, '');

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="turbo-tbody-horizon-queue-list"', $result);
        $this->assertStringContainsString('action="update"', $result);
    }

    public function test_build_alerts_streams_returns_tbody_update(): void
    {
        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'private__buildAlertsStreams');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller);

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="turbo-tbody-horizon-alerts-list"', $result);
        $this->assertStringContainsString('action="update"', $result);
    }

    public function test_parse_service_ids_from_query(): void
    {
        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'private__parseServiceIdsFromQuery');
        $reflection->setAccessible(true);

        $this->assertSame([], $reflection->invoke($controller, ''));
        $this->assertSame([1, 3], $reflection->invoke($controller, 'service_id%5B0%5D=1&service_id%5B1%5D=3'));
    }

    public function test_build_jobs_index_streams_returns_jobs_stack_replace(): void
    {
        $controller = $this->app->make(HorizonStreamsController::class);

        $reflection = new \ReflectionMethod($controller, 'private__buildJobsIndexStreams');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($controller, '');

        $this->assertNotNull($result);
        $this->assertStringContainsString('target="horizon-jobs-stack"', $result);
        $this->assertStringContainsString('action="replace"', $result);
    }
}
