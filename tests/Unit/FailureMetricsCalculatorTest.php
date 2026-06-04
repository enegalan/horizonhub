<?php

namespace Tests\Unit;

use App\Contracts\HorizonHubStore;
use App\Models\Service;
use App\Services\Horizon\Contracts\HorizonClientApi;
use App\Services\Jobs\JobsWindowFetcher;
use App\Services\Metrics\Calculators\FailureMetricsCalculator;
use App\Services\Metrics\Calculators\QueueFailureCountersCalculator;
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

        $api = $this->createMock(HorizonClientApi::class);
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

        $store = $this->app->make(HorizonHubStore::class);
        $calc = new FailureMetricsCalculator($api, new JobsWindowFetcher($api), $store);
        $result = $calc->getFailureRate24h(['service_id' => $service->id]);

        $this->assertSame(2, $result['processed']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(33.3, $result['rate']);
        Carbon::setTestNow();
    }

    public function test_get_processed_failed_by_queue_aggregates_within_seven_day_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-30 12:00:00'));
        $service = Service::create(['name' => 'svc-queues', 'base_url' => 'https://q.test', 'status' => 'online']);
        $since = now()->subDays(7)->getTimestamp();

        $api = $this->createMock(HorizonClientApi::class);
        $api->method('getCompletedJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => [
                ['index' => 1, 'queue' => 'redis.default', 'completed_at' => $since + 3600],
                ['index' => 2, 'queue' => 'redis.default', 'completed_at' => $since - 3600],
            ]],
        ]);
        $api->method('getFailedJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => [
                ['index' => 3, 'queue' => 'redis.mail', 'failed_at' => $since + 1800],
            ]],
        ]);

        $store = $this->app->make(HorizonHubStore::class);
        $calc = new QueueFailureCountersCalculator($api, new JobsWindowFetcher($api), $store);
        $result = $calc->getProcessedFailedByQueue(['service_id' => $service->id]);

        $this->assertSame(['default', 'mail'], $result['queues']);
        $this->assertSame([1, 0], $result['processed']);
        $this->assertSame([0, 1], $result['failed']);
        Carbon::setTestNow();
    }
}
