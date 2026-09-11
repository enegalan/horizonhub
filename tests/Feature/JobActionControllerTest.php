<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobActionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_list_filters_services_by_tag(): void
    {
        $matching = Service::create([
            'name' => 'svc-tagged',
            'base_url' => 'https://tagged.test',
            'status' => 'online',
            'tags' => ['production'],
        ]);
        Service::create([
            'name' => 'svc-other',
            'base_url' => 'https://other.test',
            'status' => 'online',
            'tags' => ['staging'],
        ]);

        Http::fake(function ($request) use ($matching) {
            if (\str_contains($request->url(), $matching->base_url . '/horizon/api/jobs/failed')) {
                return Http::response(['jobs' => [
                    ['id' => 'f1', 'queue' => 'default', 'name' => 'F1', 'failed_at' => '2024-01-01 00:00:00'],
                ]], 200);
            }

            return Http::response('unexpected', 500);
        });

        $response = $this->getJson(route('horizon.jobs.failed', [
            'selection' => 'all',
            'service_tag' => ['production'],
        ]));

        $response->assertOk();
        Http::assertSent(fn (Request $request): bool => \str_contains($request->url(), $matching->base_url . '/horizon/api/jobs/failed'));
    }

    public function test_failed_list_returns_empty_meta_when_no_services_match(): void
    {
        $response = $this->getJson(route('horizon.jobs.failed'));
        $response->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_failed_list_selection_all_returns_compact_jobs_shape(): void
    {
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);

        Http::fake(function ($request) use ($service) {
            if (\str_contains($request->url(), $service->base_url . '/horizon/api/jobs/failed')) {
                return Http::response(['jobs' => [
                    ['id' => 'u1', 'queue' => 'default', 'name' => 'U1', 'failed_at' => '2024-01-01 00:00:00'],
                    ['id' => 'u2', 'queue' => 'default', 'name' => 'U2', 'failed_at' => '2024-01-01 01:00:00'],
                ]], 200);
            }

            return Http::response('unexpected', 500);
        });

        $response = $this->getJson(route('horizon.jobs.failed', ['selection' => 'all', 'service_ids' => [$service->id]]));
        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('jobs.0.id', 'u2')
            ->assertJsonPath('jobs.1.id', 'u1')
            ->assertJsonPath('jobs.0.service_id', $service->id);
    }

    public function test_failed_list_selection_all_returns_empty_jobs_when_no_service_matches(): void
    {
        $response = $this->getJson(route('horizon.jobs.failed', ['selection' => 'all']));
        $response->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('jobs', []);
    }

    public function test_retry_and_retry_batch_handle_success_and_service_missing_cases(): void
    {
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);

        Http::fake(function (Request $request) use ($service) {
            if ($request->url() === $service->base_url . '/horizon') {
                return Http::response('<html><head><meta name="csrf-token" content="csrf-123"></head></html>', 200);
            }

            if ($request->method() === 'POST' && \str_contains($request->url(), '/horizon/api/jobs/retry/u-1')) {
                return Http::response(['status' => 'succeeded'], 200);
            }

            if ($request->method() === 'POST' && \str_contains($request->url(), '/horizon/api/jobs/retry/u-2')) {
                return Http::response(['message' => 'retry failed'], 500);
            }

            return Http::response('unexpected', 500);
        });
        $this->postJson(route('horizon.jobs.retry'), [
            'uuid' => 'u-1',
            'service_id' => $service->id,
        ])->assertOk()->assertJsonPath('message', 'Retry requested');

        $this->postJson(route('horizon.jobs.retry-batch'), [
            'jobs' => [
                ['id' => 'u-1', 'service_id' => $service->id],
                ['id' => 'u-2', 'service_id' => $service->id],
            ],
        ])->assertOk()
            ->assertJsonPath('requested', 2)
            ->assertJsonPath('succeeded', 1)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('results.1.message', 'retry failed');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && \str_contains($request->url(), '/horizon/api/jobs/retry/u-1'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && \str_contains($request->url(), '/horizon/api/jobs/retry/u-2'));
    }

    public function test_retry_batch_returns_failed_result_when_api_retry_fails(): void
    {
        $service = Service::create(['name' => 'svc-retry-fail', 'base_url' => 'https://retry-fail.test', 'status' => 'online']);

        Http::fake(function (Request $request) use ($service) {
            if ($request->url() === $service->base_url . '/horizon') {
                return Http::response('<html><head><meta name="csrf-token" content="csrf-123"></head></html>', 200);
            }

            if ($request->method() === 'POST' && \str_contains($request->url(), '/horizon/api/jobs/retry/u-retry-fail')) {
                return Http::response(['message' => 'api fail'], 500);
            }

            return Http::response('unexpected', 500);
        });
        $this->postJson(route('horizon.jobs.retry-batch'), [
            'jobs' => [
                ['id' => 'u-retry-fail', 'service_id' => $service->id],
            ],
        ])->assertOk()
            ->assertJsonPath('requested', 1)
            ->assertJsonPath('succeeded', 0)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('results.0.message', 'api fail');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && \str_contains($request->url(), '/horizon/api/jobs/retry/u-retry-fail'));
    }

    public function test_retry_requires_valid_payload_and_returns_422_for_invalid_data(): void
    {
        $response = $this->postJson(route('horizon.jobs.retry'), []);
        $response->assertStatus(422);
    }

    public function test_retry_returns_api_error_status_and_message_when_retry_fails(): void
    {
        $service = Service::create(['name' => 'svc2', 'base_url' => 'https://x2.test', 'status' => 'online']);

        Http::fake(function (Request $request) use ($service) {
            if ($request->url() === $service->base_url . '/horizon') {
                return Http::response('<html><head><meta name="csrf-token" content="csrf-123"></head></html>', 200);
            }

            if ($request->method() === 'POST' && \str_contains($request->url(), '/horizon/api/jobs/retry/u-fail')) {
                return Http::response(['message' => 'gateway issue'], 502);
            }

            return Http::response('unexpected', 500);
        });
        $this->postJson(route('horizon.jobs.retry'), [
            'uuid' => 'u-fail',
            'service_id' => $service->id,
        ])->assertStatus(502)->assertJsonPath('message', 'gateway issue');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && \str_contains($request->url(), '/horizon/api/jobs/retry/u-fail'));
    }
}
