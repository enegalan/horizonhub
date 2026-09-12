<?php

namespace Tests\Unit;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Services\Horizon\HorizonClientCacheService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HorizonClientTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_concurrency_limit_disabled_when_max_concurrent_is_zero(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true], 200),
        ]);

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');
        \config()->set('horizonhub.horizon_http_max_concurrent_requests_per_service', 0);

        $service = Service::create([
            'name' => 'svc-concurrency-disabled',
            'base_url' => 'https://service-concurrency-disabled.test',
            'status' => 'online',
        ]);

        // Seed the slot key with count 1 to verify that a disabled limit (max=0)
        // bypasses slot acquisition regardless of existing cache state.
        Cache::put(HorizonClientCacheService::serviceRequestSlotCacheKey($service), 1, \now()->addSeconds(30));

        $result = HorizonClientApiService::getStats($service);

        $this->assertTrue($result['success']);
        Http::assertSentCount(1);
    }

    public function test_connection_errors_without_timeout_do_not_set_timeout_advice(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.stats', '/stats');

        $service = Service::create([
            'name' => 'svc-conn-refused',
            'base_url' => 'https://service-conn-refused.test',
            'status' => 'online',
        ]);

        $result = HorizonClientApiService::getStats($service);

        $this->assertFalse($result['success']);
        $this->assertSame(502, $result['status'] ?? null);
        $this->assertFalse(Cache::has(HorizonClientCacheService::timeoutAdviceCacheKey($service)));
        $this->assertFalse($service->hasTimeoutAdvice());
    }

    public function test_dashboard_bootstrap_sends_service_headers(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET' && \str_ends_with($request->url(), '/horizon')) {
                $this->assertContains('service-key', $request->header('X-Api-Key'));

                return Http::response('<html><head><meta name="csrf-token" content="csrf-token"></head></html>', 200);
            }

            return Http::response(['ok' => true], 200);
        });

        \config()->set('horizonhub.horizon_paths.dashboard', '/horizon');
        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.retry', '/jobs/retry/{id}');

        $service = Service::create([
            'name' => 'svc-dashboard-headers',
            'base_url' => 'https://service-dashboard-headers.test',
            'status' => 'online',
        ]);

        $service->headers()->create([
            'name' => 'X-Api-Key',
            'value' => 'service-key',
        ]);

        $result = HorizonClientApiService::retryJob($service, 'job-uuid');

        $this->assertTrue($result['success']);
    }

    public function test_failed_response_prefers_json_message_and_html_fallback_message(): void
    {
        Http::fake(function ($request) {
            if (\str_contains($request->url(), 'json-message')) {
                return Http::response(['message' => 'json boom'], 500);
            }

            return Http::response('<html><body>Error</body></html>', 500);
        });

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/json-message');

        $service = Service::create([
            'name' => 'svc-error-msg',
            'base_url' => 'https://service-errors.test',
            'status' => 'online',
        ]);

        $jsonResult = HorizonClientApiService::ping($service);
        $this->assertFalse($jsonResult['success']);
        $this->assertSame('json boom', $jsonResult['message']);

        \config()->set('horizonhub.horizon_paths.ping', '/html-message');
        Cache::forget(HorizonClientCacheService::failureCooldownCacheKey($service));
        $htmlResult = HorizonClientApiService::ping($service);
        $this->assertFalse($htmlResult['success']);
        $this->assertStringContainsString('Horizon API returned an HTTP error', (string) $htmlResult['message']);
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

        Cache::forget(HorizonClientCacheService::failureCooldownCacheKey($service));

        $first = HorizonClientApiService::getStats($service);
        $second = HorizonClientApiService::getStats($service);

        $this->assertFalse($first['success']);
        $this->assertSame(504, $first['status'] ?? null);
        $this->assertFalse($second['success']);
        $this->assertSame(503, $second['status'] ?? null);
        $this->assertSame(1, $calls);
    }

    public function test_get_does_not_retry_connection_exceptions(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            throw new ConnectionException('upstream timeout');
        });

        \config()->set('horizonhub.horizon_http_retry', [
            'times' => 3,
            'sleep_ms' => 0,
            'retry_on_status' => [429, 502, 503, 504],
        ]);
        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.stats', '/stats');
        \config()->set('horizonhub.horizon_http_failure_cooldown_seconds', 0);

        $service = Service::create([
            'name' => 'svc-no-conn-retry',
            'base_url' => 'https://service-no-conn-retry.test',
            'status' => 'online',
        ]);

        $result = HorizonClientApiService::getStats($service);

        $this->assertFalse($result['success']);
        $this->assertSame(502, $result['status'] ?? null);
        $this->assertSame(1, $calls);
    }

    public function test_get_failed_jobs_calls_horizon_api_jobs_failed_path(): void
    {
        Http::fake([
            'https://service-failed-path.test/horizon/api/jobs/failed*' => Http::response(['jobs' => []], 200),
        ]);

        $service = Service::create([
            'name' => 'svc-failed-path',
            'base_url' => 'https://service-failed-path.test',
            'status' => 'online',
        ]);

        $result = HorizonClientApiService::getFailedJobs($service, ['starting_at' => 0, 'limit' => 10]);

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://service-failed-path.test/horizon/api/jobs/failed?starting_at=0&limit=10';
        });
    }

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

        $result = HorizonClientApiService::getFailedJobs($service, ['starting_at' => 0, 'limit' => 10]);

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

        $result = HorizonClientApiService::getFailedJobs($service, ['starting_at' => 0, 'limit' => 10]);

        $this->assertFalse($result['success']);
        $this->assertSame(429, $result['status'] ?? null);
        $this->assertSame('Rate limited by upstream', $result['message'] ?? null);
    }

    public function test_get_refreshes_after_hot_reload_interval_elapsed(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return Http::response(['failedJobs' => $calls, 'recentJobs' => 2], 200);
        });

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');
        \config()->set('horizonhub.hot_reload_interval', 1.0);

        $service = Service::create([
            'name' => 'svc-hot-reload-expiry',
            'base_url' => 'https://service-hot-reload-expiry.test',
            'status' => 'online',
        ]);

        Cache::flush();

        $first = HorizonClientApiService::getStats($service);
        $this->assertTrue($first['success']);
        $this->assertSame(1, (int) ($first['data']['failedJobs'] ?? 0));

        \sleep(2);

        $second = HorizonClientApiService::getStats($service);
        $this->assertTrue($second['success']);
        $this->assertSame(2, (int) ($second['data']['failedJobs'] ?? 0));
        $this->assertSame(2, $calls);
    }

    public function test_get_releases_concurrency_slot_after_request(): void
    {
        Http::fake([
            '*' => Http::response(['failedJobs' => 1], 200),
        ]);

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');

        $service = Service::create([
            'name' => 'svc-slot-release',
            'base_url' => 'https://service-slot-release.test',
            'status' => 'online',
        ]);

        $this->assertTrue(HorizonClientApiService::getStats($service)['success']);

        $this->assertFalse(Cache::has(HorizonClientCacheService::serviceRequestSlotCacheKey($service)));

        $this->assertTrue(HorizonClientApiService::getStats($service)['success']);
        Http::assertSentCount(1);
    }

    public function test_get_returns_503_when_path_fill_lock_cannot_be_acquired(): void
    {
        Http::fake([
            'https://service-fill-lock.test/horizon/api/stats' => Http::response(['jobsPerMinute' => 1], 200),
        ]);

        \config()->set('horizonhub.api_timeout', 1);
        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');

        $service = Service::create([
            'name' => 'svc-fill-lock',
            'base_url' => 'https://service-fill-lock.test',
            'status' => 'online',
        ]);

        $lock = Cache::lock(HorizonClientCacheService::requestPathCacheKey($service, '/stats') . ':fill', 30);
        $this->assertTrue($lock->get());

        try {
            $result = HorizonClientApiService::getStats($service);

            $this->assertFalse($result['success']);
            $this->assertSame(503, $result['status'] ?? null);
            $this->assertSame('Horizon API request coalescing timed out.', $result['message'] ?? null);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_get_returns_503_when_service_concurrency_limit_reached(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true], 200),
        ]);

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');
        \config()->set('horizonhub.horizon_http_max_concurrent_requests_per_service', 1);
        \config()->set('horizonhub.horizon_http_concurrent_request_wait_ms', 50);

        $service = Service::create([
            'name' => 'svc-concurrency-limit',
            'base_url' => 'https://service-concurrency.test',
            'status' => 'online',
        ]);

        // Simulate an in-flight request holding the only concurrency slot.
        Cache::add(HorizonClientCacheService::serviceRequestSlotCacheKey($service), 0, \now()->addSeconds(30));
        Cache::increment(HorizonClientCacheService::serviceRequestSlotCacheKey($service));

        $result = HorizonClientApiService::getStats($service);

        $this->assertFalse($result['success']);
        $this->assertSame(503, $result['status'] ?? null);
        $this->assertSame('Service concurrent request limit reached.', $result['message'] ?? null);
        Http::assertNothingSent();
    }

    public function test_get_reuses_successful_response_within_hot_reload_interval(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return Http::response(['failedJobs' => 1, 'recentJobs' => 2], 200);
        });

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');
        \config()->set('horizonhub.hot_reload_interval', 1.0);

        $service = Service::create([
            'name' => 'svc-hot-reload-cache',
            'base_url' => 'https://service-hot-reload-cache.test',
            'status' => 'online',
        ]);

        Cache::flush();

        $this->assertTrue(HorizonClientApiService::getStats($service)['success']);
        $this->assertTrue(HorizonClientApiService::getStats($service)['success']);
        $this->assertSame(1, $calls);
    }

    public function test_get_stats_returns_error_when_service_is_disabled(): void
    {
        $service = Service::create([
            'name' => 'svc-disabled',
            'base_url' => 'https://disabled.test',
            'status' => 'online',
            'enabled' => false,
        ]);

        Http::fake();

        $result = HorizonClientApiService::getStats($service);

        $this->assertFalse($result['success']);
        $this->assertSame('Service is disabled.', $result['message']);
        Http::assertNothingSent();
    }

    public function test_get_timeout_sets_timeout_advice_and_clears_it_on_next_success(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            if ($calls === 1) {
                throw new ConnectionException('cURL error 28: Operation timed out after 10001 milliseconds with 0 bytes received');
            }

            return Http::response(['ok' => true], 200);
        });

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');
        \config()->set('horizonhub.horizon_http_failure_cooldown_seconds', 60);

        $service = Service::create([
            'name' => 'svc-timeout-advice',
            'base_url' => 'https://service-timeout-advice.test',
            'status' => 'online',
        ]);

        $first = HorizonClientApiService::getStats($service);
        $this->assertFalse($first['success']);
        $this->assertSame(502, $first['status'] ?? null);
        $this->assertTrue(Cache::has(HorizonClientCacheService::timeoutAdviceCacheKey($service)));
        $this->assertTrue($service->hasTimeoutAdvice());

        Cache::forget(HorizonClientCacheService::failureCooldownCacheKey($service));

        $second = HorizonClientApiService::getStats($service);
        $this->assertTrue($second['success']);
        $this->assertFalse(Cache::has(HorizonClientCacheService::timeoutAdviceCacheKey($service)));
        $this->assertFalse($service->hasTimeoutAdvice());
    }

    public function test_get_workload_returns_unauthorized_without_dashboard_bootstrap(): void
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

                return Http::response(['message' => 'Unauthorized'], 401);
            }

            return Http::response('unexpected', 500);
        });

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.workload', '/workload');
        \config()->set('horizonhub.horizon_paths.dashboard', '/horizon');
        \config()->set('horizonhub.horizon_http_auth_statuses', [401, 403, 419]);

        $service = Service::create([
            'name' => 'svc-workload-unauthorized',
            'base_url' => 'https://service-workload.test',
            'status' => 'offline',
        ]);

        $result = HorizonClientApiService::getWorkload($service);

        $this->assertFalse($result['success']);
        $this->assertSame(401, $result['status']);
        $this->assertSame(1, $apiCalls);
        $this->assertSame(0, $dashboardCalls);
        $this->assertSame(ServiceStatus::Offline, $service->fresh()->status);
    }

    public function test_ping_always_bypasses_failure_cooldown_and_hits_upstream_for_diagnostics(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return Http::response('Gateway Timeout', 504);
        });

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');
        \config()->set('horizonhub.horizon_http_failure_cooldown_seconds', 60);
        \config()->set('horizonhub.horizon_http_retry', [
            'times' => 1,
            'sleep_ms' => 0,
            'retry_on_status' => [429, 502, 503, 504],
        ]);

        $service = Service::create([
            'name' => 'svc-cooldown-bypass',
            'base_url' => 'https://service-cooldown-bypass.test',
            'status' => 'online',
        ]);

        Cache::put(HorizonClientCacheService::failureCooldownCacheKey($service), true, \now()->addMinutes(1));

        $pingResult = HorizonClientApiService::ping($service);

        $this->assertFalse($pingResult['success']);
        $this->assertSame(504, $pingResult['status'] ?? null);
        $this->assertGreaterThan(0, $calls);
    }

    public function test_ping_returns_500_for_generic_runtime_exception(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('generic fail');
        });

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');

        $service = Service::create([
            'name' => 'svc-runtime-exception',
            'base_url' => 'https://service-runtime-exception.test',
            'status' => 'offline',
        ]);

        $result = HorizonClientApiService::ping($service);

        $this->assertFalse($result['success']);
        $this->assertSame(500, $result['status'] ?? null);
        $this->assertSame('generic fail', $result['message'] ?? null);
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

        $result = HorizonClientApiService::ping($service);

        $this->assertFalse($result['success']);
        $this->assertSame(502, $result['status'] ?? null);
        $this->assertSame('network down', $result['message'] ?? null);
    }

    public function test_ping_sends_configured_service_headers(): void
    {
        Http::fake(function ($request) {
            $this->assertContains('Bearer test-token', $request->header('Authorization'));

            return Http::response(['ok' => true], 200);
        });

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');

        $service = Service::create([
            'name' => 'svc-custom-headers',
            'base_url' => 'https://service-headers.test',
            'status' => 'online',
        ]);

        $service->headers()->create([
            'name' => 'Authorization',
            'value' => 'Bearer test-token',
        ]);

        $result = HorizonClientApiService::ping($service);

        $this->assertTrue($result['success']);
    }

    public function test_ping_still_works_when_service_is_disabled(): void
    {
        Http::fake(fn () => Http::response(['ok' => true], 200));

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');

        $service = Service::create([
            'name' => 'svc-disabled-ping',
            'base_url' => 'https://disabled-ping.test',
            'status' => 'offline',
            'enabled' => false,
        ]);

        $result = HorizonClientApiService::ping($service);

        $this->assertTrue($result['success']);
        Http::assertSentCount(1);
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

        $result = HorizonClientApiService::ping($service);

        $freshService = $service->fresh();
        $this->assertTrue($result['success']);
        $this->assertSame(ServiceStatus::Online, $freshService->status);
        $this->assertNotNull($freshService->last_seen_at);
    }

    public function test_public_get_wrappers_build_expected_paths_and_queries(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true], 200),
        ]);

        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.completed_jobs', '/jobs/completed');
        \config()->set('horizonhub.horizon_paths.pending_jobs', '/jobs/pending');
        \config()->set('horizonhub.horizon_paths.failed_jobs', '/jobs/failed');
        \config()->set('horizonhub.horizon_paths.job', '/jobs/{id}');
        \config()->set('horizonhub.horizon_paths.masters', '/masters');
        \config()->set('horizonhub.horizon_paths.ping', '/stats');
        \config()->set('horizonhub.horizon_api_job_list_page_size', 25);

        $service = Service::create([
            'name' => 'svc-wrappers',
            'base_url' => 'https://service-wrappers.test',
            'status' => 'online',
        ]);

        $this->assertTrue(HorizonClientApiService::getCompletedJobs($service)['success']);
        $this->assertTrue(HorizonClientApiService::getPendingJobs($service, ['starting_at' => 5])['success']);
        $this->assertTrue(HorizonClientApiService::getFailedJobs($service)['success']);
        $this->assertTrue(HorizonClientApiService::getJob($service, 'uuid-1')['success']);
        $this->assertTrue(HorizonClientApiService::getMasters($service)['success']);
        $this->assertTrue(HorizonClientApiService::getStats($service)['success']);
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

        $result = HorizonClientApiService::retryJob($service, 'job-uuid-419');

        $this->assertTrue($result['success']);
        $this->assertSame(2, $retryCalls);
        $this->assertSame(2, $dashboardCalls);
    }

    public function test_retry_job_returns_502_when_dashboard_bootstrap_fails(): void
    {
        Http::fake([
            '*' => Http::response('<html><head></head><body>no csrf</body></html>', 200),
        ]);

        \config()->set('horizonhub.horizon_paths.dashboard', '/horizon');
        \config()->set('horizonhub.horizon_paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon_paths.retry', '/jobs/retry/{id}');

        $service = Service::create([
            'name' => 'svc-bootstrap-fail',
            'base_url' => 'https://service-bootstrap-fail.test',
            'status' => 'online',
        ]);

        $result = HorizonClientApiService::retryJob($service, 'uuid-bootstrap');

        $this->assertFalse($result['success']);
        $this->assertSame(502, $result['status'] ?? null);
        $this->assertStringContainsString('Unable to bootstrap Horizon dashboard session', (string) $result['message']);
    }
}
