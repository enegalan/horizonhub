<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_with_aggregated_data_and_filters(): void
    {
        $service = Service::query()->create(['name' => 'svc-a', 'base_url' => 'https://a.test', 'status' => 'online']);

        $response = $this->get(route('horizon.jobs.index', ['search' => 'job', 'service_id' => [$service->id]]));
        $response->assertOk();
    }

    public function test_show_redirects_to_dashboard_for_invalid_service_and_renders_for_valid_service(): void
    {
        $service = Service::query()->create(['name' => 'svc-b', 'base_url' => 'https://b.test', 'status' => 'online']);

        $this->get(route('horizon.jobs.show', ['job' => 'x', 'service_id' => 99999]))->assertRedirect(route('horizon.index'));

        $response = $this->get(route('horizon.jobs.show', ['job' => 'job-1', 'service_id' => $service->id]));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('id="horizon-job-detail-meta"', $html);
        $this->assertStringContainsString('id="horizon-job-detail-data"', $html);
        $this->assertStringContainsString('id="horizon-job-detail-payload"', $html);
    }
}
