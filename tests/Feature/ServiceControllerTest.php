<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\Horizon\HorizonClientCacheService;
use App\Support\FormDrawer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_edit_show_store_update_destroy_and_connection_paths(): void
    {
        $service = Service::create([
            'name' => 'svc-a',
            'base_url' => 'https://svc-a.test',
            'public_url' => 'https://public-a.test',
            'status' => 'offline',
        ]);

        \config()->set('horizonhub.horizon_http_retry', ['times' => 1, 'sleep_ms' => 0, 'retry_on_status' => []]);

        Http::fake([
            'https://svc-a-updated.test/horizon/api/stats' => Http::sequence()
                ->push(['status' => 'running'], 200)
                ->push(['message' => 'failed ping'], 500),
        ]);

        $this->get(route('horizon.services.index'))->assertOk();
        Service::factory()->create(['tags' => ['production']]);

        $formDrawerHeaders = ['Turbo-Frame' => FormDrawer::FRAME_ID];

        $this->get(route('horizon.services.create'), $formDrawerHeaders)
            ->assertOk()
            ->assertSee('production', false);
        $this->get(route('horizon.services.edit', ['service' => $service]), $formDrawerHeaders)
            ->assertOk()
            ->assertSee('production', false);
        $this->get(route('horizon.services.show', ['service' => $service]))
            ->assertOk()
            ->assertDontSee('Supervisor data is not available', false)
            ->assertDontSee('No queues for this service yet', false);

        $this->post(route('horizon.services.store'), [
            'name' => 'svc-b',
            'base_url' => 'https://svc-b.test/',
            'public_url' => 'https://public-b.test/',
            'tags' => ['Production', ' mailing '],
        ])->assertRedirect(route('horizon.services.index'));
        $this->assertDatabaseHas('services', ['name' => 'svc-b', 'base_url' => 'https://svc-b.test', 'public_url' => 'https://public-b.test']);
        $created = Service::where('name', 'svc-b')->first();
        $this->assertNotNull($created);
        $this->assertSame(['mailing', 'production'], $created->tags);

        Cache::put(HorizonClientCacheService::failureCooldownCacheKey($service), true, now()->addMinutes(1));

        $this->put(route('horizon.services.update', ['service' => $service]), [
            'name' => 'svc-a-updated',
            'base_url' => 'https://svc-a-updated.test/',
            'public_url' => '',
        ])->assertRedirect(route('horizon.services.index'));

        $this->assertFalse(Cache::has(HorizonClientCacheService::failureCooldownCacheKey($service)));

        $this->post(route('horizon.services.test-connection', ['service' => $service]))
            ->assertRedirect()
            ->assertSessionHas('status', [
                'message' => 'Service Horizon API is reachable.',
                'type' => 'success',
            ]);
        $service->refresh();
        $this->assertSame('online', $service->status);

        $this->post(route('horizon.services.test-connection', ['service' => $service]))
            ->assertRedirect()
            ->assertSessionHas('status', [
                'message' => 'failed ping',
                'type' => 'error',
            ]);
        $service->refresh();
        $this->assertSame('offline', $service->status);

        $this->post(route('horizon.services.toggle-enabled', ['service' => $service]))
            ->assertOk()
            ->assertJson(['service_id' => $service->id, 'enabled' => false]);
        $service->refresh();
        $this->assertFalse($service->enabled);

        $this->post(route('horizon.services.toggle-enabled', ['service' => $service]))
            ->assertOk()
            ->assertJson(['enabled' => true]);

        $this->delete(route('horizon.services.destroy', ['service' => $service]))->assertRedirect(route('horizon.services.index'));
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    public function test_store_and_update_persist_headers_including_name_only_rows(): void
    {
        $this->post(route('horizon.services.store'), [
            'name' => 'svc-headers',
            'base_url' => 'https://svc-headers.test/',
            'headers' => [
                ['name' => 'Authorization', 'value' => 'Bearer abc'],
                ['name' => 'X-Feature-Flag', 'value' => ''],
            ],
        ])->assertRedirect(route('horizon.services.index'));

        $service = Service::where('name', 'svc-headers')->firstOrFail();

        $this->assertDatabaseHas('service_headers', [
            'service_id' => $service->id,
            'name' => 'Authorization',
            'value' => 'Bearer abc',
        ]);
        $this->assertDatabaseHas('service_headers', [
            'service_id' => $service->id,
            'name' => 'X-Feature-Flag',
            'value' => null,
        ]);

        $this->put(route('horizon.services.update', ['service' => $service]), [
            'name' => 'svc-headers',
            'base_url' => 'https://svc-headers.test/',
            'headers' => [
                ['name' => 'X-Api-Key', 'value' => 'key-1'],
            ],
        ])->assertRedirect(route('horizon.services.index'));

        $this->assertDatabaseMissing('service_headers', [
            'service_id' => $service->id,
            'name' => 'Authorization',
        ]);
        $this->assertDatabaseHas('service_headers', [
            'service_id' => $service->id,
            'name' => 'X-Api-Key',
            'value' => 'key-1',
        ]);
    }

    public function test_store_ignores_header_row_with_only_whitespace_in_name_and_value(): void
    {
        $this->post(route('horizon.services.store'), [
            'name' => 'svc-ws-empty',
            'base_url' => 'https://svc-ws-empty.test/',
            'headers' => [
                ['name' => 'Authorization', 'value' => 'Bearer abc'],
                ['name' => '   ', 'value' => '   '],
            ],
        ])->assertRedirect(route('horizon.services.index'));

        $service = Service::where('name', 'svc-ws-empty')->firstOrFail();

        $this->assertDatabaseCount('service_headers', 1);
        $this->assertDatabaseHas('service_headers', [
            'service_id' => $service->id,
            'name' => 'Authorization',
        ]);
    }

    public function test_store_rejects_duplicate_header_names(): void
    {
        $this->from(route('horizon.services.create'))
            ->post(route('horizon.services.store'), [
                'name' => 'svc-dup-headers',
                'base_url' => 'https://svc-dup-headers.test/',
                'headers' => [
                    ['name' => 'Authorization', 'value' => 'one'],
                    ['name' => 'authorization', 'value' => 'two'],
                ],
            ])
            ->assertRedirect(route('horizon.services.create'))
            ->assertSessionHasErrors(['headers.1.name']);

        $this->assertDatabaseMissing('services', ['name' => 'svc-dup-headers']);
    }

    public function test_store_rejects_header_name_with_only_whitespace_when_value_is_set(): void
    {
        $this->from(route('horizon.services.create'))
            ->post(route('horizon.services.store'), [
                'name' => 'svc-ws-header',
                'base_url' => 'https://svc-ws-header.test/',
                'headers' => [
                    ['name' => '   ', 'value' => 'secret'],
                ],
            ])
            ->assertRedirect(route('horizon.services.create'))
            ->assertSessionHasErrors(['headers.0.name']);

        $this->assertDatabaseMissing('services', ['name' => 'svc-ws-header']);
    }

    public function test_store_rejects_reserved_header_names(): void
    {
        $this->from(route('horizon.services.create'))
            ->post(route('horizon.services.store'), [
                'name' => 'svc-reserved-header',
                'base_url' => 'https://svc-reserved-header.test/',
                'headers' => [
                    ['name' => 'Host', 'value' => 'evil.example'],
                ],
            ])
            ->assertRedirect(route('horizon.services.create'))
            ->assertSessionHasErrors(['headers.0.name']);

        $this->assertDatabaseMissing('services', ['name' => 'svc-reserved-header']);
    }

    public function test_test_connection_returns_warning_flash_when_upstream_times_out(): void
    {
        $service = Service::create([
            'name' => 'svc-timeout-flash',
            'base_url' => 'https://service-timeout-flash.test',
            'status' => 'online',
        ]);

        \config()->set('horizonhub.api_timeout', 10);
        \config()->set('horizonhub.horizon_http_retry', ['times' => 1, 'sleep_ms' => 0, 'retry_on_status' => []]);

        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 10001 milliseconds with 0 bytes received'));

        $this->post(route('horizon.services.test-connection', ['service' => $service]))
            ->assertRedirect()
            ->assertSessionHas('status.type', 'warning');

        $status = \session('status');
        $this->assertIsArray($status);
        $this->assertStringContainsString('HORIZON_HUB_API_TIMEOUT', (string) ($status['message'] ?? ''));
        $this->assertStringContainsString('10s', (string) ($status['message'] ?? ''));

        $service->refresh();
        $this->assertSame('offline', $service->status);
    }
}
