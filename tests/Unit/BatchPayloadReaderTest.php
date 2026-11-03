<?php

namespace Tests\Unit;

use App\Support\Batches\BatchPayloadReader;
use Tests\TestCase;

class BatchPayloadReaderTest extends TestCase
{
    public function test_normalize_derives_status_and_progress(): void
    {
        $row = BatchPayloadReader::normalize([
            'id' => 'batch-1',
            'name' => 'Demo',
            'totalJobs' => 10,
            'pendingJobs' => 4,
            'failedJobs' => 0,
            'createdAt' => now()->subMinute()->timestamp,
        ]);

        $this->assertNotNull($row);
        $this->assertSame('pending', $row['status']);
        $this->assertSame(6, $row['processed_jobs']);
        $this->assertSame(60, $row['progress']);
    }

    public function test_rows_and_detail_normalize_payload_shapes(): void
    {
        $rows = BatchPayloadReader::rows([
            'batches' => [
                ['id' => 'a', 'totalJobs' => 1, 'pendingJobs' => 0, 'failedJobs' => 0],
            ],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('finished', $rows[0]['status']);

        $detail = BatchPayloadReader::detail([
            'batch' => ['id' => 'a', 'totalJobs' => 1, 'pendingJobs' => 0, 'failedJobs' => 0],
            'failedJobs' => [
                ['id' => 'job-1', 'name' => 'App\\Jobs\\X', 'queue' => 'default'],
            ],
        ]);

        $this->assertSame('a', $detail['batch']['id']);
        $this->assertCount(1, $detail['failedJobs']);
        $this->assertSame('job-1', $detail['failedJobs'][0]['id']);
    }

    public function test_summarize_counts_in_progress_and_failures_using_status(): void
    {
        $stats = BatchPayloadReader::summarize([
            [
                'id' => 'running',
                'totalJobs' => 5,
                'pendingJobs' => 2,
                'failedJobs' => 0,
            ],
            [
                'id' => 'running-with-failures',
                'totalJobs' => 5,
                'pendingJobs' => 1,
                'failedJobs' => 2,
            ],
            [
                'id' => 'done',
                'totalJobs' => 5,
                'pendingJobs' => 0,
                'failedJobs' => 1,
                'finishedAt' => now()->timestamp,
            ],
            [
                'id' => '',
                'pendingJobs' => 1,
            ],
        ]);

        $this->assertSame([
            'total' => 3,
            'inProgress' => 2,
            'withFailures' => 2,
        ], $stats);
    }
}
