<?php

namespace Tests\Unit;

use App\Mail\AlertBatchedMail;
use App\Models\Alert;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Alerts\Rules\Strategies\HorizonOffline;
use App\Services\Horizon\HorizonClientService;
use App\Services\Notifiers\Contracts\AlertNotifierMetadata;
use App\Services\Notifiers\DiscordNotifierService;
use App\Services\Notifiers\EmailNotifierService;
use App\Services\Notifiers\SlackNotifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifiersTest extends TestCase
{
    use RefreshDatabase;

    public function test_discord_notifier_builds_failure_count_payload_with_enriched_event_details(): void
    {
        Http::fake();
        $api = $this->createMock(HorizonClientService::class);
        $api->method('getJob')->willReturn([
            'success' => true,
            'data' => [
                'name' => 'App\\Jobs\\Demo',
                'queue' => 'critical',
                'failed_at' => now()->toIso8601String(),
                'exception' => 'fatal',
                'attempts' => 3,
            ],
        ]);
        $notifier = new DiscordNotifierService($api);
        $alert = Alert::create([
            'name' => 'd',
            'rule_type' => FailureCount::type(),
            'enabled' => true,
            'threshold' => ['count' => 2, 'minutes' => 5, 'queue_patterns' => ['critical']],
        ]);
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://a.test', 'status' => 'online']);
        $events = [
            ['service_id' => $service->id, 'job_uuid' => 'job-1', 'triggered_at' => now()->toIso8601String()],
            ['service_id' => $service->id, 'job_uuid' => null, 'triggered_at' => now()->toIso8601String()],
        ];

        $notifier->sendBatched($alert, $events, ['webhook_url' => 'https://discord.com/api/webhooks/1/token']);

        Http::assertSent(function ($request) {
            $embeds = (array) data_get($request->data(), 'embeds', []);
            $encoded = \json_encode($embeds);

            return \is_string($encoded)
                && str_contains($encoded, 'Failure count in window')
                && str_contains($encoded, 'critical')
                && str_contains($encoded, 'Attempts')
                && str_contains($encoded, 'fatal')
                && str_contains($encoded, 'View alert');
        });
    }

    public function test_discord_notifier_skips_without_webhook_and_posts_payload_when_present(): void
    {
        Http::fake();
        $api = $this->createMock(HorizonClientService::class);
        $notifier = new DiscordNotifierService($api);
        $alert = Alert::create(['name' => 'd', 'rule_type' => HorizonOffline::type(), 'enabled' => true]);
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://a.test', 'status' => 'online']);
        $events = [['service_id' => $service->id, 'job_uuid' => null, 'triggered_at' => now()->toIso8601String()]];

        $notifier->sendBatched($alert, $events, ['webhook_url' => '']);
        Http::assertNothingSent();

        $notifier->sendBatched($alert, $events, ['webhook_url' => 'https://discord.com/api/webhooks/1/token']);
        Http::assertSent(function ($request) {
            return \is_array(data_get($request->data(), 'embeds'))
                && \is_string(data_get($request->data(), 'content'));
        });
    }

    public function test_each_notifier_normalizes_config_from_validated_input(): void
    {
        $this->assertSame(
            ['webhook_url' => 'https://hooks.slack.test/x'],
            SlackNotifierService::normalizedConfig(['webhook_url' => 'https://hooks.slack.test/x']),
        );
        $this->assertSame(
            ['webhook_url' => 'https://discord.com/api/webhooks/1/token'],
            DiscordNotifierService::normalizedConfig(['webhook_url' => 'https://discord.com/api/webhooks/1/token']),
        );
        $this->assertSame(
            ['to' => ['a@example.com', 'b@example.com']],
            EmailNotifierService::normalizedConfig(['email_to' => 'a@example.com, b@example.com']),
        );
    }

    public function test_email_notifier_skips_without_recipients_and_sends_with_recipients(): void
    {
        Mail::fake();
        $api = $this->createMock(HorizonClientService::class);
        $notifier = new EmailNotifierService($api);
        $alert = Alert::create(['name' => 'e', 'rule_type' => FailureCount::type(), 'enabled' => true]);
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://a.test', 'status' => 'online']);
        $events = [['service_id' => $service->id, 'job_uuid' => null, 'triggered_at' => now()->toIso8601String()]];

        $notifier->sendBatched($alert, $events, ['to' => []]);
        Mail::assertNothingSent();

        $notifier->sendBatched($alert, $events, ['to' => ['ops@example.com']]);
        Mail::assertSent(AlertBatchedMail::class, function (AlertBatchedMail $mail): bool {
            return isset($mail->notification['ruleLabel'])
                && $mail->notification['ruleLabel'] === 'Failure count in window';
        });
    }

    public function test_registered_providers_define_type_and_meta(): void
    {
        foreach (NotificationProvider::getProviders() as $type => $class) {
            $this->assertTrue(\is_subclass_of($class, AlertNotifierMetadata::class));
            $this->assertSame($type, $class::type());
            $meta = $class::meta();

            foreach (['label', 'icon', 'description', 'color'] as $key) {
                $this->assertArrayHasKey($key, $meta);
                $this->assertNotSame('', $meta[$key]);
            }
        }

    }

    public function test_slack_notifier_builds_failure_count_payload_with_enriched_event_details(): void
    {
        Http::fake();
        $api = $this->createMock(HorizonClientService::class);
        $api->method('getJob')->willReturn([
            'success' => true,
            'data' => [
                'name' => 'App\\Jobs\\Demo',
                'queue' => 'critical',
                'failed_at' => now()->toIso8601String(),
                'exception' => 'fatal',
                'attempts' => 3,
            ],
        ]);
        $notifier = new SlackNotifierService($api);
        $alert = Alert::create([
            'name' => 's',
            'rule_type' => FailureCount::type(),
            'enabled' => true,
            'threshold' => ['count' => 2, 'minutes' => 5, 'queue_patterns' => ['critical']],
        ]);
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://a.test', 'status' => 'online']);
        $events = [
            ['service_id' => $service->id, 'job_uuid' => 'job-1', 'triggered_at' => now()->toIso8601String()],
            ['service_id' => $service->id, 'job_uuid' => null, 'triggered_at' => now()->toIso8601String()],
        ];

        $notifier->sendBatched($alert, $events, ['webhook_url' => 'https://hooks.slack.test/abc']);

        Http::assertSent(function ($request) {
            $blocks = (array) data_get($request->data(), 'blocks', []);
            $encoded = \json_encode($blocks);

            return \is_string($encoded)
                && str_contains($encoded, 'Failure count in window')
                && str_contains($encoded, 'critical')
                && str_contains($encoded, 'Attempts')
                && str_contains($encoded, 'fatal')
                && str_contains($encoded, 'View alert');
        });
    }

    public function test_slack_notifier_skips_without_webhook_and_posts_payload_when_present(): void
    {
        Http::fake();
        $api = $this->createMock(HorizonClientService::class);
        $notifier = new SlackNotifierService($api);
        $alert = Alert::create(['name' => 's', 'rule_type' => HorizonOffline::type(), 'enabled' => true]);
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://a.test', 'status' => 'online']);
        $events = [['service_id' => $service->id, 'job_uuid' => null, 'triggered_at' => now()->toIso8601String()]];

        $notifier->sendBatched($alert, $events, ['webhook_url' => '']);
        Http::assertNothingSent();

        $notifier->sendBatched($alert, $events, ['webhook_url' => 'https://hooks.slack.test/abc']);
        Http::assertSent(function ($request) {
            return \is_array(data_get($request->data(), 'blocks'))
                && \is_string(data_get($request->data(), 'text'));
        });
    }
}
