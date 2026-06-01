<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\Alerts\Engine\AlertNotificationDispatcher;
use App\Services\Notifiers\DiscordNotifierService;
use App\Services\Notifiers\EmailNotifierService;
use App\Services\Notifiers\SlackNotifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AlertNotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

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
            'type' => SlackNotifierService::type(),
            'config' => ['webhook_url' => 'https://hooks.slack.test/abc'],
        ]);
        $alert->notificationProviders()->sync([$provider->id]);
        $alert->load('notificationProviders');

        $discord = $this->createMock(DiscordNotifierService::class);
        $email = $this->createMock(EmailNotifierService::class);
        $slack = $this->createMock(SlackNotifierService::class);
        $slack->method('sendBatched')->willThrowException(new \RuntimeException('boom'));

        $dispatcher = $this->private__dispatcherWithNotifiers($discord, $email, $slack);
        $dispatcher->dispatch($alert, [['service_id' => 1, 'job_uuid' => null, 'triggered_at' => now()->toIso8601String()]], $log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('boom', $log->failure_message);
    }

    public function test_dispatch_routes_notifications_and_skips_empty_configs(): void
    {
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
            'type' => SlackNotifierService::type(),
            'config' => ['webhook_url' => 'https://hooks.slack.test/abc'],
        ]);
        $emailProvider = NotificationProvider::query()->create([
            'name' => 'e',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['a@example.com']],
        ]);
        $discordProvider = NotificationProvider::query()->create([
            'name' => 'd',
            'type' => DiscordNotifierService::type(),
            'config' => ['webhook_url' => 'https://discord.com/api/webhooks/1/token'],
        ]);
        $emptyEmailProvider = NotificationProvider::query()->create([
            'name' => 'e2',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => []],
        ]);
        $alert->notificationProviders()->sync([$slackProvider->id, $emailProvider->id, $discordProvider->id, $emptyEmailProvider->id]);
        $alert->load('notificationProviders');

        $discord = $this->createMock(DiscordNotifierService::class);
        $discord->expects($this->once())->method('sendBatched');
        $email = $this->createMock(EmailNotifierService::class);
        $email->expects($this->once())->method('sendBatched');
        $slack = $this->createMock(SlackNotifierService::class);
        $slack->expects($this->once())->method('sendBatched');

        $dispatcher = $this->private__dispatcherWithNotifiers($discord, $email, $slack);
        $dispatcher->dispatch($alert, [['service_id' => 1, 'job_uuid' => null, 'triggered_at' => now()->toIso8601String()]], $log);
    }

    private function private__dispatcherWithNotifiers(
        DiscordNotifierService $discord,
        EmailNotifierService $email,
        SlackNotifierService $slack,
    ): AlertNotificationDispatcher {
        $this->app->instance(DiscordNotifierService::class, $discord);
        $this->app->instance(EmailNotifierService::class, $email);
        $this->app->instance(SlackNotifierService::class, $slack);

        return $this->app->make(AlertNotificationDispatcher::class);
    }
}
