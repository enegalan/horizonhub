<?php

namespace Tests\Unit;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceAccessorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_horizon_status_is_lowercased_and_falls_back_to_offline(): void
    {
        $service = Service::create([
            'name' => 'svc-status',
            'base_url' => 'https://svc.test',
            'status' => 'online',
        ]);

        $service->horizon_status = 'STANDBY';
        $this->assertSame('standby', $service->horizon_status);

        $service->horizon_status = null;
        $this->assertSame('offline', $service->horizon_status);
    }

    public function test_persisted_tags_are_returned_in_sorted_order(): void
    {
        $tags = ['zulu', 'alpha', 'mike'];
        $sortedTags = ['alpha', 'mike', 'zulu'];
        $service = Service::create([
            'name' => 'svc-tags',
            'base_url' => 'https://svc.test',
            'status' => 'online',
            'tags' => $tags,
        ]);

        $this->assertSame($sortedTags, $service->tags);
    }

    public function test_public_url_falls_back_to_base_url_when_blank(): void
    {
        $baseUrl = 'https://svc.test/';
        $expectedUrl = 'https://svc.test';
        $service = Service::create([
            'name' => 'svc-fallback',
            'base_url' => $baseUrl,
            'public_url' => null,
            'status' => 'online',
        ]);

        $this->assertSame($expectedUrl, $service->public_url);
    }

    public function test_public_url_trims_trailing_slash_when_set(): void
    {
        $publicUrl = 'https://public.test/';
        $expectedUrl = 'https://public.test';
        $service = Service::create([
            'name' => 'svc-public',
            'base_url' => 'https://internal.test',
            'public_url' => $publicUrl,
            'status' => 'online',
        ]);

        $this->assertSame($expectedUrl, $service->public_url);
    }
}
