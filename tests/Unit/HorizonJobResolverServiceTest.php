<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\HorizonJobResolverService;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HorizonJobResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_resolves_the_job_using_the_hinted_service_id(): void
    {
        $service = Service::create([
            'name' => 'production--oversys-hermes',
            'api_key' => 'k12345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://production-service.test',
            'status' => 'online',
        ]);

        /** @var HorizonApiProxyService&MockInterface $api */
        $api = $this->mock(HorizonApiProxyService::class);
        $api->shouldReceive('getFailedJob')
            ->once()
            ->withArgs(function (Service $calledService, string $uuid) use ($service): bool {
                return $calledService->is($service) && $uuid === 'job-uuid-1';
            })
            ->andReturnUsing(static fn (): array => [
                'success' => true,
                'data' => [
                    'uuid' => 'job-uuid-1',
                    'name' => 'App\\Jobs\\SyncOrder',
                    'queue' => 'default',
                    'status' => 'failed',
                ],
            ]);

        /** @var CacheContract&MockInterface $cache */
        $cache = $this->mock(CacheContract::class);
        $cache->shouldNotReceive('get');
        $cache->shouldNotReceive('put');

        $resolver = new HorizonJobResolverService($api, $cache);
        $resolved = $resolver->getJobAndService('job-uuid-1', (int) $service->id);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved['service']->is($service));
        $this->assertSame('job-uuid-1', $resolved['job']['uuid']);
    }

    #[Test]
    public function it_does_not_fallback_to_other_services_when_hinted_service_misses_job(): void
    {
        $hintedService = Service::create([
            'name' => 'production--oversys-hermes',
            'api_key' => 'k12345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://production-service.test',
            'status' => 'online',
        ]);

        Service::create([
            'name' => 'local--oversys-hermes',
            'api_key' => 'k22345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://local-service.test',
            'status' => 'online',
        ]);

        /** @var HorizonApiProxyService&MockInterface $api */
        $api = $this->mock(HorizonApiProxyService::class);
        $api->shouldReceive('getFailedJob')
            ->once()
            ->withArgs(function (Service $calledService, string $uuid) use ($hintedService): bool {
                return $calledService->is($hintedService) && $uuid === 'job-uuid-2';
            })
            ->andReturnUsing(static fn (): array => [
                'success' => false,
            ]);

        /** @var CacheContract&MockInterface $cache */
        $cache = $this->mock(CacheContract::class);
        $cache->shouldNotReceive('get');
        $cache->shouldNotReceive('put');

        $resolver = new HorizonJobResolverService($api, $cache);
        $resolved = $resolver->getJobAndService('job-uuid-2', (int) $hintedService->id);

        $this->assertNull($resolved);
    }
}
