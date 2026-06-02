<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\Alerts\Engine\AlertBatchStore;
use App\Services\Alerts\Engine\AlertEngine;
use App\Services\Alerts\Rules\AlertRuleStrategyRegistry;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Alerts\Rules\Strategies\HorizonOffline;
use App\Services\Horizon\HorizonClientService;
use App\Services\Notifiers\DiscordNotifierService;
use App\Services\Notifiers\EmailNotifierService;
use App\Services\Notifiers\SlackNotifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_marks_log_as_failed_when_notifier_throws(): void
    {
        $alert = Alert::query()->create([
            'name' => 'notif2',
            'rule_type' => FailureCount::type(),
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

        $engine = $this->private__engineWithNotifiers($discord, $email, $slack);
        $engine->dispatch($alert, [['service_id' => 1, 'job_uuid' => null, 'triggered_at' => now()->toIso8601String()]], $log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('boom', $log->failure_message);
    }

    public function test_dispatch_routes_notifications_and_skips_empty_configs(): void
    {
        $alert = Alert::query()->create([
            'name' => 'notif',
            'rule_type' => FailureCount::type(),
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

        $engine = $this->private__engineWithNotifiers($discord, $email, $slack);
        $engine->dispatch($alert, [['service_id' => 1, 'job_uuid' => null, 'triggered_at' => now()->toIso8601String()]], $log);
    }

    public function test_evaluate_alert_does_not_error_when_services_exist_but_alert_does_not_trigger(): void
    {
        $service = Service::query()->create(['name' => 'svc-ok', 'base_url' => 'https://ok.test', 'status' => 'online']);
        $alert = Alert::query()->create([
            'name' => 'no-trigger',
            'service_ids' => [$service->id],
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);

        $api = $this->createMock(HorizonClientService::class);
        $api->method('getFailedJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => []],
        ]);

        $engine = new AlertEngine(
            new AlertBatchStore,
            $this->private__resolveRegistry($api),
        );

        $result = $engine->evaluateAlert($alert);

        $this->assertFalse($result['triggered']);
        $this->assertNull($result['error_message']);
    }

    public function test_evaluate_alert_reports_pending_flush_error_when_batch_store_throws(): void
    {
        $alert = Alert::query()->create([
            'name' => 'pending-flush-error',
            'rule_type' => FailureCount::type(),
            'enabled' => true,
        ]);

        $batch = $this->createMock(AlertBatchStore::class);
        $batch->method('getLastSentAt')->willReturn(null);
        $batch->method('getPending')->willThrowException(new \RuntimeException('pending boom'));

        $engine = new AlertEngine(
            $batch,
            $this->private__resolveRegistry($this->createMock(HorizonClientService::class)),
        );
        $result = $engine->evaluateAlert($alert);

        $this->assertSame('pending boom', $result['pending_flush_error_message']);
        $this->assertFalse($result['triggered']);
    }

    public function test_evaluate_alert_returns_error_when_no_services_available(): void
    {
        $alert = Alert::query()->create([
            'name' => 'failure',
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);

        $batch = new AlertBatchStore;
        $registry = $this->private__resolveRegistry($this->createMock(HorizonClientService::class));
        $engine = new AlertEngine($batch, $registry);

        $result = $engine->evaluateAlert($alert);

        $this->assertFalse($result['triggered']);
        $this->assertSame('No enabled services to evaluate alert (enable at least one service).', $result['error_message']);
    }

    public function test_evaluate_scheduled_handles_empty_scope_without_errors(): void
    {
        Alert::query()->create([
            'name' => 'offline',
            'rule_type' => HorizonOffline::type(),
            'enabled' => true,
        ]);

        $engine = new AlertEngine(
            new AlertBatchStore,
            $this->private__resolveRegistry($this->createMock(HorizonClientService::class)),
        );

        $engine->evaluateScheduled();
        $this->assertDatabaseCount('alerts', 1);
    }

    public function test_evaluate_scheduled_triggers_alert_and_retry_rebuilds_missing_events(): void
    {
        $service = Service::query()->create(['name' => 'svc-sch', 'base_url' => 'https://sch.test', 'status' => 'online']);
        $provider = NotificationProvider::query()->create([
            'name' => 'mail-sch',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['alerts@example.com']],
        ]);
        $alert = Alert::query()->create([
            'name' => 'scheduled-failure',
            'service_ids' => [$service->id],
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);
        $alert->notificationProviders()->sync([$provider->id]);

        $api = $this->createMock(HorizonClientService::class);
        $api->method('getFailedJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => [['id' => 'uuid-z', 'failed_at' => now()->toIso8601String(), 'queue' => '', 'payload' => []]]],
        ]);

        $engine = $this->getMockBuilder(AlertEngine::class)
            ->setConstructorArgs([new AlertBatchStore, $this->private__resolveRegistry($api)])
            ->onlyMethods(['dispatch'])
            ->getMock();
        $engine->expects($this->exactly(2))->method('dispatch');

        $engine->evaluateScheduled();
        $this->assertDatabaseCount('alert_logs', 1);

        $failedLog = AlertLog::query()->create([
            'alert_id' => $alert->id,
            'service_id' => $service->id,
            'trigger_count' => 3,
            'job_uuids' => ['u1'],
            'status' => 'failed',
            'sent_at' => now(),
        ]);
        $engine->retryAlertLog($failedLog);

        $retried = AlertLog::query()->latest('id')->first();
        $this->assertSame(3, $retried->trigger_count);
        $this->assertSame(['u1'], $retried->job_uuids);
    }

    public function test_flush_pending_alerts_handles_dispatcher_exceptions_without_stopping(): void
    {
        $service = Service::query()->create(['name' => 'svc-ex', 'base_url' => 'https://ex.test', 'status' => 'online']);
        $provider = NotificationProvider::query()->create([
            'name' => 'mail-ex',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['alerts@example.com']],
        ]);
        $alert = Alert::query()->create([
            'name' => 'failure-ex',
            'service_ids' => [$service->id],
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);
        $alert->notificationProviders()->sync([$provider->id]);

        $batch = new AlertBatchStore;
        $batch->setPending($alert, [['service_id' => $service->id, 'job_uuid' => 'u-ex', 'triggered_at' => now()->toIso8601String()]]);

        $engine = $this->getMockBuilder(AlertEngine::class)
            ->setConstructorArgs([$batch, $this->private__resolveRegistry($this->createMock(HorizonClientService::class))])
            ->onlyMethods(['dispatch'])
            ->getMock();
        $engine->method('dispatch')->willThrowException(new \RuntimeException('dispatch fail'));

        $engine->flushPendingAlerts();

        $this->assertNotSame([], $batch->getPending($alert));
    }

    public function test_flush_pending_alerts_sends_when_due_and_clears_pending(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);
        $provider = NotificationProvider::query()->create([
            'name' => 'mail-2',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['alerts@example.com']],
        ]);
        $alert = Alert::query()->create([
            'name' => 'failure-pending',
            'service_ids' => [$service->id],
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);
        $alert->notificationProviders()->sync([$provider->id]);

        $batch = new AlertBatchStore;
        $batch->setPending($alert, [['service_id' => $service->id, 'job_uuid' => 'u1', 'triggered_at' => now()->toIso8601String()]]);

        $engine = $this->getMockBuilder(AlertEngine::class)
            ->setConstructorArgs([$batch, $this->private__resolveRegistry($this->createMock(HorizonClientService::class))])
            ->onlyMethods(['dispatch'])
            ->getMock();
        $engine->expects($this->once())->method('dispatch');

        $engine->flushPendingAlerts();

        $this->assertSame([], $batch->getPending($alert));
    }

    public function test_retry_alert_log_only_processes_failed_logs_with_alert(): void
    {
        $service = Service::query()->create([
            'name' => 'svc',
            'base_url' => 'https://x.test',
            'status' => 'online',
        ]);
        $alert = Alert::query()->create([
            'name' => 'a',
            'rule_type' => FailureCount::type(),
            'enabled' => true,
        ]);
        $failed = AlertLog::query()->create([
            'alert_id' => $alert->id,
            'service_id' => $service->id,
            'trigger_count' => 2,
            'job_uuids' => ['u1'],
            'status' => 'failed',
            'sent_at' => now(),
        ]);
        $sent = AlertLog::query()->create([
            'alert_id' => $alert->id,
            'service_id' => $service->id,
            'trigger_count' => 1,
            'job_uuids' => ['u2'],
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $batch = new AlertBatchStore;
        $registry = $this->private__resolveRegistry($this->createMock(HorizonClientService::class));
        $engine = $this->getMockBuilder(AlertEngine::class)
            ->setConstructorArgs([$batch, $registry])
            ->onlyMethods(['dispatch'])
            ->getMock();
        $engine->expects($this->once())->method('dispatch');

        $engine->retryAlertLog($sent);
        $this->assertDatabaseCount('alert_logs', 2);

        $engine->retryAlertLog($failed);
        $this->assertDatabaseCount('alert_logs', 3);
    }

    private function private__engineWithNotifiers(
        DiscordNotifierService $discord,
        EmailNotifierService $email,
        SlackNotifierService $slack,
    ): AlertEngine {
        $this->app->instance(DiscordNotifierService::class, $discord);
        $this->app->instance(EmailNotifierService::class, $email);
        $this->app->instance(SlackNotifierService::class, $slack);

        return new AlertEngine(
            new AlertBatchStore,
            $this->private__resolveRegistry($this->createMock(HorizonClientService::class)),
        );
    }

    private function private__resolveRegistry(HorizonClientService $api): AlertRuleStrategyRegistry
    {
        $this->app->instance(HorizonClientService::class, $api);

        return $this->app->make(AlertRuleStrategyRegistry::class);
    }
}
