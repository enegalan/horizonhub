<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\AgentProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgentProxyServiceTest extends TestCase {
    use RefreshDatabase;

    public function test_retry_job_sends_signed_request_and_returns_success(): void {
        $capturedRequest = null;

        Http::fake(function ($request) use (&$capturedRequest) {
            $capturedRequest = $request;

            return Http::response(array(), 200);
        });

        $service = Service::create(array(
            'name' => 'svc',
            'api_key' => 'secret-key',
            'base_url' => 'https://example.test',
            'status' => 'online',
        ));

        $proxy = new AgentProxyService();

        $result = $proxy->retryJob($service, 'job-uuid-1');

        $this->assertTrue($result['success']);
        $this->assertNotNull($capturedRequest);

        $this->assertSame($service->api_key, $capturedRequest->header('X-Api-Key')[0]);

        $timestamp = $capturedRequest->header('X-Hub-Timestamp')[0] ?? null;
        $signature = $capturedRequest->header('X-Hub-Signature')[0] ?? null;

        $this->assertNotNull($timestamp);
        $this->assertNotNull($signature);

        $expectedSignature = 'sha256=' . \hash_hmac('sha256', $timestamp . '.', $service->api_key);
        $this->assertSame($expectedSignature, $signature);
    }
}
