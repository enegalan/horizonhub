<?php

namespace Tests\Unit;

use App\Jobs\EvaluateAlertJob;
use App\Models\Service;
use App\Services\Horizon\HorizonJobDetailService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HorizonJobDetailServiceTest extends TestCase
{
    #[Test]
    public function it_computes_runtime_from_reserved_to_failed_when_runtime_is_missing(): void
    {
        $service = new Service;
        $service->forceFill([
            'name' => 'Orders API',
            'base_url' => 'http://example.test',
        ]);

        $jobData = [
            'id' => 'job-failed-runtime-derived',
            'name' => 'App\\Jobs\\FailingJob',
            'queue' => 'default',
            'connection' => 'database',
            'status' => 'failed',
            'reserved_at' => '1711111111.100',
            'failed_at' => '1711111111.140',
        ];

        $serviceUnderTest = new HorizonJobDetailService;
        $result = $serviceUnderTest->buildShowViewData($service, $jobData);

        $this->assertSame('0.04 s', $result->runtime);
    }

    #[Test]
    public function it_derives_available_at_from_serialized_command_when_payload_delay_is_null(): void
    {
        $service = new Service;
        $service->forceFill([
            'name' => 'Orders API',
            'base_url' => 'http://example.test',
        ]);

        $job = new EvaluateAlertJob(9, 'eval-delay-payload');
        $job->delay(300);

        $jobData = [
            'id' => 'job-serialized-delay',
            'name' => EvaluateAlertJob::class,
            'queue' => 'default',
            'connection' => 'database',
            'status' => 'pending',
            'payload' => [
                'pushedAt' => '1711111111.000',
                'delay' => null,
                'data' => [
                    'commandName' => EvaluateAlertJob::class,
                    'command' => serialize(clone $job),
                ],
            ],
        ];

        $serviceUnderTest = new HorizonJobDetailService;
        $result = $serviceUnderTest->buildShowViewData($service, $jobData);

        $this->assertNull($result->available_at);
    }

    #[Test]
    public function it_does_not_compute_failed_runtime_from_pushed_to_failed_when_reserved_at_is_missing(): void
    {
        $service = new Service;
        $service->forceFill([
            'name' => 'Orders API',
            'base_url' => 'http://example.test',
        ]);

        $jobData = [
            'id' => 'job-failed-runtime-missing',
            'name' => 'App\\Jobs\\FailingJob',
            'queue' => 'default',
            'connection' => 'database',
            'status' => 'failed',
            'payload' => [
                'pushedAt' => '1711111111.123',
            ],
            'failed_at' => '2026-03-24T10:10:00+00:00',
        ];

        $serviceUnderTest = new HorizonJobDetailService;
        $result = $serviceUnderTest->buildShowViewData($service, $jobData);

        $this->assertNull($result->runtime);
    }

    #[Test]
    public function it_exposes_delay_and_available_at_for_delayed_jobs(): void
    {
        $service = new Service;
        $service->forceFill([
            'name' => 'Orders API',
            'base_url' => 'http://example.test',
        ]);

        $delayAt = Carbon::parse('2026-04-15 12:59:10');
        $job = new EvaluateAlertJob(99, 'eval-delay-date');
        $job->delay($delayAt);

        $jobData = [
            'id' => 'job-delayed',
            'name' => EvaluateAlertJob::class,
            'queue' => 'default',
            'connection' => 'database',
            'status' => 'pending',
            'payload' => [
                'pushedAt' => '1711111111.000',
                'data' => [
                    'commandName' => EvaluateAlertJob::class,
                    'command' => serialize(clone $job),
                ],
            ],
        ];

        $serviceUnderTest = new HorizonJobDetailService;
        $result = $serviceUnderTest->buildShowViewData($service, $jobData);

        $this->assertInstanceOf(Carbon::class, $result->available_at);
        $this->assertSame($delayAt->toIso8601String(), $result->available_at->toIso8601String());
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
            'id' => 'job-failed-123',
            'name' => 'App\\Jobs\\FailingJob',
            'queue' => 'default',
            'connection' => 'database',
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
        $result = $serviceUnderTest->buildShowViewData($service, $jobData);

        $this->assertSame(2, $result->retries);
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
        ], $result->retried_by);
        $this->assertSame([
            'tenant_id' => 25,
            'job' => 'App\\Jobs\\FailingJob',
        ], $result->context);
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
            'id' => 'job-command-123',
            'name' => 'App\\Jobs\\DispatchMail',
            'queue' => 'default',
            'connection' => 'database',
            'status' => 'failed',
            'payload' => [
                'data' => [
                    'command' => \serialize($command),
                    'commandName' => 'App\\Jobs\\DispatchMail',
                ],
            ],
        ];

        $serviceUnderTest = new HorizonJobDetailService;
        $result = $serviceUnderTest->buildShowViewData($service, $jobData);

        $this->assertSame([
            'sender' => [
                'from' => ['address' => 'from@example.test'],
                'to' => ['address' => 'to@example.test'],
            ],
            'retries' => 3,
        ], $result->command_data);
    }

    #[Test]
    public function it_reads_queued_at_from_payload_for_completed_jobs(): void
    {
        $service = new Service;
        $service->forceFill([
            'name' => 'Orders API',
            'base_url' => 'http://example.test',
        ]);

        $jobData = [
            'id' => 'job-123',
            'name' => 'App\\Jobs\\ProcessOrder',
            'queue' => 'default',
            'status' => 'completed',
            'connection' => 'database',
            'failed_at' => false,
            'completed_at' => '2026-03-24T10:10:00+00:00',
            'payload' => [
                'pushedAt' => '1711111111.123',
            ],
        ];

        $serviceUnderTest = new HorizonJobDetailService;
        $result = $serviceUnderTest->buildShowViewData($service, $jobData);

        $this->assertSame('processed', $result->status);
        $this->assertInstanceOf(Carbon::class, $result->queued_at);
        $this->assertSame(
            Carbon::createFromTimestampMs(1711111111123)->toIso8601String(),
            $result->queued_at->toIso8601String()
        );
        $this->assertInstanceOf(Carbon::class, $result->processed_at);
        $this->assertNull($result->failed_at);
    }

    #[Test]
    public function it_sets_null_delay_when_no_delay_present(): void
    {
        $service = new Service;
        $service->forceFill([
            'name' => 'Orders API',
            'base_url' => 'http://example.test',
        ]);

        $jobData = [
            'id' => 'job-no-delay',
            'name' => 'App\\Jobs\\ImmediateJob',
            'queue' => 'default',
            'connection' => 'database',
            'status' => 'completed',
            'completed_at' => '2026-03-24T10:10:00+00:00',
            'payload' => [
                'pushedAt' => '1711111111.000',
            ],
        ];

        $serviceUnderTest = new HorizonJobDetailService;
        $result = $serviceUnderTest->buildShowViewData($service, $jobData);

        $this->assertNull($result->available_at);
    }

    #[Test]
    public function it_uses_absolute_delay_datetime_from_serialized_command_when_delay_seconds_are_missing(): void
    {
        $service = new Service;
        $service->forceFill([
            'name' => 'Orders API',
            'base_url' => 'http://example.test',
        ]);

        $delayAt = Carbon::parse('2026-04-15 12:59:10');
        $job = new EvaluateAlertJob(10, 'eval-absolute-delay');
        $job->delay($delayAt);

        $jobData = [
            'id' => 'job-serialized-absolute-delay',
            'name' => EvaluateAlertJob::class,
            'queue' => 'default',
            'connection' => 'database',
            'status' => 'pending',
            'payload' => [
                'pushedAt' => '1711111111.000',
                'delay' => null,
                'data' => [
                    'commandName' => EvaluateAlertJob::class,
                    'command' => serialize(clone $job),
                ],
            ],
        ];

        $serviceUnderTest = new HorizonJobDetailService;
        $result = $serviceUnderTest->buildShowViewData($service, $jobData);

        $this->assertInstanceOf(Carbon::class, $result->available_at);
        $this->assertSame($delayAt->toIso8601String(), $result->available_at->toIso8601String());
    }
}
