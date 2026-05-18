<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\ServiceFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceFilterServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServiceFilterService $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new ServiceFilterService;
    }

    #[Test]
    public function resolve_filters_by_tags(): void
    {
        $prod = Service::factory()->create(['tags' => ['production']]);
        $staging = Service::factory()->create(['tags' => ['staging']]);
        Service::factory()->create(['tags' => ['local']]);

        $request = Request::create('/horizon/dashboard', 'GET', [
            'service_tag' => ['production', 'staging'],
        ]);

        $ids = $this->filter->resolveServiceIds($request);

        $this->assertSame([$prod->id, $staging->id], $ids);
    }

    #[Test]
    public function resolve_intersects_tags_with_explicit_service_ids(): void
    {
        $prod = Service::factory()->create(['tags' => ['production']]);
        $staging = Service::factory()->create(['tags' => ['staging']]);

        $request = Request::create('/horizon/metrics', 'GET', [
            'service_tag' => ['production', 'staging'],
            'service_id' => [$prod->id],
        ]);

        $ids = $this->filter->resolveServiceIds($request);

        $this->assertSame([$prod->id], $ids);
        $this->assertNotContains($staging->id, $ids);
    }

    #[Test]
    public function resolve_returns_empty_when_no_filters(): void
    {
        $request = Request::create('/horizon/dashboard', 'GET');

        $this->assertSame([], $this->filter->resolveServiceIds($request));
    }
}
