<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonClientService;
use App\Services\Jobs\JobServiceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class JobServiceResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_resolve_caches_service_id_after_first_match(): void
    {
        $first = Service::create(['name' => 'alpha', 'base_url' => 'https://alpha.test', 'status' => 'online']);
        $second = Service::create(['name' => 'beta', 'base_url' => 'https://beta.test', 'status' => 'online']);

        $api = $this->createMock(HorizonClientService::class);
        $api->expects($this->exactly(2))
            ->method('getJob')
            ->willReturnCallback(function (Service $service) use ($first): array {
                if ($service->is($first)) {
                    return ['success' => false];
                }

                return [
                    'success' => true,
                    'data' => ['id' => 'job-uuid-1'],
                ];
            });

        $resolver = new JobServiceResolver($api);
        $resolved = $resolver->resolve('job-uuid-1');

        $this->assertTrue($resolved['service']->is($second));
        $this->assertEquals($second->id, Cache::get('horizonhub:job-service:job-uuid-1'));
    }

    public function test_resolve_returns_null_for_blank_uuid(): void
    {
        $api = $this->createMock(HorizonClientService::class);
        $api->expects($this->never())->method('getJob');

        $resolver = new JobServiceResolver($api);

        $this->assertNull($resolver->resolve(''));
    }

    public function test_resolve_returns_null_when_no_service_has_job(): void
    {
        Service::create(['name' => 'alpha', 'base_url' => 'https://alpha.test', 'status' => 'online']);

        $api = $this->createMock(HorizonClientService::class);
        $api->method('getJob')->willReturn(['success' => false]);

        $resolver = new JobServiceResolver($api);

        $this->assertNull($resolver->resolve('missing-job'));
    }

    public function test_resolve_uses_cached_service_id(): void
    {
        Service::create(['name' => 'alpha', 'base_url' => 'https://alpha.test', 'status' => 'online']);
        $second = Service::create(['name' => 'beta', 'base_url' => 'https://beta.test', 'status' => 'online']);

        Cache::forever('horizonhub:job-service:job-uuid-1', $second->id);

        $api = $this->createMock(HorizonClientService::class);
        $api->expects($this->once())
            ->method('getJob')
            ->willReturn(['success' => true, 'data' => ['id' => 'job-uuid-1']]);

        $resolver = new JobServiceResolver($api);

        $this->assertTrue($resolver->resolve('job-uuid-1')['service']->is($second));
    }
}
