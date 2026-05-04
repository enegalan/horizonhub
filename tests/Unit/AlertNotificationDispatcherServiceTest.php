<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\Alerts\AlertNotificationDispatcherService;
use App\Services\Notifiers\EmailNotifier;
use App\Services\Notifiers\SlackNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AlertNotificationDispatcherServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_routes_notifications_and_skips_empty_configs(): void
    {
        Log::spy();

        $alert = Alert::query()->create([
            'name' => 'notif',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'enabled' => true,
        ]);
        $service = Service::query()->create([
            'name' => 'svc',
            'base_url' => 'https://x.test',
            'status' => 'online',
        ]);
        $log = AlertLog::query()->create([
            'alert_id' => $alert->id,
            'service_id' => $service->id,
            'status' => 'sent',
            'trigger_count' => 1,
            'sent_at' => now(),
        ]);

        $slackProvider = NotificationProvider::query()->create([
            'name' => 's',
            'type' => NotificationProvider::TYPE_SLACK,
            'config' => ['webhook_url' => 'https://hooks.slack.test/abc'],
        ]);
        $emailProvider = NotificationProvider::query()->create([
            'name' => 'e',
            'type' => NotificationProvider::TYPE_EMAIL,
            'config' => ['to' => ['a@example.com']],
        ]);
        $emptyEmailProvider = NotificationProvider::query()->create([
            'name' => 'e2',
            'type' => NotificationProvider::TYPE_EMAIL,
            'config' => ['to' => []],
        ]);
        $alert->notificationProviders()->sync([$slackProvider->id, $emailProvider->id, $emptyEmailProvider->id]);
        $alert->load('notificationProviders');

        $email = $this->createMock(EmailNotifier::class);
        $email->expects($this->once())->method('sendBatched');
        $slack = $this->createMock(SlackNotifier::class);
        $slack->expects($this->once())->method('sendBatched');

        $service = new AlertNotificationDispatcherService($email, $slack);
        $service->dispatch($alert, [['service_id' => 1, 'job_uuid' => null, 'triggered_at' => now()->toIso8601String()]], $log);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_dispatch_marks_log_as_failed_when_notifier_throws(): void
    {
        Log::spy();

        $alert = Alert::query()->create([
            'name' => 'notif2',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'enabled' => true,
        ]);
        $service = Service::query()->create([
            'name' => 'svc',
            'base_url' => 'https://x.test',
            'status' => 'online',
        ]);
        $log = AlertLog::query()->create([
            'alert_id' => $alert->id,
            'service_id' => $service->id,
            'status' => 'sent',
            'trigger_count' => 1,
            'sent_at' => now(),
        ]);
        $provider = NotificationProvider::query()->create([
            'name' => 's',
            'type' => NotificationProvider::TYPE_SLACK,
            'config' => ['webhook_url' => 'https://hooks.slack.test/abc'],
        ]);
        $alert->notificationProviders()->sync([$provider->id]);
        $alert->load('notificationProviders');

        $email = $this->createMock(EmailNotifier::class);
        $slack = $this->createMock(SlackNotifier::class);
        $slack->method('sendBatched')->willThrowException(new \RuntimeException('boom'));

        $service = new AlertNotificationDispatcherService($email, $slack);
        $service->dispatch($alert, [['service_id' => 1, 'job_uuid' => null, 'triggered_at' => now()->toIso8601String()]], $log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('boom', $log->failure_message);
    }
}
