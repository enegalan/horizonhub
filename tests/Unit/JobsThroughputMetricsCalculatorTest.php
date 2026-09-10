<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Jobs\JobsWindowFetcherService;
use App\Services\Metrics\Calculators\JobsThroughputMetricsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobsThroughputMetricsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_throughput_calculator_handles_service_and_global_paths(): void
    {
        $s1 = Service::create(['name' => 'svc-a', 'base_url' => 'https://a.test', 'status' => 'online']);
        Service::create(['name' => 'svc-b', 'base_url' => 'https://b.test', 'status' => 'online']);

        Http::fake(function ($request) use ($s1) {
            if (\str_contains($request->url(), $s1->getBaseUrl())) {
                return Http::response(['failedJobs' => 2, 'recentJobs' => 20, 'jobsPerMinute' => 3.4], 200);
            }

            return Http::response(['failedJobs' => 1, 'recentJobs' => 10, 'periods' => ['recentJobs' => 20]], 200);
        });

        $calc = new JobsThroughputMetricsCalculator(new JobsWindowFetcherService);

        $this->assertSame(2, $calc->getFailedPastSevenDays($s1));
        $this->assertSame(3, $calc->getJobsPastMinute($s1));
        $this->assertSame(20, $calc->getJobsPastHour($s1));

        $totals = $calc->getThroughputTotals(null);

        $this->assertSame(3, $totals['failedPastSevenDays']);
        $this->assertSame(30, $totals['jobsPastHour']);
        $this->assertSame(4, $totals['jobsPastMinute']);
        $this->assertSame(3, $calc->getFailedPastSevenDays(null));
        $this->assertSame(30, $calc->getJobsPastHour(null));
        $this->assertSame(4, $calc->getJobsPastMinute(null));
    }
}
