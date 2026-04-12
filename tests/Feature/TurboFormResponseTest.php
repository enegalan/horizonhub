<?php

namespace Tests\Feature;

use App\Models\Service;
use HotwiredLaravel\TurboLaravel\Turbo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurboFormResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_store_returns_303_for_turbo_request(): void
    {
        $response = $this->post(route('horizon.services.store'), [
            'name' => 'Test Service',
            'base_url' => 'https://example.com',
        ], [
            'Accept' => Turbo::TURBO_STREAM_FORMAT,
        ]);

        $response->assertStatus(303);
        $response->assertRedirect(route('horizon.services.index'));
    }

    public function test_service_store_returns_302_for_non_turbo_request(): void
    {
        $response = $this->post(route('horizon.services.store'), [
            'name' => 'Test Service Non-Turbo',
            'base_url' => 'https://example.com',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('horizon.services.index'));
    }

    public function test_service_destroy_returns_303_for_turbo_request(): void
    {
        $service = Service::create([
            'name' => 'Destroy Test',
            'base_url' => 'https://example.com',
            'api_key' => \Str::random(64),
            'status' => 'online',
        ]);

        $response = $this->delete(route('horizon.services.destroy', $service), [], [
            'Accept' => Turbo::TURBO_STREAM_FORMAT,
        ]);

        $response->assertStatus(303);
    }
}
