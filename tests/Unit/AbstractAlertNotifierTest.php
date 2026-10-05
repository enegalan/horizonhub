<?php

namespace Tests\Unit;

use App\Enums\NotificationProviderType;
use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Notifiers\AbstractAlertNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AbstractAlertNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_notification_reads_first_pending_event_from_sparse_array(): void
    {
        $service = Service::create(['name' => 'svc-sparse', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $alert = Alert::create(['name' => 'a', 'rule_type' => FailureCount::type(), 'enabled' => true]);

        $notifier = new class extends AbstractAlertNotifier
        {
            /**
             * Return fixed provider metadata to satisfy the notifier metadata contract.
             *
             * This anonymous class stands in for a concrete notifier service, so the
             * inherited `buildNotification()` logic can be tested without depending on
             * any real provider.
             *
             * @return array{label: string, icon: string, description: string, color: string}
             */
            public static function meta(): array
            {
                return ['label' => 'Test', 'icon' => 'test', 'description' => 'Test', 'color' => 'gray'];
            }

            /**
             * Discard the validated provider config and return an empty array.
             *
             * The fake has no delivery endpoint, so there is nothing to normalize.
             *
             * @param array<string, mixed> $validated The validated provider config, ignored by the fake.
             *
             * @return array<string, mixed>
             */
            public static function normalizedConfig(array $validated): array
            {
                return [];
            }

            /**
             * Claim the email provider type so the fake looks like a real notifier.
             */
            public static function type(): NotificationProviderType
            {
                return NotificationProviderType::Email;
            }

            /**
             * Swallow the batch, because this test calls `buildNotification()` directly.
             */
            public function sendBatched(Alert $alert, array $events, array $config): void {}
        };

        $events = [
            2 => [
                'service_id' => $service->id,
                'job_uuid' => null,
                'triggered_at' => now()->toIso8601String(),
            ],
        ];

        $notification = (new \ReflectionMethod($notifier, 'buildNotification'))->invoke($notifier, $alert, $events);

        $this->assertSame('svc-sparse', $notification['serviceName']);
        $this->assertSame(1, $notification['totalEventCount']);
    }

    public function test_enrich_events_includes_all_batch_events(): void
    {
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $alert = Alert::create(['name' => 'a', 'rule_type' => FailureCount::type(), 'enabled' => true]);

        $notifier = new class extends AbstractAlertNotifier
        {
            /**
             * Return fixed provider metadata to satisfy the notifier metadata contract.
             *
             * This anonymous class stands in for a concrete notifier service, so the
             * inherited `enrichEvents()` logic can be tested without depending on any
             * real provider.
             *
             * @return array{label: string, icon: string, description: string, color: string}
             */
            public static function meta(): array
            {
                return ['label' => 'Test', 'icon' => 'test', 'description' => 'Test', 'color' => 'gray'];
            }

            /**
             * Discard the validated provider config and return an empty array.
             *
             * The fake has no delivery endpoint, so there is nothing to normalize.
             *
             * @param array<string, mixed> $validated The validated provider config, ignored by the fake.
             *
             * @return array<string, mixed>
             */
            public static function normalizedConfig(array $validated): array
            {
                return [];
            }

            /**
             * Claim the email provider type so the fake looks like a real notifier.
             */
            public static function type(): NotificationProviderType
            {
                return NotificationProviderType::Email;
            }

            /**
             * Swallow the batch, because this test calls `enrichEvents()` directly.
             */
            public function sendBatched(Alert $alert, array $events, array $config): void {}
        };

        $events = [];

        for ($i = 0; $i < 12; $i++) {
            $events[] = [
                'service_id' => $service->id,
                'job_uuid' => null,
                'triggered_at' => now()->toIso8601String(),
            ];
        }

        $enriched = (new \ReflectionMethod($notifier, 'enrichEvents'))->invoke($notifier, $events, $service);

        $this->assertCount(12, $enriched);
    }

    public function test_send_and_enrich_events_preserves_full_exception_text(): void
    {
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $alert = Alert::create(['name' => 'a', 'rule_type' => FailureCount::type(), 'enabled' => true]);
        $exception = "line1\nline2\nline3\nline4\nline5\nline6";

        Http::fake(function ($request) use ($exception) {
            if ($request->method() === 'GET' && \str_contains($request->url(), '/horizon/api/jobs/')) {
                return Http::response([
                    'name' => 'App\\Jobs\\Demo',
                    'queue' => 'default',
                    'failed_at' => now()->toIso8601String(),
                    'exception' => $exception,
                    'attempts' => 2,
                ], 200);
            }

            return Http::response('unexpected', 500);
        });

        $notifier = new class extends AbstractAlertNotifier
        {
            public array $captured = [];

            /**
             * Return fixed provider metadata to satisfy the notifier metadata contract.
             *
             * This anonymous class stands in for a concrete notifier service, so
             * `send()` and the inherited `enrichEvents()` logic can be tested without
             * depending on any real provider.
             *
             * @return array{label: string, icon: string, description: string, color: string}
             */
            public static function meta(): array
            {
                return ['label' => 'Test', 'icon' => 'test', 'description' => 'Test', 'color' => 'gray'];
            }

            /**
             * Discard the validated provider config and return an empty array.
             *
             * The fake has no delivery endpoint, so there is nothing to normalize.
             *
             * @param array<string, mixed> $validated The validated provider config, ignored by the fake.
             *
             * @return array<string, mixed>
             */
            public static function normalizedConfig(array $validated): array
            {
                return [];
            }

            /**
             * Claim the email provider type so the fake looks like a real notifier.
             */
            public static function type(): NotificationProviderType
            {
                return NotificationProviderType::Email;
            }

            /**
             * Record the enriched events instead of sending them to a provider.
             *
             * The fake resolves the service from the first event and stores the
             * `enrichEvents()` output on `$captured`, so the test can assert on the
             * job details fetched from the Horizon API. The real notifiers would
             * hand the same enriched batch to their webhook/email renderer.
             *
             * @param Alert $alert The triggering alert, unused by the fake.
             * @param array<int, array<string, mixed>> $events The events enriched and captured.
             * @param array<string, mixed> $config The provider config, unused by the fake.
             */
            public function sendBatched(Alert $alert, array $events, array $config): void
            {
                $this->captured = $this->enrichEvents($events, Service::find((int) ($events[0]['service_id'] ?? 0)));
            }
        };

        $notifier->send($alert, $service->id, 'u1', []);
        $this->assertCount(1, $notifier->captured);
        $this->assertSame('App\\Jobs\\Demo', $notifier->captured[0]['job_class']);
        $this->assertSame('default', $notifier->captured[0]['queue']);
        $this->assertSame(2, $notifier->captured[0]['attempts']);
        $this->assertSame($exception, $notifier->captured[0]['exception']);
        $this->assertStringNotContainsString('...', (string) $notifier->captured[0]['exception']);
    }
}
