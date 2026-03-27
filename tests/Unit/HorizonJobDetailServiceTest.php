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

    #[Test]
    public function it_exposes_retried_by_and_context_for_failed_jobs(): void
    {
        $service = new Service;
        $service->forceFill([
            'name' => 'Orders API',
            'base_url' => 'http://example.test',
        ]);

        $jobData = [
            'uuid' => 'job-failed-123',
            'name' => 'App\\Jobs\\FailingJob',
            'queue' => 'default',
            'status' => 'failed',
            'retried_by' => [
                [
                    'id' => 'retry-1',
                    'status' => 'failed',
                    'retried_at' => 1774532546,
                ],
                [
                    'id' => 'retry-2',
                    'status' => '',
                    'retried_at' => '1774532534',
                ],
                [
                    'id' => '',
                    'status' => 'failed',
                    'retried_at' => 1774532500,
                ],
            ],
            'context' => [
                'tenant_id' => 25,
                'job' => 'App\\Jobs\\FailingJob',
            ],
        ];

        $serviceUnderTest = new HorizonJobDetailService;
        $result = $serviceUnderTest->buildShowViewData($service, $jobData, 'job-failed-123');

        $this->assertSame(2, $result['horizonJob']['retries']);
        $this->assertSame([
            [
                'id' => 'retry-1',
                'status' => 'failed',
                'retried_at' => 1774532546,
            ],
            [
                'id' => 'retry-2',
                'status' => null,
                'retried_at' => 1774532534,
            ],
        ], $result['horizonJob']['retriedBy']);
        $this->assertSame([
            'tenant_id' => 25,
            'job' => 'App\\Jobs\\FailingJob',
        ], $result['horizonJob']['context']);
    }

    #[Test]
    public function it_extracts_data_from_serialized_command_payload(): void
    {
        $service = new Service;
        $service->forceFill([
            'name' => 'Orders API',
            'base_url' => 'http://example.test',
        ]);

        $command = (object) [
            'sender' => (object) [
                'from' => (object) ['address' => 'from@example.test'],
                'to' => (object) ['address' => 'to@example.test'],
            ],
            'retries' => 3,
        ];

        $jobData = [
            'uuid' => 'job-command-123',
            'name' => 'App\\Jobs\\DispatchMail',
            'queue' => 'default',
            'status' => 'failed',
            'payload' => [
                'data' => [
                    'command' => \serialize($command),
                    'commandName' => 'App\\Jobs\\DispatchMail',
                ],
            ],
        ];

        $serviceUnderTest = new HorizonJobDetailService;
        $result = $serviceUnderTest->buildShowViewData($service, $jobData, 'job-command-123');

        $this->assertSame([
            'sender' => [
                'from' => ['address' => 'from@example.test'],
                'to' => ['address' => 'to@example.test'],
            ],
            'retries' => 3,
        ], $result['horizonJob']['commandData']);
    }
}
