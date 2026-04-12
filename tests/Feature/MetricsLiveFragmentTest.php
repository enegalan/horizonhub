<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsLiveFragmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_page_contains_turbo_stream_targets(): void
    {
        $response = $this->get('/horizon/metrics');

        $response->assertOk();
        $response->assertSee('id="horizon-metrics-dashboard"', false);
        $response->assertSee('id="metrics-value-jobs-minute"', false);
        $response->assertSee('id="metrics-chart-data"', false);
        $response->assertSee('horizonHubStreamsBaseUrl', false);
    }

    public function test_metrics_page_includes_chart_data_script_element(): void
    {
        $response = $this->get('/horizon/metrics');

        $response->assertOk();
        $response->assertSee('id="metrics-chart-data"', false);
    }
}
