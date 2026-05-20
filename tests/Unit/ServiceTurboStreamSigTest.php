<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Service;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Service::class)]
class ServiceTurboStreamSigTest extends TestCase
{
    public function test_sig_changes_when_horizon_job_count_changes(): void
    {
        $a = new Service([
            'id' => 2,
            'name' => 'svc',
            'base_url' => 'https://a.test',
            'status' => 'online',
            'enabled' => true,
            'tags' => [],
        ]);
        $a->horizon_jobs_count = 1;
        $a->horizon_failed_jobs_count = 0;

        $b = new Service([
            'id' => 2,
            'name' => 'svc',
            'base_url' => 'https://a.test',
            'status' => 'online',
            'enabled' => true,
            'tags' => [],
        ]);
        $b->horizon_jobs_count = 2;
        $b->horizon_failed_jobs_count = 0;

        $this->assertNotSame($a->getTurboStreamSig(), $b->getTurboStreamSig());
    }

    public function test_sig_stable_for_same_semantic_state(): void
    {
        $a = new Service([
            'id' => 1,
            'name' => 'svc',
            'base_url' => 'https://a.test',
            'public_url' => null,
            'status' => 'online',
            'enabled' => true,
            'tags' => ['b', 'a'],
            'last_seen_at' => Carbon::parse('2024-06-01 10:05:12', 'UTC'),
        ]);
        $a->horizon_status = 'running';
        $a->horizon_jobs_count = 3;
        $a->horizon_failed_jobs_count = 1;

        $b = new Service([
            'id' => 1,
            'name' => 'svc',
            'base_url' => 'https://a.test',
            'public_url' => null,
            'status' => 'online',
            'enabled' => true,
            'tags' => ['a', 'b'],
            'last_seen_at' => Carbon::parse('2024-06-01 10:05:59', 'UTC'),
        ]);
        $b->horizon_status = 'running';
        $b->horizon_jobs_count = 3;
        $b->horizon_failed_jobs_count = 1;

        $this->assertSame($a->getTurboStreamSig(), $b->getTurboStreamSig());
    }
}
