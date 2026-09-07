<?php

namespace Tests\Unit;

use App\Services\Jobs\JobCommandDataExtractorService;
use App\Services\Jobs\JobRuntimeHelperService;
use Carbon\Carbon;
use Tests\TestCase;

class HorizonSupportHelpersTest extends TestCase
{
    public function test_job_command_data_extractor_handles_invalid_and_serialized_inputs(): void
    {
        $this->assertNull(JobCommandDataExtractorService::extract([]));
        $this->assertNull(JobCommandDataExtractorService::extract(['data' => []]));
        $this->assertNull(JobCommandDataExtractorService::extract(['data' => ['command' => 'not-serialized']]));
        $this->assertNull(JobCommandDataExtractorService::extract(['data' => ['command' => 's:3:"abc";']]));

        $serialized = serialize((object) ['foo' => 'bar', 'nested' => ['x' => 1]]);
        $result = JobCommandDataExtractorService::extract(['data' => ['command' => $serialized]]);
        $this->assertIsArray($result);
        $this->assertSame('bar', $result['foo']);
        $this->assertSame(1, $result['nested']['x']);
    }

    public function test_job_runtime_helper_covers_runtime_status_and_timestamp_paths(): void
    {
        $this->assertNull(JobRuntimeHelperService::getFormattedRuntime(null));
        $this->assertSame('1.50 s', JobRuntimeHelperService::getFormattedRuntime(1.5));

        $start = Carbon::parse('2026-01-01 10:00:00');
        $end = Carbon::parse('2026-01-01 10:00:03');
        $this->assertSame(2.5, JobRuntimeHelperService::getRuntimeSeconds(2.5, null, null, null));
        $this->assertSame(3.0, JobRuntimeHelperService::getRuntimeSeconds(null, $start, $end, null));
        $this->assertNull(JobRuntimeHelperService::getRuntimeSeconds(null, 'invalid', null, null));

        $processedAt = '2026-01-01 10:00:01';
        $failedAt = '2026-01-01 10:00:02';
        JobRuntimeHelperService::normalizeStatusDates('processed', $processedAt, $failedAt);
        $this->assertNull($failedAt);

        $processedAt = '2026-01-01 10:00:01';
        $failedAt = '2026-01-01 10:00:02';
        JobRuntimeHelperService::normalizeStatusDates('failed', $processedAt, $failedAt);
        $this->assertNull($processedAt);

        $processedAt = '2026-01-01 10:00:01';
        $failedAt = '2026-01-01 10:00:02';
        JobRuntimeHelperService::normalizeStatusDates('processing', $processedAt, $failedAt);
        $this->assertNull($processedAt);
        $this->assertNull($failedAt);

        $this->assertInstanceOf(Carbon::class, JobRuntimeHelperService::parseJobTimestamp(123));
        $this->assertInstanceOf(Carbon::class, JobRuntimeHelperService::parseJobTimestamp(1704067200));
        $this->assertSame(1704067200, JobRuntimeHelperService::parseJobTimestamp(1704067200)->getTimestamp());
        $this->assertInstanceOf(Carbon::class, JobRuntimeHelperService::parseJobTimestamp('2026-01-01 10:00:00'));
        $this->assertInstanceOf(Carbon::class, JobRuntimeHelperService::parseJobTimestamp(Carbon::parse('2026-01-01 10:00:00')));
        $this->assertNull(JobRuntimeHelperService::parseJobTimestamp(false));
        $this->assertNull(JobRuntimeHelperService::parseJobTimestamp('not-a-date'));
    }
}
