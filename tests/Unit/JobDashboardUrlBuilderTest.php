<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Support\Horizon\JobDashboardUrlBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobDashboardUrlBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_a_completed_job_dashboard_url(): void
    {
        $service = new Service;
        $service->forceFill([
            'base_url' => 'http://example.test',
            'public_url' => null,
        ]);

        $url = JobDashboardUrlBuilder::build($service, 'abc-123', 'processed');

        $this->assertSame('http://example.test/horizon/jobs/completed/abc-123', $url);
    }

    #[Test]
    public function it_uses_public_url_when_available(): void
    {
        $service = new Service;
        $service->forceFill([
            'base_url' => 'http://internal.test',
            'public_url' => 'http://public.test',
        ]);

        $url = JobDashboardUrlBuilder::build($service, 'abc-123', 'failed');

        $this->assertSame('http://public.test/horizon/jobs/failed/abc-123', $url);
    }

    #[Test]
    public function it_returns_null_when_required_values_are_missing(): void
    {
        $service = new Service;
        $service->forceFill([
            'base_url' => null,
            'public_url' => null,
        ]);

        $this->assertNull(JobDashboardUrlBuilder::build($service, 'abc-123', 'processed'));
        $this->assertNull(JobDashboardUrlBuilder::build(null, 'abc-123', 'processed'));
        $this->assertNull(JobDashboardUrlBuilder::build($service, '', 'processed'));
    }
}
