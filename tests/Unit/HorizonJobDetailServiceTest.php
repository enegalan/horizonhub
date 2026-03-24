<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonJobDetailService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HorizonJobDetailServiceTest extends TestCase
{
    #[Test]
    public function it_reads_queued_at_from_payload_for_completed_jobs(): void
    {
        $service = new Service;
        $service->forceFill([
            'name' => 'Orders API',
            'base_url' => 'http://example.test',
        ]);

        $jobData = [
            'uuid' => 'job-123',
            'name' => 'App\\Jobs\\ProcessOrder',
            'queue' => 'default',
            'status' => 'completed',
            'failed_at' => false,
            'completed_at' => '2026-03-24T10:10:00+00:00',
            'payload' => [
                'pushedAt' => '1711111111.123',
            ],
        ];

        $serviceUnderTest = new HorizonJobDetailService;
        $result = $serviceUnderTest->buildShowViewData($service, $jobData, 'job-123');

        $this->assertArrayHasKey('job', $result);
        $this->assertSame('processed', $result['job']->status);
        $this->assertInstanceOf(Carbon::class, $result['job']->queued_at);
        $this->assertSame(
            Carbon::createFromTimestampMs(1711111111123)->toIso8601String(),
            $result['job']->queued_at->toIso8601String()
        );
        $this->assertInstanceOf(Carbon::class, $result['job']->processed_at);
        $this->assertNull($result['job']->failed_at);
    }
}
