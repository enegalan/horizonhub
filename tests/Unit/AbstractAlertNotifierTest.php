<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Notifiers\AbstractAlertNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbstractAlertNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_and_enrich_events_and_truncate_exception_paths(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $alert = Alert::query()->create(['name' => 'a', 'rule_type' => Alert::RULE_FAILURE_COUNT, 'enabled' => true]);

        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('getJob')->willReturn([
            'success' => true,
            'data' => [
                'name' => 'App\\Jobs\\Demo',
                'queue' => 'default',
                'failed_at' => now()->toIso8601String(),
                'exception' => "line1\nline2\nline3",
                'attempts' => 2,
            ],
        ]);

        $notifier = new class($api) extends AbstractAlertNotifier
        {
            public array $captured = [];

            public function sendBatched(Alert $alert, array $events, array $config): void
            {
                $this->captured = $this->enrichEvents($events, 5, 10);
            }

            public function public__truncate(string $text, int $max): string
            {
                return $this->truncateException($text, $max);
            }
        };

        $notifier->send($alert, $service->id, 'u1', []);
        $this->assertCount(1, $notifier->captured);
        $this->assertSame('App\\Jobs\\Demo', $notifier->captured[0]['job_class']);
        $this->assertSame('default', $notifier->captured[0]['queue']);
        $this->assertSame(2, $notifier->captured[0]['attempts']);

        $truncated = $notifier->public__truncate("abc\ndef\nghi", 7);
        $this->assertStringContainsString('...', $truncated);
    }
}
