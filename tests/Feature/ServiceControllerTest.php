<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Support\FormDrawer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_edit_show_store_update_destroy_and_connection_paths(): void
    {
        $service = Service::query()->create([
            'name' => 'svc-a',
            'base_url' => 'https://svc-a.test',
            'public_url' => 'https://public-a.test',
            'status' => 'offline',
        ]);

        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('ping')->willReturnOnConsecutiveCalls(
            ['success' => true],
            ['success' => false, 'message' => 'failed ping'],
        );
        $api->expects($this->once())
            ->method('resetFailureCooldown')
            ->with($this->callback(function (Service $cooldownService) use ($service): bool {
                return $cooldownService->id === $service->id;
            }));
        $this->app->instance(HorizonApiProxyService::class, $api);

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
        $created = Service::query()->where('name', 'svc-b')->first();
        $this->assertNotNull($created);
        $this->assertSame(['mailing', 'production'], $created->tags);

        $this->put(route('horizon.services.update', ['service' => $service]), [
            'name' => 'svc-a-updated',
            'base_url' => 'https://svc-a-updated.test/',
            'public_url' => '',
        ])->assertRedirect(route('horizon.services.index'));

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

        $service = Service::query()->where('name', 'svc-headers')->firstOrFail();

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

        $service = Service::query()->where('name', 'svc-ws-empty')->firstOrFail();

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
}
