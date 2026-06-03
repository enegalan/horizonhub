<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_queue_page_with_services_and_deferred_loading(): void
    {
        $service = Service::create([
            'name' => 'queue-svc',
            'base_url' => 'https://queue-svc.test',
            'status' => 'online',
        ]);

        $response = $this->get(route('horizon.queues.index'));

        $response->assertOk();
        $response->assertViewHas('defer', true);
        $response->assertViewHas('selectedServiceIds', []);
        $response->assertSee('queue-svc', false);
        $response->assertSee('id="turbo-tbody-horizon-queue-list"', false);
    }

    public function test_index_restricts_service_ids_to_existing_services(): void
    {
        $service = Service::create([
            'name' => 'scoped-queue-svc',
            'base_url' => 'https://scoped-queue-svc.test',
            'status' => 'online',
        ]);

        $response = $this->get(route('horizon.queues.index', [
            'service_id' => [$service->id, 99999, '0'],
        ]));

        $response->assertOk();
        $response->assertViewHas('selectedServiceIds', [$service->id]);
    }
}
