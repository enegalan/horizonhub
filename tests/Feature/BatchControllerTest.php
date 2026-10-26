<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_batch_page_with_services_and_deferred_loading(): void
    {
        Service::create([
            'name' => 'batch-svc',
            'base_url' => 'https://batch-svc.test',
            'status' => 'online',
        ]);

        $response = $this->get(route('horizon.batches.index'));

        $response->assertOk();
        $response->assertViewHas('defer', true);
        $response->assertViewHas('selectedServiceIds', []);
        $response->assertSee('batch-svc', false);
        $response->assertSee('id="tbody-horizon-batch-list"', false);
        $response->assertSee('name="search"', false);
    }

    public function test_index_restricts_service_ids_to_existing_services(): void
    {
        $service = Service::create([
            'name' => 'scoped-batch-svc',
            'base_url' => 'https://scoped-batch-svc.test',
            'status' => 'online',
        ]);

        $response = $this->get(route('horizon.batches.index', [
            'service_id' => [$service->id, 99999, '0'],
        ]));

        $response->assertOk();
        $response->assertViewHas('selectedServiceIds', [$service->id]);
    }

    public function test_show_renders_service_scoped_batch_detail(): void
    {
        $service = Service::create([
            'name' => 'batch-detail-svc',
            'base_url' => 'https://batch-detail-svc.test',
            'status' => 'online',
        ]);

        $response = $this->get(route('horizon.services.batches.show', [
            'service' => $service,
            'batch' => 'batch-123',
        ]));

        $response->assertOk();
        $response->assertViewHas('defer', true);
        $response->assertViewHas('batchId', 'batch-123');
        $response->assertSee('batch-detail-svc', false);
        $response->assertSee('id="batch-show-body"', false);
    }
}
