<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_service_tag_filter_with_query_params(): void
    {
        $service = Service::factory()->create(['tags' => ['production']]);

        $response = $this->get('/horizon?service_tag[]=production&service_id[]=' . $service->id);

        $response->assertOk();
        $response->assertSee('value="production"', false);
        $response->assertSee('value="' . $service->id . '"', false);
    }

    public function test_horizon_index_renders_dashboard(): void
    {
        $response = $this->get('/horizon');

        $response->assertOk();
        $response->assertSee('horizon-dashboard', false);
        $response->assertSee('data-service-tag-filter="1"', false);
        $response->assertSee('dashboard-index-services', false);
    }

    public function test_jobs_list_renders_at_horizon_jobs_path(): void
    {
        $response = $this->get('/horizon/jobs');

        $response->assertOk();
    }
}
