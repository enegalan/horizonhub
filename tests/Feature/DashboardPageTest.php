<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_horizon_index_renders_dashboard(): void
    {
        $response = $this->get('/horizon');

        $response->assertOk();
        $response->assertSee('horizon-dashboard', false);
    }

    public function test_jobs_list_renders_at_horizon_jobs_path(): void
    {
        $response = $this->get('/horizon/jobs');

        $response->assertOk();
    }
}
