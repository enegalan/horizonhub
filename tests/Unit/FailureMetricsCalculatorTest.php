<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Jobs\JobsWindowFetcherService;
use App\Services\Metrics\Calculators\FailureMetricsCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FailureMetricsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_failure_rate_24h_aggregates_paginated_jobs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-30 12:00:00'));
        $service = Service::create(['name' => 'svc-failure', 'base_url' => 'https://f.test', 'status' => 'online']);
        $since = now()->subDay()->startOfDay()->getTimestamp();

        Http::fake(function ($request) use ($since) {
            if (\str_contains($request->url(), '/jobs/completed')) {
                return Http::response(['jobs' => [
                    ['index' => 1, 'completed_at' => $since + 3600],
                    ['index' => 2, 'completed_at' => $since + 7200],
                ]], 200);
            }

            if (\str_contains($request->url(), '/jobs/failed')) {
                return Http::response(['jobs' => [
                    ['index' => 3, 'failed_at' => $since + 1800],
                ]], 200);
            }

            return Http::response('unexpected', 500);
        });

        $calc = new FailureMetricsCalculator(new JobsWindowFetcherService);
        $result = $calc->getFailureRate24h([$service->id]);

        $this->assertSame(2, $result['processed']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(33.3, $result['rate']);
        Carbon::setTestNow();
    }
}
