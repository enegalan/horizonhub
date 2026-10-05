<?php

namespace Tests\Unit;

use App\Support\Jobs\JobSearchFilter;
use Tests\TestCase;

class JobSearchFilterTest extends TestCase
{
    public function test_matches_accepts_empty_search(): void
    {
        $this->assertTrue(JobSearchFilter::matches(['id' => 'abc', 'queue' => 'redis.default', 'name' => 'App\\Jobs\\Foo'], ''));
    }

    public function test_matches_is_case_insensitive_on_queue_name_and_uuid(): void
    {
        $job = ['id' => 'Job-UUID-1', 'queue' => 'redis.default', 'name' => 'App\\Jobs\\SendInvoice'];

        $this->assertTrue(JobSearchFilter::matches($job, 'invoice'));
        $this->assertTrue(JobSearchFilter::matches($job, 'REDIS.DEFAULT'));
        $this->assertTrue(JobSearchFilter::matches($job, 'uuid-1'));
        $this->assertFalse(JobSearchFilter::matches($job, 'refund'));
    }

    public function test_matches_treats_missing_fields_as_empty_strings(): void
    {
        $this->assertFalse(JobSearchFilter::matches([], 'invoice'));
        $this->assertTrue(JobSearchFilter::matches(['name' => 'invoice'], 'invoice'));
        $this->assertFalse(JobSearchFilter::matches(['name' => null, 'queue' => null, 'id' => null], 'invoice'));
    }
}
