<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstanceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_instances_page_with_deferred_loading(): void
    {
        Service::create([
            'name' => 'instance-svc',
            'base_url' => 'https://instance-svc.test',
            'status' => 'online',
        ]);

        $response = $this->get(route('horizon.instances.index'));

        $response->assertOk();
        $response->assertViewHas('defer', true);
        $response->assertViewHas('selectedServiceIds', []);
        $response->assertSee('instance-svc', false);
        $response->assertSee('id="instances-stats"', false);
        $response->assertSee('id="instances-content"', false);
    }

    public function test_index_restricts_service_ids_to_existing_services(): void
    {
        $service = Service::create([
            'name' => 'scoped-instance-svc',
            'base_url' => 'https://scoped-instance-svc.test',
            'status' => 'online',
        ]);

        $response = $this->get(route('horizon.instances.index', [
            'service_id' => [$service->id, 99999, '0'],
        ]));

        $response->assertOk();
        $response->assertViewHas('selectedServiceIds', [$service->id]);
    }
}
