<?php

namespace Tests\Feature;

use Tests\TestCase;

class MockEnvironmentTest extends TestCase
{
    protected static bool $useMockEnvironment = true;

    public function test_horizon_dashboard_loads_with_mock_services(): void
    {
        $response = $this->get('/horizon');

        $response->assertOk();
        $response->assertSee('billing-api', false);
        $response->assertSee('Dashboard', false);
    }

    public function test_post_to_create_service_is_blocked_in_mock_mode(): void
    {
        $response = $this->post('/horizon/services', [
            'name' => 'new-svc',
            'base_url' => 'https://new.test',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
    }
}
