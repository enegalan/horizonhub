<?php

namespace Tests\Unit;

use App\Mail\AlertBatchedMail;
use App\Models\Alert;
use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Notifiers\EmailNotifier;
use App\Services\Notifiers\SlackNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifiersTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_notifier_skips_without_recipients_and_sends_with_recipients(): void
    {
        Mail::fake();
        $api = $this->createMock(HorizonApiProxyService::class);
        $notifier = new EmailNotifier($api);
        $alert = Alert::query()->create(['name' => 'e', 'rule_type' => Alert::RULE_FAILURE_COUNT, 'enabled' => true]);
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://a.test', 'status' => 'online']);
        $events = [['service_id' => $service->id, 'job_uuid' => null, 'triggered_at' => now()->toIso8601String()]];

        $notifier->sendBatched($alert, $events, ['to' => []]);
        Mail::assertNothingSent();

        $notifier->sendBatched($alert, $events, ['to' => ['ops@example.com']]);
        Mail::assertSent(AlertBatchedMail::class);
    }

    public function test_slack_notifier_skips_without_webhook_and_posts_payload_when_present(): void
    {
        Http::fake();
        $api = $this->createMock(HorizonApiProxyService::class);
        $notifier = new SlackNotifier($api);
        $alert = Alert::query()->create(['name' => 's', 'rule_type' => Alert::RULE_HORIZON_OFFLINE, 'enabled' => true]);
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://a.test', 'status' => 'online']);
        $events = [['service_id' => $service->id, 'job_uuid' => null, 'triggered_at' => now()->toIso8601String()]];

        $notifier->sendBatched($alert, $events, ['webhook_url' => '']);
        Http::assertNothingSent();

        $notifier->sendBatched($alert, $events, ['webhook_url' => 'https://hooks.slack.test/abc']);
        Http::assertSentCount(1);
    }

    public function test_slack_notifier_builds_failure_count_payload_with_enriched_event_details(): void
    {
        Http::fake();
        $api = $this->createMock(HorizonApiProxyService::class);
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
        $notifier = new SlackNotifier($api);
        $alert = Alert::query()->create([
            'name' => 's',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'enabled' => true,
            'queue' => 'critical',
            'threshold' => ['count' => 2, 'minutes' => 5],
        ]);
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://a.test', 'status' => 'online']);
        $events = [
            ['service_id' => $service->id, 'job_uuid' => 'job-1', 'triggered_at' => now()->toIso8601String()],
            ['service_id' => $service->id, 'job_uuid' => null, 'triggered_at' => now()->toIso8601String()],
        ];

        $notifier->sendBatched($alert, $events, ['webhook_url' => 'https://hooks.slack.test/abc']);

        Http::assertSent(function ($request) {
            $text = (string) data_get($request->data(), 'text', '');

            return str_contains($text, 'Condition')
                && str_contains($text, 'critical')
                && str_contains($text, 'attempts')
                && str_contains($text, 'exception');
        });
    }
}
