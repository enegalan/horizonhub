<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\HorizonApiProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgentProxyServiceTest extends TestCase {
    use RefreshDatabase;

    public function test_retry_job_calls_horizon_api_and_returns_success(): void {
        $capturedRequest = null;

        Http::fake(function ($request) use (&$capturedRequest) {
            $capturedRequest = $request;

            return Http::response([], 200);
        });

        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'secret-key',
            'base_url' => 'https://example.test',
            'status' => 'online',
        ]);

        \config()->set('horizonhub.horizon.paths.api', '/horizon/api');
        \config()->set('horizonhub.horizon.paths.retry', '/jobs/retry/{id}');

        $proxy = new HorizonApiProxyService();

        $result = $proxy->retryJob($service, 'job-uuid-1');

        $this->assertTrue($result['success']);
        $this->assertNotNull($capturedRequest);
        $this->assertSame('https://example.test/horizon/api/jobs/retry/job-uuid-1', $capturedRequest->url());
    }
}
