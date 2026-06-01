<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Horizon\HorizonClientService;
use App\Services\Notifiers\AbstractAlertNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbstractAlertNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrich_events_includes_all_batch_events(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $alert = Alert::query()->create(['name' => 'a', 'rule_type' => FailureCount::type(), 'enabled' => true]);

        $api = $this->createMock(HorizonClientService::class);
        $api->method('getJob')->willReturn(['success' => false]);

        $notifier = new class($api) extends AbstractAlertNotifier
        {
            public static function meta(): array
            {
                return ['label' => 'Test', 'icon' => 'test', 'description' => 'Test', 'color' => 'gray'];
            }

            public static function normalizedConfig(array $validated): array
            {
                return [];
            }

            public static function type(): string
            {
                return 'test';
            }

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

        $enriched = (new \ReflectionMethod($notifier, 'enrichEvents'))->invoke($notifier, $events);

        $this->assertCount(12, $enriched);
    }

    public function test_send_and_enrich_events_preserves_full_exception_text(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $alert = Alert::query()->create(['name' => 'a', 'rule_type' => FailureCount::type(), 'enabled' => true]);
        $exception = "line1\nline2\nline3\nline4\nline5\nline6";

        $api = $this->createMock(HorizonClientService::class);
        $api->method('getJob')->willReturn([
            'success' => true,
            'data' => [
                'name' => 'App\\Jobs\\Demo',
                'queue' => 'default',
                'failed_at' => now()->toIso8601String(),
                'exception' => $exception,
                'attempts' => 2,
            ],
        ]);

        $notifier = new class($api) extends AbstractAlertNotifier
        {
            public array $captured = [];

            public static function meta(): array
            {
                return ['label' => 'Test', 'icon' => 'test', 'description' => 'Test', 'color' => 'gray'];
            }

            public static function normalizedConfig(array $validated): array
            {
                return [];
            }

            public static function type(): string
            {
                return 'test';
            }

            public function sendBatched(Alert $alert, array $events, array $config): void
            {
                $this->captured = $this->enrichEvents($events);
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
