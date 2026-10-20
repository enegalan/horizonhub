<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_with_aggregated_data_and_filters(): void
    {
        $service = Service::create(['name' => 'svc-a', 'base_url' => 'https://a.test', 'status' => 'online']);

        $response = $this->get(route('horizon.jobs.index', ['search' => 'job', 'service_id' => [$service->id]]));
        $response->assertOk();
    }

    public function test_show_redirects_to_the_jobs_index_when_the_job_is_gone(): void
    {
        $response = $this->get(route('horizon.jobs.show', ['job' => 'job-1']));

        $response->assertRedirect(route('horizon.jobs.index'));
        $response->assertSessionHas('status', [
            'message' => 'Job not found or no longer available.',
            'type' => 'error',
        ]);
    }

    public function test_show_renders_job_detail_shell_when_the_job_resolves(): void
    {
        Service::create(['name' => 'svc-a', 'base_url' => 'https://a.test', 'status' => 'online']);

        Http::fake([
            '*/horizon/api/jobs/job-1' => Http::response([
                'id' => 'job-1',
                'name' => 'App\\Jobs\\Demo',
                'queue' => 'default',
                'status' => 'failed',
                'payload' => [],
            ], 200),
        ]);

        $response = $this->get(route('horizon.jobs.show', ['job' => 'job-1']));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('id="horizon-job-detail-meta"', $html);
        $this->assertStringContainsString('id="horizon-job-detail-data"', $html);
        $this->assertStringContainsString('id="horizon-job-detail-payload"', $html);
    }
}
