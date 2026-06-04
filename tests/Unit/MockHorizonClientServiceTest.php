<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\MockHorizonClientService;
use Tests\TestCase;

class MockHorizonClientServiceTest extends TestCase
{
    public function test_get_job_resolves_pending_job_from_list_fixture(): void
    {
        $service = new Service(['id' => 1, 'name' => 'billing-api', 'base_url' => 'https://b.test', 'enabled' => true]);
        $service->id = 1;
        $pending = config('demo.horizon')[1]['pending_jobs']['jobs'][0] ?? [];
        $uuid = (string) ($pending['id'] ?? '');

        $this->assertNotSame('', $uuid);

        $result = (new MockHorizonClientService)->getJob($service, $uuid);

        $this->assertTrue($result['success']);
        $this->assertSame($uuid, $result['data']['id'] ?? null);
        $this->assertSame('pending', $result['data']['status'] ?? null);
    }

    public function test_get_job_returns_404_for_unknown_uuid(): void
    {
        $service = new Service(['id' => 1, 'name' => 'billing-api', 'base_url' => 'https://b.test', 'enabled' => true]);
        $service->id = 1;

        $result = (new MockHorizonClientService)->getJob($service, 'unknown-uuid');

        $this->assertFalse($result['success']);
        $this->assertSame(404, $result['status'] ?? null);
    }

    public function test_get_stats_returns_fixture_for_billing_service(): void
    {
        $service = new Service([
            'id' => 1,
            'name' => 'billing-api',
            'base_url' => 'https://billing.demo.test',
            'status' => 'online',
            'enabled' => true,
        ]);
        $service->id = 1;

        $client = new MockHorizonClientService;
        $result = $client->getStats($service);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, (int) ($result['data']['failedJobs'] ?? 0));
        $this->assertSame('running', $result['data']['status'] ?? null);
    }

    public function test_job_list_respects_pagination_query(): void
    {
        $service = new Service(['id' => 1, 'name' => 'billing-api', 'base_url' => 'https://b.test', 'enabled' => true]);
        $service->id = 1;
        $client = new MockHorizonClientService;

        $pageOne = $client->getPendingJobs($service, ['starting_at' => -1, 'limit' => 5]);
        $pageTwo = $client->getPendingJobs($service, [
            'starting_at' => (int) ($pageOne['data']['jobs'][4]['index'] ?? 0),
            'limit' => 5,
        ]);

        $this->assertTrue($pageOne['success']);
        $this->assertCount(5, $pageOne['data']['jobs'] ?? []);
        $this->assertTrue($pageTwo['success']);
        $this->assertNotSame(
            $pageOne['data']['jobs'][0]['id'] ?? null,
            $pageTwo['data']['jobs'][0]['id'] ?? null,
        );
    }

    public function test_retry_job_succeeds_without_http(): void
    {
        $service = new Service(['id' => 1, 'name' => 'billing-api', 'base_url' => 'https://b.test', 'enabled' => true]);
        $service->id = 1;

        $result = (new MockHorizonClientService)->retryJob($service, '763dc9c2-a7cd-4b95-9da5-77beff5c264e');

        $this->assertTrue($result['success']);
    }
}
