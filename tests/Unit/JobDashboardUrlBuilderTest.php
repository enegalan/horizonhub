<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Support\PathBuilder;
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

        $url = PathBuilder::jobDashboard($service, 'abc-123', 'processed');

        $this->assertSame('http://example.test/horizon/jobs/completed/abc-123', $url);
    }

    #[Test]
    public function it_builds_pending_and_default_paths_and_encodes_uuid(): void
    {
        $service = new Service;
        $service->forceFill([
            'base_url' => 'http://example.test/',
            'public_url' => null,
        ]);

        $pending = PathBuilder::jobDashboard($service, 'abc 123', 'pending');
        $unknown = PathBuilder::jobDashboard($service, 'abc 123', 'unknown');

        $this->assertSame('http://example.test/horizon/jobs/pending/abc+123', $pending);
        $this->assertSame('http://example.test/horizon/jobs/pending/abc+123', $unknown);
    }

    #[Test]
    public function it_returns_null_when_service_or_job_uuid_is_missing(): void
    {
        $service = new Service;
        $service->forceFill([
            'base_url' => 'http://example.test',
            'public_url' => null,
        ]);

        $this->assertNull(PathBuilder::jobDashboard(null, 'abc-123', 'processed'));
        $this->assertNull(PathBuilder::jobDashboard($service, '', 'processed'));
    }

    #[Test]
    public function it_uses_public_url_when_available(): void
    {
        $service = new Service;
        $service->forceFill([
            'base_url' => 'http://internal.test',
            'public_url' => 'http://public.test',
        ]);

        $url = PathBuilder::jobDashboard($service, 'abc-123', 'failed');

        $this->assertSame('http://public.test/horizon/failed/abc-123', $url);
    }
}
