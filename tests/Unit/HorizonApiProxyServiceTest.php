<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HorizonApiProxyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_failed_jobs_retries_on_429_then_succeeds(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            if ($calls < 3) {
                return Http::response(['message' => 'Rate limited'], 429);
            }

            return Http::response(['data' => ['jobs' => []]], 200);
        });

        \config()->set('horizonhub.horizon_http_retry', [
            'times' => 3,
            'sleep_ms' => 0,
            'retry_on_status' => [429, 502, 503, 504],
        ]);
        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.failed_jobs', '/jobs/failed');

        $service = Service::create([
            'name' => 'svc-retry-429',
            'base_url' => 'https://service-retry-429.test',
            'status' => 'online',
        ]);

        $proxy = new HorizonApiProxyService;
        $result = $proxy->getFailedJobs($service, ['starting_at' => 0, 'limit' => 10]);

        $this->assertTrue($result['success']);
        $this->assertSame(3, $calls);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_get_failed_jobs_returns_trimmed_plain_text_error_message(): void
    {
        Http::fake([
            '*' => Http::response('Rate limited by upstream', 429, ['Content-Type' => 'text/plain']),
        ]);

        \config()->set('horizonhub.horizon_http_retry', [
            'times' => 1,
            'sleep_ms' => 100,
            'retry_on_status' => [429, 502, 503, 504],
        ]);
        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.failed_jobs', '/jobs/failed');

        $service = Service::create([
            'name' => 'svc-plain-text-error',
            'base_url' => 'https://service-plain-error.test',
            'status' => 'online',
        ]);

        $proxy = new HorizonApiProxyService;
        $result = $proxy->getFailedJobs($service, ['starting_at' => 0, 'limit' => 10]);

        $this->assertFalse($result['success']);
        $this->assertSame(429, $result['status'] ?? null);
        $this->assertSame('Rate limited by upstream', $result['message'] ?? null);
    }

    public function test_get_workload_retries_with_dashboard_session_after_unauthorized_response(): void
    {
        $apiCalls = 0;
        $dashboardCalls = 0;

        Http::fake(function ($request) use (&$apiCalls, &$dashboardCalls) {
            if ($request->method() === 'GET' && \str_ends_with($request->url(), '/horizon')) {
                $dashboardCalls++;

                return Http::response('<html><head><meta name="csrf-token" content="csrf-123"></head></html>', 200);
            }

            if ($request->method() === 'GET' && \str_ends_with($request->url(), '/horizon/api/workload')) {
                $apiCalls++;
                if ($apiCalls === 1) {
                    return Http::response(['message' => 'Unauthorized'], 401);
                }

                return Http::response(['data' => [['name' => 'redis.default', 'length' => 2]]], 200);
            }

            return Http::response('unexpected', 500);
        });

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.workload', '/workload');
        \config()->set('horizonhub.horizon_paths.dashboard', '/horizon');
        \config()->set('horizonhub.horizon_http_auth_statuses', [401, 403, 419]);

        $service = Service::create([
            'name' => 'svc-workload-fallback',
            'base_url' => 'https://service-workload.test',
            'status' => 'offline',
        ]);

        $proxy = new HorizonApiProxyService;
        $result = $proxy->getWorkload($service);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $apiCalls);
        $this->assertSame(1, $dashboardCalls);
        $this->assertSame('offline', $service->fresh()->status);
    }

    public function test_ping_returns_502_on_http_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('network down');
        });

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');

        $service = Service::create([
            'name' => 'svc-exception',
            'base_url' => 'https://service-exception.test',
            'status' => 'offline',
        ]);

        $proxy = new HorizonApiProxyService;
        $result = $proxy->ping($service);

        $this->assertFalse($result['success']);
        $this->assertSame(502, $result['status'] ?? null);
        $this->assertSame('network down', $result['message'] ?? null);
    }

    public function test_ping_success_updates_last_seen_at_and_status(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'ok'], 200),
        ]);

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');

        $service = Service::create([
            'name' => 'svc-heartbeat',
            'base_url' => 'https://service-heartbeat.test',
            'status' => 'offline',
            'last_seen_at' => null,
        ]);

        $proxy = new HorizonApiProxyService;
        $result = $proxy->ping($service);

        $freshService = $service->fresh();
        $this->assertTrue($result['success']);
        $this->assertSame('online', $freshService->status);
        $this->assertNotNull($freshService->last_seen_at);
    }

    public function test_get_call_enters_failure_cooldown_and_skips_immediate_retry(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return Http::response('Gateway Timeout', 504);
        });

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');
        \config()->set('horizonhub.horizon_http_failure_cooldown_seconds', 60);
        \config()->set('horizonhub.horizon_http_auth_statuses', [401, 403, 419]);
        \config()->set('horizonhub.horizon_http_retry', [
            'times' => 1,
            'sleep_ms' => 0,
            'retry_on_status' => [429, 502, 503, 504],
        ]);

        $service = Service::create([
            'name' => 'svc-cooldown',
            'base_url' => 'https://service-cooldown.test',
            'status' => 'online',
        ]);

        Cache::forget('horizonhub:horizon-api-failure-cooldown:'.$service->id);

        $proxy = new HorizonApiProxyService;

        $first = $proxy->ping($service);
        $second = $proxy->ping($service);

        $this->assertFalse($first['success']);
        $this->assertSame(504, $first['status'] ?? null);
        $this->assertFalse($second['success']);
        $this->assertSame(503, $second['status'] ?? null);
        $this->assertSame(1, $calls);
    }

    public function test_retry_job_retries_once_after_419_response(): void
    {
        $retryCalls = 0;
        $dashboardCalls = 0;

        Http::fake(function ($request) use (&$retryCalls, &$dashboardCalls) {
            if ($request->method() === 'GET' && \str_ends_with($request->url(), '/horizon')) {
                $dashboardCalls++;

                return Http::response('<html><head><meta name="csrf-token" content="csrf-419"></head></html>', 200);
            }

            if ($request->method() === 'POST' && \str_contains($request->url(), '/jobs/retry/')) {
                $retryCalls++;
                if ($retryCalls === 1) {
                    return Http::response(['message' => 'Page Expired'], 419);
                }

                return Http::response([], 200);
            }

            return Http::response('unexpected', 500);
        });

        \config()->set('horizonhub.horizon_paths.dashboard', '/horizon');
        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.retry', '/jobs/retry/{id}');

        $service = Service::create([
            'name' => 'svc-retry-419',
            'base_url' => 'https://service-retry.test',
            'status' => 'online',
        ]);

        $proxy = new HorizonApiProxyService;
        $result = $proxy->retryJob($service, 'job-uuid-419');

        $this->assertTrue($result['success']);
        $this->assertSame(2, $retryCalls);
        $this->assertSame(2, $dashboardCalls);
    }
}
