<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\HorizonSupervisorState;
use App\Models\Service;
use App\Services\AlertEngine;
use App\Services\AlertRuleEvaluator;
use App\Services\EmailNotifier;
use App\Services\SlackNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertEngineTest extends TestCase {
    use RefreshDatabase;

    private function createEngine(): AlertEngine {
        return new AlertEngine(
            $this->createMock(EmailNotifier::class),
            $this->createMock(SlackNotifier::class),
            new AlertRuleEvaluator()
        );
    }

    public function test_job_specific_failure_creates_alert_log_when_job_failed(): void {
        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ]);
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'job_specific_failure',
            'threshold' => array(),
            'notification_channels' => array(),
        ]);
        $job = HorizonJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'uuid-1',
            'queue' => 'default',
            'status' => 'failed',
            'failed_at' => \now(),
        ]);
        HorizonFailedJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'uuid-1',
            'queue' => 'default',
            'payload' => array(),
            'exception' => 'err',
            'failed_at' => \now(),
        ]);
        $this->createEngine()->evaluateAfterEvent($service->id, 'JobFailed', $job->id);

        $this->assertSame(1, AlertLog::count());
        $log = AlertLog::first();
        $this->assertNotNull($log);
        $this->assertSame($alert->id, $log->alert_id);
        $this->assertSame($service->id, $log->service_id);
        $this->assertSame('sent', $log->status);
    }

    public function test_job_type_failure_creates_alert_log_when_matching_job_failed(): void {
        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ]);
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'job_type_failure',
            'job_type' => 'SendEmail',
            'threshold' => array(),
            'notification_channels' => array(),
        ]);
        HorizonFailedJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'u1',
            'queue' => 'default',
            'payload' => array('displayName' => 'App\\Jobs\\SendEmail'),
            'exception' => 'err',
            'failed_at' => \now(),
        ]);
        $this->createEngine()->evaluateAfterEvent($service->id, 'JobFailed', null);

        $this->assertSame(1, AlertLog::count());
        $log = AlertLog::first();
        $this->assertNotNull($log);
        $this->assertSame($alert->id, $log->alert_id);
        $this->assertSame($service->id, $log->service_id);
    }

    public function test_failure_count_rule_creates_alert_log_when_threshold_met(): void {
        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ]);
        Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'failure_count',
            'threshold' => ['count' => 2, 'minutes' => 60],
            'notification_channels' => [],
        ]);
        HorizonFailedJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'u1',
            'queue' => 'default',
            'payload' => [],
            'exception' => 'err',
            'failed_at' => \now(),
        ]);
        HorizonFailedJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'u2',
            'queue' => 'default',
            'payload' => [],
            'exception' => 'err',
            'failed_at' => \now(),
        ]);
        $this->createEngine()->evaluateAfterEvent($service->id, 'JobFailed', null);

        $this->assertSame(1, AlertLog::count());
        $log = AlertLog::first();
        $this->assertNotNull($log);
        $this->assertSame('sent', $log->status);
        $this->assertSame($service->id, $log->service_id);
    }

    public function test_avg_execution_time_creates_alert_log_when_threshold_exceeded(): void {
        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ]);
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'avg_execution_time',
            'threshold' => array('seconds' => 10, 'minutes' => 60),
            'notification_channels' => array(),
        ]);
        HorizonJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'j1',
            'queue' => 'default',
            'status' => 'processed',
            'processed_at' => \now(),
            'queued_at' => \now()->subSeconds(20),
            'runtime_seconds' => 20,
        ]);
        $this->createEngine()->evaluateScheduled();

        $this->assertSame(1, AlertLog::count());
        $log = AlertLog::first();
        $this->assertNotNull($log);
        $this->assertSame($alert->id, $log->alert_id);
        $this->assertSame($service->id, $log->service_id);
    }

    public function test_queue_blocked_creates_alert_log_when_last_processed_too_old(): void {
        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ]);
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'queue_blocked',
            'threshold' => array('minutes' => 30),
            'notification_channels' => array(),
        ]);
        HorizonJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'j1',
            'queue' => 'default',
            'status' => 'processed',
            'processed_at' => \now()->subMinutes(45),
        ]);
        $this->createEngine()->evaluateScheduled();

        $this->assertSame(1, AlertLog::count());
        $log = AlertLog::first();
        $this->assertNotNull($log);
        $this->assertSame($alert->id, $log->alert_id);
        $this->assertSame($service->id, $log->service_id);
    }

    public function test_worker_offline_creates_alert_log_when_last_seen_too_old(): void {
        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
            'last_seen_at' => \now()->subMinutes(10),
        ]);
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'worker_offline',
            'threshold' => array('minutes' => 5),
            'notification_channels' => array(),
        ]);
        $this->createEngine()->evaluateScheduled();

        $this->assertSame(1, AlertLog::count());
        $log = AlertLog::first();
        $this->assertNotNull($log);
        $this->assertSame($alert->id, $log->alert_id);
        $this->assertSame($service->id, $log->service_id);
    }

    public function test_supervisor_offline_creates_alert_log_when_supervisor_stale(): void {
        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ]);
        HorizonSupervisorState::create([
            'service_id' => $service->id,
            'name' => 'supervisor-default',
            'last_seen_at' => \now()->subMinutes(10),
        ]);
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'supervisor_offline',
            'threshold' => array('minutes' => 5),
            'notification_channels' => array(),
        ]);
        $this->createEngine()->evaluateScheduled();

        $this->assertSame(1, AlertLog::count());
        $log = AlertLog::first();
        $this->assertNotNull($log);
        $this->assertSame($alert->id, $log->alert_id);
        $this->assertSame($service->id, $log->service_id);
    }
}
