<?php

namespace Tests\Unit;

use App\Jobs\EvaluateAlertJob;
use App\Support\Horizon\JobCommandDataExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobCommandDataExtractorTest extends TestCase
{
    #[Test]
    public function it_reads_delay_from_serialized_command_when_payload_delay_is_null(): void
    {
        $job = new EvaluateAlertJob(1, 'eval-test');
        $job->delay(300);

        $payload = [
            'uuid' => 'test-job-uuid',
            'displayName' => 'TestJob',
            'delay' => null,
            'pushedAt' => '1711111111.000',
            'data' => [
                'commandName' => EvaluateAlertJob::class,
                'command' => serialize(clone $job),
            ],
        ];

        $seconds = JobCommandDataExtractor::extractDelaySeconds($payload);

        $this->assertSame(300, $seconds);
    }

    #[Test]
    public function it_prefers_horizon_meta_delay_over_payload_and_command(): void
    {
        $job = new EvaluateAlertJob(2, 'eval-test-2');
        $job->delay(60);

        $payload = [
            'uuid' => 'test-job-uuid',
            'displayName' => 'TestJob',
            'delay' => 120,
            'data' => [
                'commandName' => EvaluateAlertJob::class,
                'command' => serialize(clone $job),
            ],
        ];

        $seconds = JobCommandDataExtractor::extractDelaySeconds($payload, 90);

        $this->assertSame(90, $seconds);
    }

    #[Test]
    public function it_reuses_preloaded_command_data_without_second_unserialize(): void
    {
        $job = new EvaluateAlertJob(3, 'eval-test-3');
        $job->delay(45);

        $payload = [
            'uuid' => 'test-job-uuid',
            'displayName' => 'TestJob',
            'delay' => null,
            'data' => [
                'commandName' => EvaluateAlertJob::class,
                'command' => serialize(clone $job),
            ],
        ];

        $commandData = JobCommandDataExtractor::extract($payload);
        $this->assertIsArray($commandData);

        $seconds = JobCommandDataExtractor::extractDelaySeconds($payload, null, $commandData);

        $this->assertSame(45, $seconds);
    }
}
