<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
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
        $this->get(route('horizon.services.edit', ['service' => $service]))->assertOk();
        $this->get(route('horizon.services.show', ['service' => $service]))
            ->assertOk()
            ->assertDontSee('Supervisor data is not available', false)
            ->assertDontSee('No queues for this service yet', false);

        $this->post(route('horizon.services.store'), [
            'name' => 'svc-b',
            'base_url' => 'https://svc-b.test/',
            'public_url' => 'https://public-b.test/',
        ])->assertRedirect(route('horizon.services.index'));
        $this->assertDatabaseHas('services', ['name' => 'svc-b', 'base_url' => 'https://svc-b.test', 'public_url' => 'https://public-b.test']);

        $this->put(route('horizon.services.update', ['service' => $service]), [
            'name' => 'svc-a-updated',
            'base_url' => 'https://svc-a-updated.test/',
            'public_url' => '',
        ])->assertRedirect(route('horizon.services.index'));

        $this->post(route('horizon.services.test-connection', ['service' => $service]))->assertRedirect();
        $service->refresh();
        $this->assertSame('online', $service->status);

        $this->post(route('horizon.services.test-connection', ['service' => $service]))->assertRedirect();
        $service->refresh();
        $this->assertSame('offline', $service->status);

        $this->delete(route('horizon.services.destroy', ['service' => $service]))->assertRedirect(route('horizon.services.index'));
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }
}
