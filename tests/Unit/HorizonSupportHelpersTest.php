<?php

namespace Tests\Unit;

use App\Support\Jobs\JobCommandDataExtractor;
use App\Support\Jobs\JobRuntime;
use Carbon\Carbon;
use Tests\TestCase;

class HorizonSupportHelpersTest extends TestCase
{
    public function test_job_command_data_extractor_handles_invalid_and_serialized_inputs(): void
    {
        $this->assertNull(JobCommandDataExtractor::extract([]));
        $this->assertNull(JobCommandDataExtractor::extract(['data' => []]));
        $this->assertNull(JobCommandDataExtractor::extract(['data' => ['command' => 'not-serialized']]));
        $this->assertNull(JobCommandDataExtractor::extract(['data' => ['command' => 's:3:"abc";']]));

        $serialized = serialize((object) ['foo' => 'bar', 'nested' => ['x' => 1]]);
        $result = JobCommandDataExtractor::extract(['data' => ['command' => $serialized]]);
        $this->assertIsArray($result);
        $this->assertSame('bar', $result['foo']);
        $this->assertSame(1, $result['nested']['x']);
    }

    public function test_job_runtime_helper_covers_runtime_status_and_timestamp_paths(): void
    {
        $this->assertNull(JobRuntime::getFormattedRuntime(null));
        $this->assertSame('1.50 s', JobRuntime::getFormattedRuntime(1.5));

        $start = Carbon::parse('2026-01-01 10:00:00');
        $end = Carbon::parse('2026-01-01 10:00:03');
        $this->assertSame(2.5, JobRuntime::getRuntimeSeconds(2.5, null, null, null));
        $this->assertSame(3.0, JobRuntime::getRuntimeSeconds(null, $start, $end, null));
        $this->assertNull(JobRuntime::getRuntimeSeconds(null, 'invalid', null, null));

        $processedAt = '2026-01-01 10:00:01';
        $failedAt = '2026-01-01 10:00:02';
        JobRuntime::normalizeStatusDates('processed', $processedAt, $failedAt);
        $this->assertNull($failedAt);

        $processedAt = '2026-01-01 10:00:01';
        $failedAt = '2026-01-01 10:00:02';
        JobRuntime::normalizeStatusDates('failed', $processedAt, $failedAt);
        $this->assertNull($processedAt);

        $processedAt = '2026-01-01 10:00:01';
        $failedAt = '2026-01-01 10:00:02';
        JobRuntime::normalizeStatusDates('processing', $processedAt, $failedAt);
        $this->assertNull($processedAt);
        $this->assertNull($failedAt);

        $this->assertInstanceOf(Carbon::class, JobRuntime::parseJobTimestamp(123));
        $this->assertInstanceOf(Carbon::class, JobRuntime::parseJobTimestamp(1704067200));
        $this->assertSame(1704067200, JobRuntime::parseJobTimestamp(1704067200)->getTimestamp());
        $this->assertInstanceOf(Carbon::class, JobRuntime::parseJobTimestamp('2026-01-01 10:00:00'));
        $this->assertInstanceOf(Carbon::class, JobRuntime::parseJobTimestamp(Carbon::parse('2026-01-01 10:00:00')));
        $this->assertNull(JobRuntime::parseJobTimestamp(false));
        $this->assertNull(JobRuntime::parseJobTimestamp('not-a-date'));
    }
}
