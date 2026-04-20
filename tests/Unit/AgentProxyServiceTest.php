<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgentProxyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_failed_jobs_surfaces_json_message_from_error_response(): void
    {
        Http::fake([
            '*' => Http::response('{"message":"Rate limited"}', 429, ['Content-Type' => 'application/json']),
        ]);

        \config()->set('horizonhub.horizon_http_retry', [
            'times' => 1,
            'sleep_ms' => 100,
            'retry_on_status' => [429, 502, 503, 504],
        ]);

        $service = Service::create([
            'name' => 'proxy-err',
            'base_url' => 'https://proxy-err.test',
            'status' => 'online',
        ]);

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.failed_jobs', '/jobs/failed');

        $proxy = new HorizonApiProxyService;
        $result = $proxy->getFailedJobs($service, ['starting_at' => 0, 'limit' => 5]);

        $this->assertFalse($result['success']);
        $this->assertSame(429, $result['status'] ?? null);
        $this->assertSame('Rate limited', $result['message'] ?? null);
    }

    public function test_ping_returns_error_when_service_has_no_base_url(): void
    {
        Http::fake();

        $service = Service::create([
            'name' => 'no-base',
            'base_url' => '',
            'status' => 'online',
        ]);

        \config()->set('horizonhub.horizon_paths.ping', '/stats');

        $proxy = new HorizonApiProxyService;
        $result = $proxy->ping($service);

        $this->assertFalse($result['success']);
        $this->assertSame(400, $result['status'] ?? null);
        $this->assertStringContainsString('base_url', (string) ($result['message'] ?? ''));
    }

    public function test_retry_job_calls_horizon_api_and_returns_success(): void
    {
        $capturedRetryRequest = null;

        Http::fake(function ($request) use (&$capturedRetryRequest) {
            if ($request->method() === 'GET' && \str_contains($request->url(), 'example.test/horizon')) {
                return Http::response(
                    '<html><head><meta name="csrf-token" content="test-csrf-token"></head></html>',
                    200
                );
            }
            if ($request->method() === 'POST' && \str_contains($request->url(), 'jobs/retry')) {
                $capturedRetryRequest = $request;

                return Http::response([], 200);
            }

            return Http::response('unexpected', 500);
        });

        $service = Service::create([
            'name' => 'svc',
            'base_url' => 'https://example.test',
            'status' => 'online',
        ]);

        \config()->set('horizonhub.horizon_paths.dashboard', '/horizon');
        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.retry', '/jobs/retry/{id}');

        $proxy = new HorizonApiProxyService;

        $result = $proxy->retryJob($service, 'job-uuid-1');

        $this->assertTrue($result['success']);
        $this->assertNotNull($capturedRetryRequest);
        $this->assertSame('https://example.test/horizon/api/jobs/retry/job-uuid-1', $capturedRetryRequest->url());
    }
}
