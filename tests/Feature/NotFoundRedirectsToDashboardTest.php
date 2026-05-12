<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotFoundRedirectsToDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_horizon_web_path_redirects_to_dashboard(): void
    {
        $response = $this->get('/horizon/path-that-does-not-exist');

        $response->assertRedirect(route('horizon.index'));
    }

    public function test_unknown_json_request_returns_not_found(): void
    {
        $response = $this->getJson('/path-that-does-not-exist-json');

        $response->assertNotFound();
    }

    public function test_unknown_web_path_outside_horizon_returns_not_found(): void
    {
        $response = $this->get('/path-that-does-not-exist-xyz');

        $response->assertNotFound();
    }
}
