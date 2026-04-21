<?php

namespace Tests\Feature;

use App\Models\Service;
use HotwiredLaravel\TurboLaravel\Turbo;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurboFormResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_service_destroy_returns_303_for_turbo_request(): void
    {
        $service = Service::create([
            'name' => 'Destroy Test',
            'base_url' => 'https://example.com',
            'status' => 'online',
        ]);

        $response = $this->delete(route('horizon.services.destroy', $service), [], [
            'Accept' => Turbo::TURBO_STREAM_FORMAT,
        ]);

        $response->assertStatus(303);
    }

    public function test_service_store_returns_302_for_non_turbo_request(): void
    {
        $serviceName = 'Test Service Non-Turbo';

        $response = $this->post(route('horizon.services.store'), [
            'name' => $serviceName,
            'base_url' => 'https://example.com',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('horizon.services.index'));
        $this->assertDatabaseHas('services', [
            'name' => $serviceName,
            'status' => 'offline',
        ]);
    }

    public function test_service_store_returns_303_for_turbo_request(): void
    {
        $serviceName = 'Test Service';

        $response = $this->post(route('horizon.services.store'), [
            'name' => $serviceName,
            'base_url' => 'https://example.com',
        ], [
            'Accept' => Turbo::TURBO_STREAM_FORMAT,
        ]);

        $response->assertStatus(303);
        $response->assertRedirect(route('horizon.services.index'));
        $this->assertDatabaseHas('services', [
            'name' => $serviceName,
            'status' => 'offline',
        ]);
    }
}
