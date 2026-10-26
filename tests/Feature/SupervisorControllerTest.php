<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_renders_service_scoped_supervisor_detail(): void
    {
        $service = Service::create([
            'name' => 'supervisor-detail-svc',
            'base_url' => 'https://supervisor-detail-svc.test',
            'status' => 'online',
        ]);

        $response = $this->get(route('horizon.services.supervisors.show', [
            'service' => $service,
            'supervisor' => 'supervisor-1',
        ]));

        $response->assertOk();
        $response->assertViewHas('defer', true);
        $response->assertViewHas('supervisorName', 'supervisor-1');
        $response->assertSee('supervisor-detail-svc', false);
        $response->assertSee('id="supervisor-show-body"', false);
    }
}
