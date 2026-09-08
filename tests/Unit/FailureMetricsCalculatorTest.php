<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonClientService;
use App\Services\Jobs\JobsWindowFetcherService;
use App\Services\Metrics\Calculators\FailureMetricsCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailureMetricsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_failure_rate_24h_aggregates_paginated_jobs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-30 12:00:00'));
        $service = Service::create(['name' => 'svc-failure', 'base_url' => 'https://f.test', 'status' => 'online']);
        $since = now()->subDay()->startOfDay()->getTimestamp();

        $api = $this->createMock(HorizonClientService::class);
        $api->method('getCompletedJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => [
                ['index' => 1, 'completed_at' => $since + 3600],
                ['index' => 2, 'completed_at' => $since + 7200],
            ]],
        ]);
        $api->method('getFailedJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => [
                ['index' => 3, 'failed_at' => $since + 1800],
            ]],
        ]);

        $calc = new FailureMetricsCalculator($api, new JobsWindowFetcherService($api));
        $result = $calc->getFailureRate24h([$service->id]);

        $this->assertSame(2, $result['processed']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(33.3, $result['rate']);
        Carbon::setTestNow();
    }
}
