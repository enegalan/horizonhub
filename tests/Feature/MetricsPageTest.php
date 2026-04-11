<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_index_returns_successful_response(): void
    {
        $response = $this->get('/horizon/metrics');

        $response->assertOk();
    }
}
