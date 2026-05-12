<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\Alerts\AlertBatchStoreService;
use App\Services\Alerts\AlertEngine;
use App\Services\Alerts\AlertNotificationDispatcherService;
use App\Services\Alerts\Rules\AlertRuleEvaluationSupport;
use App\Services\Alerts\Rules\AlertRuleStrategyRegistry;
use App\Services\Alerts\Rules\Strategies\AvgExecutionTimeAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\FailureCountAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\HorizonOfflineAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\NullAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\QueueBlockedAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\SupervisorOfflineAlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\WorkerOfflineAlertRuleStrategy;
use App\Services\Horizon\HorizonApiProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluate_alert_reports_pending_flush_error_when_batch_store_throws(): void
    {
        $alert = Alert::query()->create([
            'name' => 'pending-flush-error',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'enabled' => true,
        ]);

        $batch = $this->createMock(AlertBatchStoreService::class);
        $batch->method('getLastSentAt')->willReturn(null);
        $batch->method('getPending')->willThrowException(new \RuntimeException('pending boom'));

        $engine = new AlertEngine(
            $batch,
            $this->createMock(AlertNotificationDispatcherService::class),
            $this->private__buildRegistry($this->createMock(HorizonApiProxyService::class)),
        );
        $result = $engine->evaluateAlert($alert);

        $this->assertSame('pending boom', $result['pending_flush_error_message']);
        $this->assertFalse($result['triggered']);
    }

    public function test_evaluate_alert_returns_error_when_no_services_available(): void
    {
        $alert = Alert::query()->create([
            'name' => 'failure',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);

        $batch = new AlertBatchStoreService;
        $dispatcher = $this->createMock(AlertNotificationDispatcherService::class);
        $registry = $this->private__buildRegistry($this->createMock(HorizonApiProxyService::class));
        $engine = new AlertEngine($batch, $dispatcher, $registry);

        $result = $engine->evaluateAlert($alert);

        $this->assertFalse($result['triggered']);
        $this->assertSame('No services to evaluate alert (add at least one service).', $result['error_message']);
    }

    public function test_evaluate_scheduled_handles_empty_scope_without_errors(): void
    {
        Alert::query()->create([
            'name' => 'offline',
            'rule_type' => Alert::RULE_HORIZON_OFFLINE,
            'enabled' => true,
        ]);

        $engine = new AlertEngine(
            new AlertBatchStoreService,
            $this->createMock(AlertNotificationDispatcherService::class),
            $this->private__buildRegistry($this->createMock(HorizonApiProxyService::class)),
        );

        $engine->evaluateScheduled();
        $this->assertDatabaseCount('alerts', 1);
    }

    public function test_evaluate_scheduled_triggers_alert_and_retry_rebuilds_missing_events(): void
    {
        $service = Service::query()->create(['name' => 'svc-sch', 'base_url' => 'https://sch.test', 'status' => 'online']);
        $provider = NotificationProvider::query()->create([
            'name' => 'mail-sch',
            'type' => NotificationProvider::TYPE_EMAIL,
            'config' => ['to' => ['alerts@example.com']],
        ]);
        $alert = Alert::query()->create([
            'name' => 'scheduled-failure',
            'service_ids' => [$service->id],
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);
        $alert->notificationProviders()->sync([$provider->id]);

        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('getFailedJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => [['id' => 'uuid-z', 'failed_at' => now()->toIso8601String(), 'queue' => '', 'payload' => []]]],
        ]);

        $dispatcher = $this->createMock(AlertNotificationDispatcherService::class);
        $dispatcher->expects($this->exactly(2))->method('dispatch');

        $engine = new AlertEngine(new AlertBatchStoreService, $dispatcher, $this->private__buildRegistry($api));
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

    public function test_evaluate_with_triggering_jobs_returns_strategy_output_for_known_rule(): void
    {
        $service = Service::query()->create(['name' => 'svc-strategy', 'base_url' => 'https://strategy.test', 'status' => 'online']);
        $alert = Alert::query()->create([
            'name' => 'failure-known',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);
        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('getFailedJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => [['id' => 'job-a', 'failed_at' => now()->toIso8601String(), 'queue' => '', 'payload' => []]]],
        ]);
        $engine = new AlertEngine(
            new AlertBatchStoreService,
            $this->createMock(AlertNotificationDispatcherService::class),
            $this->private__buildRegistry($api),
        );
        $result = $engine->evaluateWithTriggeringJobs($alert, $service->id, null);

        $this->assertTrue($result['triggered']);
        $this->assertSame(['job-a'], $result['job_uuids']);
    }

    public function test_flush_pending_alerts_handles_dispatcher_exceptions_without_stopping(): void
    {
        $service = Service::query()->create(['name' => 'svc-ex', 'base_url' => 'https://ex.test', 'status' => 'online']);
        $provider = NotificationProvider::query()->create([
            'name' => 'mail-ex',
            'type' => NotificationProvider::TYPE_EMAIL,
            'config' => ['to' => ['alerts@example.com']],
        ]);
        $alert = Alert::query()->create([
            'name' => 'failure-ex',
            'service_ids' => [$service->id],
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);
        $alert->notificationProviders()->sync([$provider->id]);

        $batch = new AlertBatchStoreService;
        $batch->setPending($alert, [['service_id' => $service->id, 'job_uuid' => 'u-ex', 'triggered_at' => now()->toIso8601String()]]);

        $dispatcher = $this->createMock(AlertNotificationDispatcherService::class);
        $dispatcher->method('dispatch')->willThrowException(new \RuntimeException('dispatch fail'));

        $engine = new AlertEngine($batch, $dispatcher, $this->private__buildRegistry($this->createMock(HorizonApiProxyService::class)));
        $engine->flushPendingAlerts();

        $this->assertNotSame([], $batch->getPending($alert));
    }

    public function test_flush_pending_alerts_sends_when_due_and_clears_pending(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);
        $provider = NotificationProvider::query()->create([
            'name' => 'mail-2',
            'type' => NotificationProvider::TYPE_EMAIL,
            'config' => ['to' => ['alerts@example.com']],
        ]);
        $alert = Alert::query()->create([
            'name' => 'failure-pending',
            'service_ids' => [$service->id],
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);
        $alert->notificationProviders()->sync([$provider->id]);

        $batch = new AlertBatchStoreService;
        $batch->setPending($alert, [['service_id' => $service->id, 'job_uuid' => 'u1', 'triggered_at' => now()->toIso8601String()]]);

        $dispatcher = $this->createMock(AlertNotificationDispatcherService::class);
        $dispatcher->expects($this->once())->method('dispatch');
        $engine = new AlertEngine($batch, $dispatcher, $this->private__buildRegistry($this->createMock(HorizonApiProxyService::class)));

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
            'rule_type' => Alert::RULE_FAILURE_COUNT,
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

        $batch = new AlertBatchStoreService;
        $dispatcher = $this->createMock(AlertNotificationDispatcherService::class);
        $dispatcher->expects($this->once())->method('dispatch');
        $registry = $this->private__buildRegistry($this->createMock(HorizonApiProxyService::class));
        $engine = new AlertEngine($batch, $dispatcher, $registry);

        $engine->retryAlertLog($sent);
        $this->assertDatabaseCount('alert_logs', 2);

        $engine->retryAlertLog($failed);
        $this->assertDatabaseCount('alert_logs', 3);
    }

    private function private__buildRegistry(HorizonApiProxyService $api): AlertRuleStrategyRegistry
    {
        $support = new AlertRuleEvaluationSupport($api);

        return new AlertRuleStrategyRegistry(
            new NullAlertRuleStrategy,
            new FailureCountAlertRuleStrategy($support),
            new AvgExecutionTimeAlertRuleStrategy($support),
            new QueueBlockedAlertRuleStrategy($support),
            new WorkerOfflineAlertRuleStrategy,
            new SupervisorOfflineAlertRuleStrategy($api),
            new HorizonOfflineAlertRuleStrategy($api),
        );
    }
}
