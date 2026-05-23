<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Support\FormDrawer;
use HotwiredLaravel\TurboLaravel\Turbo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurboFormResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_create_in_form_drawer_uses_drawer_layout(): void
    {
        $response = $this->get(route('horizon.services.create'), [
            'Turbo-Frame' => FormDrawer::FRAME_ID,
        ]);

        $response->assertOk();
        $response->assertSee('id="form-drawer"', false);
        $response->assertSee('form-drawer-body', false);
        $response->assertDontSee('<!DOCTYPE html>', false);
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

    public function test_service_store_from_form_drawer_redirects_with_turbo_frame_top(): void
    {
        $response = $this->post(route('horizon.services.store'), [
            'name' => 'Drawer Service',
            'base_url' => 'https://example.com',
        ], [
            'Turbo-Frame' => FormDrawer::FRAME_ID,
        ]);

        $response->assertRedirect(route('horizon.services.index'));
        $this->assertSame('_top', $response->headers->get('Turbo-Frame'));
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
