<?php

namespace Tests\Feature;

use App\Models\NotificationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_provider_stream_container(): void
    {
        $response = $this->get(route('horizon.providers.index'));

        $response->assertOk();
        $response->assertViewHas('defer', true);
        $response->assertSee('id="turbo-tbody-horizon-provider-list"', false);
    }

    public function test_store_creates_slack_provider_with_webhook_config(): void
    {
        $response = $this->post(route('horizon.providers.store'), [
            'name' => 'ops-slack',
            'type' => NotificationProvider::TYPE_SLACK,
            'webhook_url' => 'https://hooks.slack.test/services/T/B',
        ]);

        $response->assertRedirect(route('horizon.providers.index'));
        $response->assertSessionHas('status', 'Provider created.');
        $this->assertDatabaseHas('notification_providers', [
            'name' => 'ops-slack',
            'type' => NotificationProvider::TYPE_SLACK,
        ]);
        $provider = NotificationProvider::query()->where('name', 'ops-slack')->firstOrFail();
        $this->assertSame('https://hooks.slack.test/services/T/B', $provider->config['webhook_url'] ?? null);
    }

    public function test_store_normalizes_email_recipients_and_rejects_invalid_addresses(): void
    {
        $validResponse = $this->post(route('horizon.providers.store'), [
            'name' => 'ops-mail',
            'type' => NotificationProvider::TYPE_EMAIL,
            'email_to' => '  a@example.com , b@example.com ',
        ]);

        $validResponse->assertRedirect(route('horizon.providers.index'));
        $provider = NotificationProvider::query()->where('name', 'ops-mail')->firstOrFail();
        $this->assertSame(['a@example.com', 'b@example.com'], $provider->getToEmails());

        $invalidResponse = $this->post(route('horizon.providers.store'), [
            'name' => 'bad-mail',
            'type' => NotificationProvider::TYPE_EMAIL,
            'email_to' => 'not-an-email',
        ]);

        $invalidResponse->assertStatus(422);
    }

    public function test_update_and_destroy_provider_paths(): void
    {
        $provider = NotificationProvider::query()->create([
            'name' => 'old-name',
            'type' => NotificationProvider::TYPE_SLACK,
            'config' => ['webhook_url' => 'https://hooks.slack.test/old'],
        ]);

        $this->put(route('horizon.providers.update', ['provider' => $provider]), [
            'name' => 'new-name',
            'type' => NotificationProvider::TYPE_SLACK,
            'webhook_url' => 'https://hooks.slack.test/new',
        ])->assertRedirect(route('horizon.providers.index'))
            ->assertSessionHas('status', 'Provider updated.');

        $this->assertDatabaseHas('notification_providers', [
            'id' => $provider->id,
            'name' => 'new-name',
        ]);

        $this->delete(route('horizon.providers.destroy', ['provider' => $provider]))
            ->assertRedirect(route('horizon.providers.index'))
            ->assertSessionHas('status', 'Provider deleted.');

        $this->assertDatabaseMissing('notification_providers', ['id' => $provider->id]);
    }
}
