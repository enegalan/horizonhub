<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Jobs\JobServiceResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobServiceResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_resolve_caches_service_id_after_first_match(): void
    {
        config()->set('horizonhub.horizon_http_retry', ['times' => 1, 'sleep_ms' => 0, 'retry_on_status' => []]);

        Service::create(['name' => 'alpha', 'base_url' => 'https://alpha.test', 'status' => 'online']);
        $second = Service::create(['name' => 'beta', 'base_url' => 'https://beta.test', 'status' => 'online']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'https://alpha.test/horizon/api/jobs/job-uuid-1')) {
                return Http::response(['message' => 'not found'], 404);
            }

            if (str_contains($request->url(), 'https://beta.test/horizon/api/jobs/job-uuid-1')) {
                return Http::response(['id' => 'job-uuid-1'], 200);
            }

            return Http::response('unexpected', 500);
        });

        $resolved = JobServiceResolverService::resolve('job-uuid-1');

        $this->assertTrue($resolved['service']->is($second));
        $this->assertEquals($second->id, Cache::get('horizonhub:job-service:job-uuid-1'));

        Http::assertSentCount(2);
    }

    public function test_resolve_returns_null_for_blank_uuid(): void
    {
        Http::fake();

        $this->assertNull(JobServiceResolverService::resolve(''));
        Http::assertNothingSent();
    }

    public function test_resolve_returns_null_when_no_service_has_job(): void
    {
        config()->set('horizonhub.horizon_http_retry', ['times' => 1, 'sleep_ms' => 0, 'retry_on_status' => []]);

        Service::create(['name' => 'alpha', 'base_url' => 'https://alpha.test', 'status' => 'online']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'https://alpha.test/horizon/api/jobs/missing-job')) {
                return Http::response(['message' => 'not found'], 404);
            }

            return Http::response('unexpected', 500);
        });

        $this->assertNull(JobServiceResolverService::resolve('missing-job'));
    }

    public function test_resolve_uses_cached_service_id(): void
    {
        Service::create(['name' => 'alpha', 'base_url' => 'https://alpha.test', 'status' => 'online']);
        $second = Service::create(['name' => 'beta', 'base_url' => 'https://beta.test', 'status' => 'online']);

        Cache::forever('horizonhub:job-service:job-uuid-1', $second->id);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'https://beta.test/horizon/api/jobs/job-uuid-1')) {
                return Http::response(['id' => 'job-uuid-1'], 200);
            }

            return Http::response('unexpected', 500);
        });

        $this->assertTrue(JobServiceResolverService::resolve('job-uuid-1')['service']->is($second));
        Http::assertSentCount(1);
    }
}
